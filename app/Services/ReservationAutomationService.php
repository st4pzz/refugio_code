<?php
declare(strict_types=1);

namespace Refugio\Services;

use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

final class ReservationAutomationService
{
    public const EVENTS=['RESERVATION_REQUEST_CREATED','RESERVATION_APPROVED','RESERVATION_REJECTED','QUOTE_CREATED','QUOTE_SENT','QUOTE_EXPIRING','PAYMENT_REQUEST_CREATED','PAYMENT_CONFIRMED','PAYMENT_EXPIRED','CONTRACT_READY','CONTRACT_SENT','CONTRACT_VIEWED','CONTRACT_SIGNED','PRECHECKIN_AVAILABLE','PRECHECKIN_REMINDER','PRECHECKIN_APPROVED','CHECKIN_UPCOMING','CHECKIN_DAY','CHECKOUT_UPCOMING','CHECKOUT_DAY','RESERVATION_COMPLETED','REVIEW_INVITATION_AVAILABLE'];

    public function __construct(private PDO $db,private array $config=[])
    {
    }

    public function emit(string $event,int $reservationId,array $context=[],?string $eventKey=null):int
    {
        return $this->enqueueRules($event,$reservationId,$context,$eventKey,null);
    }

    public function emitRule(string $ruleCode,string $event,int $reservationId,array $context=[],?string $eventKey=null):int
    {
        if(trim($ruleCode)==='')throw new RuntimeException('Regra de automação inválida.');
        return $this->enqueueRules($event,$reservationId,$context,$eventKey,$ruleCode);
    }

    private function enqueueRules(string $event,int $reservationId,array $context,?string $eventKey,?string $ruleCode):int
    {
        if(!in_array($event,self::EVENTS,true))throw new RuntimeException('Evento de automação desconhecido.');
        $stmt=$this->db->prepare('SELECT * FROM reservas WHERE id=?');$stmt->execute([$reservationId]);$reservation=$stmt->fetch()?:throw new RuntimeException('Reserva não encontrada para automação.');
        $sql='SELECT * FROM automation_rules WHERE trigger_event=? AND ativo=1';
        $params=[$event];
        if($ruleCode!==null){$sql.=' AND code=?';$params[]=$ruleCode;}
        $rules=$this->db->prepare($sql.' ORDER BY id');$rules->execute($params);
        $queue=new JobQueueService($this->db);$count=0;
        foreach($rules->fetchAll() as $rule){
            $scheduled=$this->scheduleAt($rule,$reservation,$context);$key=$eventKey??hash('sha256',$event.'|'.$reservationId.'|'.$scheduled->format('Y-m-d H:i:s'));
            $stmt=$this->db->prepare("INSERT INTO automation_runs (rule_id,reservation_id,event_name,event_key,scheduled_at,status,rendered_payload_json) VALUES (?,?,?,?,?,'SCHEDULED',?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
            $stmt->execute([$rule['id'],$reservationId,$event,$key,$scheduled->format('Y-m-d H:i:s'),json_encode(['context_keys'=>array_keys($context)],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
            $runId=(int)$this->db->lastInsertId();
            $jobId=$queue->enqueue('RESERVATION_AUTOMATION',['run_id'=>$runId],'automation:'.$runId,80,5,$scheduled);
            $this->db->prepare("UPDATE automation_runs SET status='QUEUED',job_id=? WHERE id=? AND status='SCHEDULED'")->execute([$jobId,$runId]);$count++;
        }
        return $count;
    }

    public function scheduleMilestones(int $reservationId):int
    {
        $count=0;
        foreach(['CHECKIN_UPCOMING','CHECKIN_DAY','CHECKOUT_UPCOMING','CHECKOUT_DAY'] as $event)$count+=$this->emit($event,$reservationId,[],'milestone:'.$event);
        return $count;
    }

    public function process(int $runId):void
    {
        $stmt=$this->db->prepare('SELECT ar.*,ar.status automation_status,au.code,au.channels_json,au.subject_template,au.body_template,r.*,r.status reservation_status FROM automation_runs ar JOIN automation_rules au ON au.id=ar.rule_id JOIN reservas r ON r.id=ar.reservation_id WHERE ar.id=?');$stmt->execute([$runId]);$run=$stmt->fetch()?:throw new RuntimeException('Execução de automação não encontrada.');
        if(in_array($run['automation_status'],['SENT','SKIPPED','CANCELLED'],true))return;
        if(in_array($run['reservation_status'],['CANCELADA','RECUSADA','EXPIRADA'],true)&&!in_array($run['event_name'],['RESERVATION_REJECTED','PAYMENT_EXPIRED'],true)){$this->mark($runId,'SKIPPED');return;}
        $portal=new GuestPortalService($this->db,$this->config);$token=$portal->tokenForReservation((int)$run['reservation_id']);if($token===null)$token=$portal->regenerate((int)$run['reservation_id']);
        $payment=$this->first('SELECT * FROM pagamentos WHERE reserva_id=? ORDER BY data_vencimento,id',[(int)$run['reservation_id']]);
        $contract=$this->first('SELECT * FROM reservation_contracts WHERE reservation_id=? AND status<>\'SUPERSEDED\' ORDER BY version_no DESC LIMIT 1',[(int)$run['reservation_id']]);
        $settings=(new PropertySettingsService($this->db))->values();
        $values=[
            'first_name'=>explode(' ',trim((string)$run['nome_cliente']))[0]??'','reservation_code'=>$run['codigo'],'checkin'=>date('d/m/Y',strtotime($run['checkin'])),'checkout'=>date('d/m/Y',strtotime($run['checkout'])),'guests'=>$run['quantidade_hospedes'],
            'total'=>money($run['valor_total']??0),'payment_due'=>!empty($payment['data_vencimento'])?date('d/m/Y H:i',strtotime($payment['data_vencimento'])):'','payment_link'=>base_url('reserva/'.$run['token_publico']),
            'portal_link'=>base_url('minha-reserva/'.$token),'contract_link'=>base_url('minha-reserva/'.$token.'#contrato'),'precheckin_link'=>base_url('minha-reserva/'.$token.'/pre-checkin'),
            'checkin_time'=>$settings['DEFAULT_CHECKIN_TIME']??'','checkout_time'=>$settings['DEFAULT_CHECKOUT_TIME']??'',
        ];
        $subject=$this->render((string)$run['subject_template'],$values);$body=$this->render((string)$run['body_template'],$values);$channels=json_decode((string)$run['channels_json'],true);$channels=is_array($channels)?$channels:['EMAIL'];
        $template=$this->whatsAppTemplate((string)$run['code']);
        $this->db->prepare("UPDATE automation_runs SET status='PROCESSING' WHERE id=?")->execute([$runId]);
        try{
            (new NotificationService($this->db))->automation($run,$run['event_name'],$subject,$body,$channels,$template,array_values($values));
            if($run['event_name']==='CONTRACT_READY'&&!empty($contract['id'])){$this->db->prepare("UPDATE reservation_contracts SET status='SENT',sent_at=NOW() WHERE id=? AND status='READY'")->execute([$contract['id']]);$this->emit('CONTRACT_SENT',(int)$run['reservation_id'],[],'contract-sent:'.$contract['id']);}
            $safeValues=$values;foreach(['payment_link','portal_link','contract_link','precheckin_link','review_link'] as $key)if(array_key_exists($key,$safeValues))$safeValues[$key]='[protected-link]';
            $this->db->prepare("UPDATE automation_runs SET status='SENT',rendered_payload_json=?,executed_at=NOW(),error_message=NULL WHERE id=?")->execute([json_encode(['subject'=>$subject,'variables'=>$safeValues],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$runId]);
        }catch(Throwable $error){$this->db->prepare("UPDATE automation_runs SET status='FAILED',error_message=? WHERE id=?")->execute([mb_substr($error->getMessage(),0,4000),$runId]);throw $error;}
    }

    private function scheduleAt(array $rule,array $reservation,array $context):DateTimeImmutable
    {
        $base=match($rule['schedule_anchor']){
            'CHECKIN'=>new DateTimeImmutable($reservation['checkin'].' 00:00:00'),
            'CHECKOUT'=>new DateTimeImmutable($reservation['checkout'].' 00:00:00'),
            'PAYMENT_DUE'=>new DateTimeImmutable((string)($context['payment_due']??$reservation['prazo_pagamento']??'now')),
            'QUOTE_EXPIRY'=>new DateTimeImmutable((string)($context['quote_expires_at']??'now')),
            default=>new DateTimeImmutable(),
        };
        $minutes=(int)$rule['offset_minutes'];$scheduled=$minutes===0?$base:$base->modify(($minutes>0?'+':'').$minutes.' minutes');return $scheduled<new DateTimeImmutable()?new DateTimeImmutable():$scheduled;
    }
    private function render(string $template,array $values):string
    {
        $replace=[];foreach($values as $key=>$value)$replace['{{'.$key.'}}']=(string)$value;
        $rendered=strtr($template,$replace);
        if(preg_match('/\{\{\s*[a-zA-Z0-9_]+\s*\}\}/',$rendered))throw new RuntimeException('A automação possui variáveis não resolvidas e não será enviada.');
        return $rendered;
    }
    private function first(string $sql,array $params):array{$stmt=$this->db->prepare($sql);$stmt->execute($params);return $stmt->fetch()?:[];}
    private function mark(int $id,string $status):void{$this->db->prepare('UPDATE automation_runs SET status=?,executed_at=NOW() WHERE id=?')->execute([$status,$id]);}
    private function whatsAppTemplate(string $code):?string
    {
        $key=match($code){'APPROVAL_QUOTE'=>'WHATSAPP_TEMPLATE_ORCAMENTO','PIX_CHARGE','PAYMENT_REMINDER'=>'WHATSAPP_TEMPLATE_COBRANCA','PAYMENT_CONFIRMED'=>'WHATSAPP_TEMPLATE_PAGAMENTO_CONFIRMADO','CONTRACT_AVAILABLE'=>'WHATSAPP_TEMPLATE_CONTRATO','CONTRACT_REMINDER'=>'WHATSAPP_TEMPLATE_LEMBRETE_CONTRATO','PRECHECKIN_AVAILABLE'=>'WHATSAPP_TEMPLATE_PRECHECKIN','PRECHECKIN_REMINDER'=>'WHATSAPP_TEMPLATE_LEMBRETE_PRECHECKIN','CHECKIN_3_DAYS','CHECKIN_1_DAY'=>'WHATSAPP_TEMPLATE_CHECKIN_PROXIMO','CHECKIN_TODAY'=>'WHATSAPP_TEMPLATE_CHECKIN_DIA','CHECKOUT_EVE','CHECKOUT_TODAY'=>'WHATSAPP_TEMPLATE_CHECKOUT','THANK_YOU'=>'WHATSAPP_TEMPLATE_AGRADECIMENTO','REVIEW_INVITATION'=>'WHATSAPP_TEMPLATE_AVALIACAO',default=>null};
        if($key===null)return null;$value=(new PropertySettingsService($this->db))->get($key,null,'communication');return is_string($value)&&$value!==''?$value:null;
    }
}

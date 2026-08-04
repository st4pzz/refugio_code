<?php
declare(strict_types=1);

namespace Refugio\Services;

use DateTimeImmutable;
use PDO;
use RuntimeException;
use Refugio\Support\Env;

final class GuestPortalService
{
    private EncryptionService $encryption;

    public function __construct(private PDO $db, private array $appConfig = [], ?EncryptionService $encryption = null)
    {
        $this->encryption=$encryption??new EncryptionService(Env::get('PORTAL_TOKEN_ENCRYPTION_KEY'));
    }

    public function regenerate(int $reservationId, ?int $userId = null, ?DateTimeImmutable $expiresAt = null): string
    {
        $token=bin2hex(random_bytes(32));
        $this->db->beginTransaction();
        try{
            $stmt=$this->db->prepare('SELECT id FROM reservas WHERE id=? FOR UPDATE');$stmt->execute([$reservationId]);if(!$stmt->fetchColumn())throw new RuntimeException('Reserva não encontrada.');
            $this->db->prepare("UPDATE guest_portal_tokens SET status='REVOKED',revoked_at=NOW(),revoked_reason='TOKEN_REGENERATED' WHERE reservation_id=? AND status='ACTIVE'")
                ->execute([$reservationId]);
            $this->db->prepare("INSERT INTO guest_portal_tokens (reservation_id,token_hash,token_prefix,token_encrypted,status,expires_at,created_by) VALUES (?,?,?,?,'ACTIVE',?,?)")
                ->execute([$reservationId,hash('sha256',$token),substr($token,0,12),$this->encryption->encrypt($token),$expiresAt?->format('Y-m-d H:i:s'),$userId]);
            $this->db->commit();return $token;
        }catch(\Throwable $error){if($this->db->inTransaction())$this->db->rollBack();throw $error;}
    }

    public function tokenForReservation(int $reservationId):?string
    {
        $stmt=$this->db->prepare("SELECT token_encrypted FROM guest_portal_tokens WHERE reservation_id=? AND status='ACTIVE' AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at>NOW()) ORDER BY id DESC LIMIT 1");
        $stmt->execute([$reservationId]);$encrypted=$stmt->fetchColumn();
        return $encrypted!==false?$this->encryption->decrypt((string)$encrypted):null;
    }

    public function resolve(string $token): array
    {
        if(!preg_match('/^[a-f0-9]{64}$/',$token))throw new RuntimeException('Link da reserva inválido.');
        $stmt=$this->db->prepare("SELECT t.id token_record_id,t.reservation_id,r.* FROM guest_portal_tokens t JOIN reservas r ON r.id=t.reservation_id WHERE t.token_hash=? AND t.status='ACTIVE' AND t.revoked_at IS NULL AND (t.expires_at IS NULL OR t.expires_at>NOW())");
        $stmt->execute([hash('sha256',$token)]);$reservation=$stmt->fetch();
        if(!$reservation)throw new RuntimeException('Este link expirou ou foi revogado. Solicite um novo ao atendimento.');
        $this->db->prepare('UPDATE guest_portal_tokens SET last_used_at=NOW(),use_count=use_count+1 WHERE id=?')->execute([$reservation['token_record_id']]);
        return $this->buildPortal($reservation,$token);
    }

    public function reservationId(string $token):int
    {
        if(!preg_match('/^[a-f0-9]{64}$/',$token))throw new RuntimeException('Link da reserva inválido.');
        $stmt=$this->db->prepare("SELECT reservation_id FROM guest_portal_tokens WHERE token_hash=? AND status='ACTIVE' AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at>NOW())");
        $stmt->execute([hash('sha256',$token)]);$id=$stmt->fetchColumn();if(!$id)throw new RuntimeException('Este link expirou ou foi revogado.');return (int)$id;
    }

    private function buildPortal(array $reservation,string $token):array
    {
        $reservationId=(int)$reservation['reservation_id'];
        $payments=$this->rows('SELECT tipo,valor,status,data_vencimento,data_confirmacao,pix_copia_cola FROM pagamentos WHERE reserva_id=? ORDER BY created_at',[$reservationId]);
        $paid=(bool)array_filter($payments,static fn(array $payment):bool=>$payment['status']==='CONFIRMADO');
        $contracts=$this->rows('SELECT id,contract_number,status,ready_at,sent_at,fully_signed_at,pdf_path,pdf_hash FROM reservation_contracts WHERE reservation_id=? AND status<>\'SUPERSEDED\' ORDER BY version_no DESC',[$reservationId]);
        if($paid&&isset($contracts[0])&&in_array($contracts[0]['status'],['READY','SENT'],true)){
            $stmt=$this->db->prepare("UPDATE reservation_contracts SET status='VIEWED' WHERE id=? AND status IN ('READY','SENT')");$stmt->execute([$contracts[0]['id']]);
            if($stmt->rowCount()===1){$contracts[0]['status']='VIEWED';$this->db->prepare("INSERT INTO contract_events (contract_id,event_type,metadata_json,document_hash) SELECT id,'CONTRACT_VIEWED',JSON_OBJECT('channel','GUEST_PORTAL'),content_hash FROM reservation_contracts WHERE id=?")->execute([$contracts[0]['id']]);try{(new ReservationAutomationService($this->db,$this->appConfig))->emit('CONTRACT_VIEWED',$reservationId,[],'contract-viewed:'.$contracts[0]['id']);}catch(\Throwable $error){error_log('[portal-contract-viewed] '.$error->getMessage());}}
        }
        if(isset($contracts[0])){
            $contracts[0]['guest_signed_document']=$this->row("SELECT id,revision_no,original_name,byte_size,sha256,created_at FROM contract_signature_documents WHERE contract_id=? AND stage='GUEST_SIGNED' ORDER BY revision_no DESC LIMIT 1",[(int)$contracts[0]['id']]);
            $contracts[0]['fully_signed_document']=$this->row("SELECT id,revision_no,original_name,byte_size,sha256,created_at FROM contract_signature_documents WHERE contract_id=? AND stage='FULLY_SIGNED' ORDER BY revision_no DESC LIMIT 1",[(int)$contracts[0]['id']]);
        }
        $precheckin=$this->row('SELECT status,submitted_at,reviewed_at,correction_message FROM precheckins WHERE reservation_id=?',[$reservationId]);
        $settings=(new PropertySettingsService($this->db))->values();
        $hours=max(0,(int)($settings['CHECKIN_INSTRUCTIONS_RELEASE_HOURS']??0));
        $checkinAt=new DateTimeImmutable($reservation['checkin'].' '.($settings['DEFAULT_CHECKIN_TIME']??'00:00'));
        $nearCheckin=(new DateTimeImmutable()) >= $checkinAt->modify('-'.$hours.' hours');
        $contractComplete=(bool)array_filter($contracts,static fn(array $contract):bool=>$contract['status']==='FULLY_SIGNED');
        $requirementsMet=(!(bool)($settings['CONTRACT_REQUIRED_BEFORE_CHECKIN']??true)||$contractComplete)&&(!(bool)($settings['PRECHECKIN_REQUIRED']??true)||($precheckin['status']??null)==='APPROVED');
        $releaseSensitive=$paid&&$nearCheckin&&$requirementsMet&&in_array($reservation['status'],['PAGAMENTO_CONFIRMADO','RESERVA_CONFIRMADA','FINALIZADA'],true);
        $timeline=[
            ['label'=>'Solicitação recebida','done'=>true],
            ['label'=>'Aprovada','done'=>!in_array($reservation['status'],['AGUARDANDO_APROVACAO','RECUSADA'],true)],
            ['label'=>'Pagamento confirmado','done'=>$paid],
            ['label'=>'Contrato concluído','done'=>$contractComplete],
            ['label'=>'Pré-check-in aprovado','done'=>($precheckin['status']??null)==='APPROVED'],
            ['label'=>'Instruções liberadas','done'=>$releaseSensitive],
        ];
        return [
            'code'=>$reservation['codigo'],'guest_first_name'=>explode(' ',trim($reservation['nome_cliente']))[0]??'',
            'status'=>$reservation['status'],'checkin'=>$reservation['checkin'],'checkout'=>$reservation['checkout'],'guests'=>(int)$reservation['quantidade_hospedes'],
            'total'=>$reservation['valor_total'],'payments'=>$payments,'timeline'=>$timeline,
            'contract'=>($paid||empty($settings['PAYMENT_REQUIRED_BEFORE_CONTRACT']))?($contracts[0]??null):null,
            'precheckin'=>$paid?$precheckin:null,'precheckin_path'=>'/minha-reserva/'.$token.'/pre-checkin',
            'rules_available'=>$paid,'sensitive_released'=>$releaseSensitive,
            'arrival'=>$releaseSensitive?[
                'address'=>$settings['PROPERTY_FULL_ADDRESS']??null,'directions'=>$settings['ARRIVAL_DIRECTIONS']??null,'access'=>$settings['ACCESS_INSTRUCTIONS']??null,
                'wifi_name'=>$settings['WIFI_NAME']??null,'wifi_password'=>$settings['WIFI_PASSWORD']??null,'emergency_contact'=>$settings['EMERGENCY_CONTACT']??null,
            ]:null,
            'contact'=>['whatsapp'=>$settings['OWNER_PHONE']??($this->appConfig['contact_whatsapp']??null),'email'=>$settings['OWNER_EMAIL']??($this->appConfig['admin_email']??null)],
        ];
    }

    private function rows(string $sql,array $params):array{$stmt=$this->db->prepare($sql);$stmt->execute($params);return $stmt->fetchAll();}
    private function row(string $sql,array $params):?array{$stmt=$this->db->prepare($sql);$stmt->execute($params);$row=$stmt->fetch();return $row?:null;}
}

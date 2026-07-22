<?php
declare(strict_types=1);

namespace Refugio\Services;

use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

final class LocalAuditSignatureProvider implements SignatureProviderInterface
{
    public function __construct(private PDO $db, private string $secret)
    {
        if(strlen($secret)<32)throw new RuntimeException('APP_KEY deve ter pelo menos 32 caracteres para assinatura local.');
    }

    public function issueChallenge(int $contractId,string $role):array
    {
        $role=strtoupper($role);if(!in_array($role,['GUEST','OWNER'],true))throw new RuntimeException('Papel do signatário inválido.');
        $code=(string)random_int(100000,999999);$expires=(new DateTimeImmutable())->modify('+15 minutes');
        $stmt=$this->db->prepare("UPDATE contract_signers SET auth_code_hash=?,auth_code_expires_at=?,auth_attempts=0,auth_used_at=NULL,status='CODE_SENT' WHERE contract_id=? AND signer_role=? AND status NOT IN ('SIGNED','DECLINED')");
        $stmt->execute([$this->hashCode($contractId,$role,$code),$expires->format('Y-m-d H:i:s'),$contractId,$role]);
        if($stmt->rowCount()!==1)throw new RuntimeException('Signatário indisponível para autenticação.');
        $this->event($contractId,null,'AUTH_CODE_ISSUED',['role'=>$role,'expires_at'=>$expires->format(DATE_ATOM)],null);
        return ['code'=>$code,'expires_at'=>$expires->format(DATE_ATOM),'role'=>$role];
    }

    public function sign(int $contractId,string $role,string $code,array $acceptance):array
    {
        $role=strtoupper($role);$name=trim((string)($acceptance['name']??''));$cpf=preg_replace('/\D/','',(string)($acceptance['cpf']??''));
        if($name===''||!in_array(strlen((string)$cpf),[0,11],true)||empty($acceptance['accepted']))throw new RuntimeException('Confirme nome, CPF e aceite do contrato.');
        $this->db->beginTransaction();
        try{
            $stmt=$this->db->prepare('SELECT s.*,c.content_hash,c.status contract_status FROM contract_signers s JOIN reservation_contracts c ON c.id=s.contract_id WHERE s.contract_id=? AND s.signer_role=? FOR UPDATE');$stmt->execute([$contractId,$role]);
            $signer=$stmt->fetch()?:throw new RuntimeException('Signatário não encontrado.');
            if($signer['status']==='SIGNED')throw new RuntimeException('Este signatário já concluiu o aceite.');
            $expectedCpf=preg_replace('/\D/','',(string)($signer['cpf']??''));if($expectedCpf!==''&&!hash_equals($expectedCpf,(string)$cpf))throw new RuntimeException('O CPF não corresponde ao signatário do contrato.');
            if((int)$signer['auth_attempts']>=(int)$signer['auth_max_attempts'])throw new RuntimeException('Limite de tentativas excedido. Solicite novo código.');
            if(empty($signer['auth_code_hash'])||empty($signer['auth_code_expires_at'])||$signer['auth_code_expires_at']<date('Y-m-d H:i:s'))throw new RuntimeException('Código expirado. Solicite um novo.');
            if(!hash_equals((string)$signer['auth_code_hash'],$this->hashCode($contractId,$role,$code))){
                $this->db->prepare("UPDATE contract_signers SET auth_attempts=auth_attempts+1,status=IF(auth_attempts+1>=auth_max_attempts,'LOCKED',status) WHERE id=?")->execute([$signer['id']]);$this->db->commit();throw new RuntimeException('Código inválido.');
            }
            $acceptanceText='Li o documento integralmente e concordo com seu conteúdo.';$acceptanceHash=hash('sha256',$acceptanceText);
            $this->db->prepare("UPDATE contract_signers SET status='SIGNED',auth_used_at=NOW(),auth_code_hash=NULL,accepted_at=NOW(),accepted_name=?,accepted_cpf=?,accepted_ip=?,accepted_user_agent=?,acceptance_text_hash=?,document_hash_at_acceptance=? WHERE id=?")->execute([$name,$cpf?:null,$acceptance['ip']??null,mb_substr((string)($acceptance['user_agent']??''),0,500),$acceptanceHash,$signer['content_hash'],$signer['id']]);
            $this->event($contractId,(int)$signer['id'],'SIGNATURE_ACCEPTED',['role'=>$role,'delivery'=>$acceptance['delivery']??null,'authentication'=>'ONE_TIME_CODE'],$signer['content_hash'],$acceptance['ip']??null,$acceptance['user_agent']??null);
            $statuses=$this->db->prepare('SELECT signer_role,status FROM contract_signers WHERE contract_id=?');$statuses->execute([$contractId]);$signed=[];foreach($statuses->fetchAll() as $row)$signed[$row['signer_role']]=$row['status']==='SIGNED';
            $status=($signed['GUEST']??false)&&($signed['OWNER']??false)?'FULLY_SIGNED':(($signed['GUEST']??false)?'SIGNED_BY_GUEST':'SIGNED_BY_OWNER');
            $this->db->prepare('UPDATE reservation_contracts SET status=?,fully_signed_at=IF(?=\'FULLY_SIGNED\',NOW(),fully_signed_at) WHERE id=?')->execute([$status,$status,$contractId]);
            $this->db->commit();return ['status'=>$status,'signed_at'=>date(DATE_ATOM),'document_hash'=>$signer['content_hash']];
        }catch(Throwable $error){if($this->db->inTransaction())$this->db->rollBack();throw $error;}
    }

    private function hashCode(int $contractId,string $role,string $code):string{return hash_hmac('sha256',$contractId.'|'.$role.'|'.$code,$this->secret);}
    private function event(int $contractId,?int $signerId,string $type,array $meta,?string $hash,?string $ip=null,?string $ua=null):void{$stmt=$this->db->prepare('INSERT INTO contract_events (contract_id,signer_id,event_type,metadata_json,ip,user_agent,document_hash) VALUES (?,?,?,?,?,?,?)');$stmt->execute([$contractId,$signerId,$type,json_encode($meta,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$ip,mb_substr((string)$ua,0,500),$hash]);}
}

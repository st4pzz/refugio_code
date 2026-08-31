<?php
declare(strict_types=1);

namespace Refugio\Services;

use PDO;
use RuntimeException;
use Throwable;

final class PreCheckinService
{
    public const REQUIRED_RULES=['capacity','visitors','hours','quiet','events','pool','barbecue','smoking','pets','furniture','waste','gates','emergency'];

    public function __construct(private PDO $db)
    {
    }

    public function ensure(int $reservationId):array
    {
        $this->db->prepare("INSERT INTO precheckins (reservation_id,status) VALUES (?,'NOT_STARTED') ON DUPLICATE KEY UPDATE reservation_id=VALUES(reservation_id)")->execute([$reservationId]);
        return $this->load($reservationId);
    }

    public function load(int $reservationId):array
    {
        $stmt=$this->db->prepare('SELECT * FROM precheckins WHERE reservation_id=?');$stmt->execute([$reservationId]);$record=$stmt->fetch()?:throw new RuntimeException('Pré-check-in não encontrado.');
        foreach(['guests'=>'reservation_guests','vehicles'=>'reservation_vehicles','pets'=>'reservation_pets'] as $key=>$table){$stmt=$this->db->prepare("SELECT * FROM {$table} WHERE precheckin_id=? ORDER BY id");$stmt->execute([$record['id']]);$record[$key]=$stmt->fetchAll();}
        $stmt=$this->db->prepare('SELECT accepted_items_json FROM house_rule_acceptances WHERE precheckin_id=? ORDER BY accepted_at DESC,id DESC LIMIT 1');
        $stmt->execute([$record['id']]);
        $accepted=json_decode((string)($stmt->fetchColumn()?:'{}'),true);
        $record['accepted_rules']=is_array($accepted)?array_keys($accepted):[];
        return $record;
    }

    public function approvedHouseRules():?array
    {
        $version=$this->db->query("SELECT id,version_no,title,rules_json,content_hash FROM house_rule_versions WHERE status='APPROVED' ORDER BY version_no DESC LIMIT 1")->fetch();
        if(!$version)return null;
        $rules=json_decode((string)$version['rules_json'],true);
        if(!is_array($rules))throw new RuntimeException('A versão aprovada das regras da casa está inválida.');
        foreach(self::REQUIRED_RULES as $code){
            if(trim((string)($rules[$code]??''))==='')throw new RuntimeException('A versão aprovada das regras está incompleta: '.$code.'.');
        }
        $version['rules']=$rules;
        return $version;
    }

    public function save(int $reservationId,array $input):array
    {
        $guests=is_array($input['guests']??null)?array_values(array_filter($input['guests'],static fn($row):bool=>is_array($row)&&trim((string)($row['full_name']??''))!=='')):[];
        $vehicles=is_array($input['vehicles']??null)?array_values(array_filter($input['vehicles'],static fn($row):bool=>is_array($row)&&trim((string)($row['plate']??''))!=='')):[];
        $pets=is_array($input['pets']??null)?array_values(array_filter($input['pets'],static fn($row):bool=>is_array($row)&&trim((string)($row['name']??''))!=='')):[];
        $settings=(new PropertySettingsService($this->db))->values();
        $maxGuests=max(1,min(10,(int)($settings['MAX_GUESTS']??10)));
        if(count($guests)<1||count($guests)>$maxGuests)throw new RuntimeException('Informe de 1 a '.$maxGuests.' hóspedes. O limite configurado não pode ser excedido.');
        if($pets!==[]&&empty($settings['PETS_ALLOWED']))throw new RuntimeException('Pets não estão permitidos na configuração da propriedade.');
        if(isset($settings['MAX_PETS'])&&$settings['MAX_PETS']!==null&&count($pets)>(int)$settings['MAX_PETS'])throw new RuntimeException('Quantidade de pets acima do limite configurado.');
        $responsibleName=trim((string)($input['responsible_name']??''));$responsibleCpf=preg_replace('/\D/','',(string)($input['responsible_cpf']??''));
        if($responsibleName===''||strlen((string)$responsibleCpf)!==11)throw new RuntimeException('Informe o responsável e um CPF com 11 dígitos.');
        $this->db->beginTransaction();
        try{
            $this->ensure($reservationId);
            $stmt=$this->db->prepare('SELECT * FROM precheckins WHERE reservation_id=? FOR UPDATE');$stmt->execute([$reservationId]);$record=$stmt->fetch()?:throw new RuntimeException('Pré-check-in não encontrado.');
            if(!in_array($record['status'],['NOT_STARTED','IN_PROGRESS','CORRECTION_REQUESTED'],true))throw new RuntimeException('Este pré-check-in não pode ser alterado no estado atual.');
            $id=(int)$record['id'];
            $this->db->prepare("UPDATE precheckins SET status='IN_PROGRESS',responsible_name=?,responsible_cpf=?,responsible_birth_date=?,responsible_document=?,estimated_arrival_time=?,notes=? WHERE id=?")
                ->execute([$responsibleName,$responsibleCpf,($input['responsible_birth_date']??'')?:null,mb_substr(trim((string)($input['responsible_document']??'')),0,80),($input['estimated_arrival_time']??'')?:null,mb_substr(strip_tags((string)($input['notes']??'')),0,4000),$id]);
            foreach(['reservation_guests','reservation_vehicles','reservation_pets'] as $table)$this->db->prepare("DELETE FROM {$table} WHERE precheckin_id=?")->execute([$id]);
            $insertGuest=$this->db->prepare('INSERT INTO reservation_guests (precheckin_id,full_name,cpf,document_number,birth_date,is_responsible,sort_order) VALUES (?,?,?,?,?,?,?)');
            foreach($guests as $index=>$guest){$name=trim((string)($guest['full_name']??''));if($name==='')throw new RuntimeException('Todos os hóspedes precisam de nome completo.');$insertGuest->execute([$id,$name,$this->nullableDigits($guest['cpf']??null),mb_substr(trim((string)($guest['document_number']??'')),0,80)?:null,($guest['birth_date']??'')?:null,!empty($guest['is_responsible'])?1:0,$index+1]);}
            $insertVehicle=$this->db->prepare('INSERT INTO reservation_vehicles (precheckin_id,plate,make_model,color,driver_name) VALUES (?,?,?,?,?)');
            foreach($vehicles as $vehicle){$plate=strtoupper(preg_replace('/[^A-Z0-9]/i','',(string)($vehicle['plate']??'')));if(!preg_match('/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/',$plate))throw new RuntimeException('Placa de veículo inválida.');$insertVehicle->execute([$id,$plate,mb_substr(trim((string)($vehicle['make_model']??'')),0,120),mb_substr(trim((string)($vehicle['color']??'')),0,40),mb_substr(trim((string)($vehicle['driver_name']??'')),0,160)]);}
            $insertPet=$this->db->prepare('INSERT INTO reservation_pets (precheckin_id,name,species,breed,size,notes) VALUES (?,?,?,?,?,?)');
            foreach($pets as $pet){$name=trim((string)($pet['name']??''));$species=trim((string)($pet['species']??''));if($name===''||$species==='')throw new RuntimeException('Informe nome e espécie de cada pet.');$size=in_array($pet['size']??null,['SMALL','MEDIUM','LARGE'],true)?$pet['size']:null;$insertPet->execute([$id,$name,$species,mb_substr(trim((string)($pet['breed']??'')),0,80),$size,mb_substr(trim((string)($pet['notes']??'')),0,255)]);}
            $this->db->commit();return $this->load($reservationId);
        }catch(Throwable $error){if($this->db->inTransaction())$this->db->rollBack();throw $error;}
    }

    public function submit(int $reservationId,array $accepted,string $name,?string $cpf,?string $ip,?string $userAgent):void
    {
        $this->db->beginTransaction();try{
            $stmt=$this->db->prepare('SELECT * FROM precheckins WHERE reservation_id=? FOR UPDATE');$stmt->execute([$reservationId]);$record=$stmt->fetch()?:throw new RuntimeException('Pré-check-in não encontrado.');
            if(!in_array($record['status'],['IN_PROGRESS','CORRECTION_REQUESTED'],true))throw new RuntimeException('Este pré-check-in não está disponível para envio.');
            $guestCount=$this->db->prepare('SELECT COUNT(*) FROM reservation_guests WHERE precheckin_id=?');$guestCount->execute([$record['id']]);
            $settings=(new PropertySettingsService($this->db))->values();$maxGuests=max(1,min(10,(int)($settings['MAX_GUESTS']??10)));
            $count=(int)$guestCount->fetchColumn();if($count<1||$count>$maxGuests)throw new RuntimeException('A lista deve conter entre 1 e '.$maxGuests.' hóspedes.');
            $acceptedCodes=array_keys(array_filter($accepted));$missing=array_values(array_diff(self::REQUIRED_RULES,$acceptedCodes));if($missing!==[])throw new RuntimeException('Aceites obrigatórios ausentes: '.implode(', ',$missing).'.');
            $version=$this->approvedHouseRules()?:throw new RuntimeException('As regras da casa ainda não foram aprovadas.');
            $acceptedItems=[];foreach(self::REQUIRED_RULES as $code)$acceptedItems[$code]=(string)$version['rules'][$code];
            $snapshot=json_encode($acceptedItems,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$hash=hash('sha256',(string)$version['content_hash'].'|'.$snapshot);
            $this->db->prepare('INSERT INTO house_rule_acceptances (precheckin_id,rule_version_id,accepted_items_json,acceptance_text_hash,accepted_name,accepted_cpf,ip,user_agent) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$record['id'],$version['id'],$snapshot,$hash,trim($name),$this->nullableDigits($cpf),$ip,mb_substr((string)$userAgent,0,500)]);
            $update=$this->db->prepare("UPDATE precheckins SET status='SUBMITTED',submitted_at=NOW(),reviewed_at=NULL,reviewed_by=NULL,correction_message=NULL WHERE id=? AND status IN ('IN_PROGRESS','CORRECTION_REQUESTED')");$update->execute([$record['id']]);
            if($update->rowCount()!==1)throw new RuntimeException('O estado do pré-check-in mudou durante o envio.');
            $this->db->commit();
        }catch(Throwable $error){if($this->db->inTransaction())$this->db->rollBack();throw $error;}
    }

    public function review(int $reservationId,string $decision,string $message,int $userId):void
    {
        $status=match($decision){'approve'=>'APPROVED','correction'=>'CORRECTION_REQUESTED','reject'=>'REJECTED',default=>throw new RuntimeException('Decisão inválida.')};
        if($status!=='APPROVED'&&trim($message)==='')throw new RuntimeException('Informe o motivo da decisão.');
        $stmt=$this->db->prepare('UPDATE precheckins SET status=?,reviewed_at=NOW(),reviewed_by=?,correction_message=? WHERE reservation_id=? AND status IN (\'SUBMITTED\',\'UNDER_REVIEW\',\'CORRECTION_REQUESTED\')');
        $stmt->execute([$status,$userId,$message?:null,$reservationId]);if($stmt->rowCount()!==1)throw new RuntimeException('O pré-check-in não está pronto para revisão.');
    }

    public function bootstrapHouseRules(?int $userId=null):int
    {
        $rules=['capacity'=>'Máximo absoluto de 10 pessoas, incluindo crianças.','visitors'=>'Visitantes somente com autorização prévia.','hours'=>'Respeitar check-in e check-out configurados.','quiet'=>'Respeitar o período de silêncio e manter som moderado.','events'=>'Eventos não autorizados são proibidos.','pool'=>'Sem vidro; crianças acompanhadas; não manipular equipamentos.','barbecue'=>'Fogo apenas nos locais permitidos e totalmente apagado após o uso.','smoking'=>'Fumo e similares proibidos em áreas internas.','pets'=>'Cumprir a política de animais configurada.','furniture'=>'Não mover, desmontar ou levar itens internos para fora.','waste'=>'Acondicionar lixo e não descartar objetos ou óleo na rede.','gates'=>'Manter portões fechados e não copiar chaves ou alterar senhas.','emergency'=>'Seguir contatos e orientações de emergência.'];
        $json=json_encode($rules,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$hash=hash('sha256',$json);
        $stmt=$this->db->prepare("INSERT INTO house_rule_versions (version_no,status,title,rules_json,content_hash,approved_by,approved_at) VALUES (1,'DRAFT','Regras essenciais da casa',?,?,?,NULL) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");$stmt->execute([$json,$hash,$userId]);return (int)$this->db->lastInsertId();
    }

    private function nullableDigits(mixed $value):?string{$digits=preg_replace('/\D/','',(string)$value);return $digits!==''?$digits:null;}
}

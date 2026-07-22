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
        return $record;
    }

    public function save(int $reservationId,array $input):array
    {
        $guests=is_array($input['guests']??null)?array_values(array_filter($input['guests'],static fn($row):bool=>is_array($row)&&trim((string)($row['full_name']??''))!=='')):[];
        $vehicles=is_array($input['vehicles']??null)?array_values(array_filter($input['vehicles'],static fn($row):bool=>is_array($row)&&trim((string)($row['plate']??''))!=='')):[];
        $pets=is_array($input['pets']??null)?array_values(array_filter($input['pets'],static fn($row):bool=>is_array($row)&&trim((string)($row['name']??''))!=='')):[];
        if(count($guests)<1||count($guests)>10)throw new RuntimeException('Informe de 1 a 10 hóspedes. O limite de 10 não pode ser excedido.');
        $settings=(new PropertySettingsService($this->db))->values();
        if($pets!==[]&&empty($settings['PETS_ALLOWED']))throw new RuntimeException('Pets não estão permitidos na configuração da propriedade.');
        if(isset($settings['MAX_PETS'])&&$settings['MAX_PETS']!==null&&count($pets)>(int)$settings['MAX_PETS'])throw new RuntimeException('Quantidade de pets acima do limite configurado.');
        $responsibleName=trim((string)($input['responsible_name']??''));$responsibleCpf=preg_replace('/\D/','',(string)($input['responsible_cpf']??''));
        if($responsibleName===''||strlen((string)$responsibleCpf)!==11)throw new RuntimeException('Informe o responsável e um CPF com 11 dígitos.');
        $this->db->beginTransaction();
        try{
            $record=$this->ensure($reservationId);$id=(int)$record['id'];
            $this->db->prepare("UPDATE precheckins SET status='IN_PROGRESS',responsible_name=?,responsible_cpf=?,responsible_birth_date=?,responsible_document=?,estimated_arrival_time=?,notes=? WHERE id=?")
                ->execute([$responsibleName,$responsibleCpf,$input['responsible_birth_date']?:null,mb_substr(trim((string)($input['responsible_document']??'')),0,80),$input['estimated_arrival_time']?:null,mb_substr(strip_tags((string)($input['notes']??'')),0,4000),$id]);
            foreach(['reservation_guests','reservation_vehicles','reservation_pets'] as $table)$this->db->prepare("DELETE FROM {$table} WHERE precheckin_id=?")->execute([$id]);
            $insertGuest=$this->db->prepare('INSERT INTO reservation_guests (precheckin_id,full_name,cpf,document_number,birth_date,is_responsible,sort_order) VALUES (?,?,?,?,?,?,?)');
            foreach($guests as $index=>$guest){$name=trim((string)($guest['full_name']??''));if($name==='')throw new RuntimeException('Todos os hóspedes precisam de nome completo.');$insertGuest->execute([$id,$name,$this->nullableDigits($guest['cpf']??null),mb_substr(trim((string)($guest['document_number']??'')),0,80)?:null,$guest['birth_date']?:null,!empty($guest['is_responsible'])?1:0,$index+1]);}
            $insertVehicle=$this->db->prepare('INSERT INTO reservation_vehicles (precheckin_id,plate,make_model,color,driver_name) VALUES (?,?,?,?,?)');
            foreach($vehicles as $vehicle){$plate=strtoupper(preg_replace('/[^A-Z0-9]/i','',(string)($vehicle['plate']??'')));if(!preg_match('/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/',$plate))throw new RuntimeException('Placa de veículo inválida.');$insertVehicle->execute([$id,$plate,mb_substr(trim((string)($vehicle['make_model']??'')),0,120),mb_substr(trim((string)($vehicle['color']??'')),0,40),mb_substr(trim((string)($vehicle['driver_name']??'')),0,160)]);}
            $insertPet=$this->db->prepare('INSERT INTO reservation_pets (precheckin_id,name,species,breed,size,notes) VALUES (?,?,?,?,?,?)');
            foreach($pets as $pet){$name=trim((string)($pet['name']??''));$species=trim((string)($pet['species']??''));if($name===''||$species==='')throw new RuntimeException('Informe nome e espécie de cada pet.');$size=in_array($pet['size']??null,['SMALL','MEDIUM','LARGE'],true)?$pet['size']:null;$insertPet->execute([$id,$name,$species,mb_substr(trim((string)($pet['breed']??'')),0,80),$size,mb_substr(trim((string)($pet['notes']??'')),0,255)]);}
            $this->db->commit();return $this->load($reservationId);
        }catch(Throwable $error){if($this->db->inTransaction())$this->db->rollBack();throw $error;}
    }

    public function submit(int $reservationId,array $accepted,string $name,?string $cpf,?string $ip,?string $userAgent):void
    {
        $record=$this->load($reservationId);if(count($record['guests'])<1||count($record['guests'])>10)throw new RuntimeException('A lista deve conter entre 1 e 10 hóspedes.');
        $missing=array_values(array_diff(self::REQUIRED_RULES,array_keys(array_filter($accepted))));if($missing!==[])throw new RuntimeException('Aceites obrigatórios ausentes: '.implode(', ',$missing).'.');
        $version=$this->db->query("SELECT * FROM house_rule_versions WHERE status='APPROVED' ORDER BY version_no DESC LIMIT 1")->fetch()?:throw new RuntimeException('As regras da casa ainda não foram aprovadas.');
        $snapshot=json_encode($accepted,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$hash=hash('sha256',(string)$version['content_hash'].'|'.$snapshot);
        $this->db->beginTransaction();try{
            $this->db->prepare('INSERT INTO house_rule_acceptances (precheckin_id,rule_version_id,accepted_items_json,acceptance_text_hash,accepted_name,accepted_cpf,ip,user_agent) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE accepted_items_json=VALUES(accepted_items_json),acceptance_text_hash=VALUES(acceptance_text_hash),accepted_name=VALUES(accepted_name),accepted_cpf=VALUES(accepted_cpf),ip=VALUES(ip),user_agent=VALUES(user_agent),accepted_at=NOW()')
                ->execute([$record['id'],$version['id'],$snapshot,$hash,trim($name),$this->nullableDigits($cpf),$ip,mb_substr((string)$userAgent,0,500)]);
            $this->db->prepare("UPDATE precheckins SET status='SUBMITTED',submitted_at=NOW(),correction_message=NULL WHERE id=?")->execute([$record['id']]);$this->db->commit();
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

<?php
declare(strict_types=1);

namespace Refugio\Services;

use PDO;
use RuntimeException;
use Throwable;

final class ContractTemplateService
{
    public const TEMPLATE_CODE = 'TEMPORARY_LEISURE_RENTAL';

    public const REQUIRED_VARIABLES = [
        'owner_full_name','owner_nationality','owner_marital_status','owner_profession','owner_cpf','owner_address','owner_phone','owner_email',
        'guest_full_name','guest_nationality','guest_marital_status','guest_profession','guest_cpf','guest_address','guest_phone','guest_email',
        'property_name','property_full_address','checkin_at','checkout_at','number_of_nights','total_amount','rental_amount','cleaning_fee','extra_guest_amount',
        'deposit_amount','deposit_due_at','balance_amount','balance_due_at','payment_method','unauthorized_visitor_fee','cancellation_policy','quiet_hours','pets_policy',
        'contract_forum_city','contract_city','contract_date_long','checkin_time','checkout_time','emergency_contact','contract_number','contract_version','document_hash',
    ];

    private const RAW_VARIABLES = ['inventory_rows','guest_rows','vehicle_rows'];

    public function __construct(private PDO $db)
    {
    }

    public function bootstrapBundledTemplates(?int $userId = null, ?string $sourcePdfPath = null): array
    {
        $archivedPath = BASE_PATH . '/resources/contracts/source-v1-archived.html';
        $suggestedPath = BASE_PATH . '/resources/contracts/contract-v2-suggested.html';
        $archived = file_get_contents($archivedPath);
        $suggested = file_get_contents($suggestedPath);
        if ($archived === false || $suggested === false) throw new RuntimeException('Templates contratuais empacotados não foram encontrados.');
        $metadata=json_decode((string)file_get_contents(BASE_PATH.'/resources/contracts/source-metadata.json'),true);
        $expectedHash=is_array($metadata)?(string)($metadata['sha256']??''):'';
        $sourceHash = $sourcePdfPath && is_file($sourcePdfPath) ? hash_file('sha256', $sourcePdfPath) : ($expectedHash?:null);
        if($expectedHash!==''&&$sourceHash!==null&&!hash_equals($expectedHash,strtolower($sourceHash)))throw new RuntimeException('O PDF-fonte não corresponde ao hash auditado.');
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('INSERT INTO contract_templates (code,name,description,created_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
            $stmt->execute([self::TEMPLATE_CODE,'Locação temporária para lazer','Versão arquivada do PDF e proposta dinâmica separada.',$userId]);
            $templateId = (int) $this->db->lastInsertId();
            if ($templateId === 0) {
                $stmt = $this->db->prepare('SELECT id FROM contract_templates WHERE code=?'); $stmt->execute([self::TEMPLATE_CODE]); $templateId=(int)$stmt->fetchColumn();
            }
            $variables = json_encode(['required'=>self::REQUIRED_VARIABLES,'raw'=>self::RAW_VARIABLES],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            $insert=$this->db->prepare('INSERT IGNORE INTO contract_template_versions (template_id,version_no,status,source_kind,title,body_html,variables_json,change_summary,legal_review_notes,source_document_hash,content_hash,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            $insert->execute([$templateId,1,'ARCHIVED','SOURCE_ARCHIVE','Contrato recebido — arquivo imutável',$archived,$variables,'Extração textual integral das 12 páginas do PDF recebido, preservada para auditoria.','Não usar para assinatura: inclui a página de instruções, placeholders e 12 linhas de hóspedes.',$sourceHash,hash('sha256',$archived),$userId]);
            $insert->execute([$templateId,2,'PENDING_APPROVAL','EDITABLE_HTML','Contrato dinâmico — proposta v2',$suggested,$variables,'Remove página editorial; limita a 10 hóspedes; substitui placeholders por variáveis; explicita assinatura local auditável.','Revisar cancelamento, multa de 20%, foro, caução, visitantes, pets, silêncio e regras municipais antes de aprovar.',$sourceHash,hash('sha256',$suggested),$userId]);
            $insert->execute([$templateId,3,'PENDING_APPROVAL','EDITABLE_HTML','Contrato dinâmico — CPF como documento',$suggested,$variables,'Substitui a identificação por RG pelo CPF das partes.','Revisar e aprovar esta versão antes de gerar novos contratos.',$sourceHash,hash('sha256',$suggested),$userId]);
            $this->db->commit();
            return ['template_id'=>$templateId,'source_hash'=>$sourceHash];
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    public function approveVersion(int $versionId, int $userId): void
    {
        $this->db->beginTransaction();
        try {
            $stmt=$this->db->prepare('SELECT * FROM contract_template_versions WHERE id=? FOR UPDATE'); $stmt->execute([$versionId]);
            $version=$stmt->fetch() ?: throw new RuntimeException('Versão contratual não encontrada.');
            if ($version['source_kind'] !== 'EDITABLE_HTML') throw new RuntimeException('A versão de arquivo não pode ser publicada.');
            if ($version['status'] === 'APPROVED') { $this->db->commit(); return; }
            $this->db->prepare("UPDATE contract_template_versions SET status='SUPERSEDED' WHERE template_id=? AND status='APPROVED'")->execute([$version['template_id']]);
            $this->db->prepare("UPDATE contract_template_versions SET status='APPROVED',approved_by=?,approved_at=NOW() WHERE id=?")->execute([$userId,$versionId]);
            $this->db->prepare('UPDATE contract_templates SET active_version_id=? WHERE id=?')->execute([$versionId,$version['template_id']]);
            $this->db->commit();
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    public function activeVersion(): array
    {
        $stmt=$this->db->prepare('SELECT v.* FROM contract_templates t JOIN contract_template_versions v ON v.id=t.active_version_id WHERE t.code=? AND v.status=\'APPROVED\'');
        $stmt->execute([self::TEMPLATE_CODE]);
        return $stmt->fetch() ?: throw new RuntimeException('Nenhuma versão contratual foi aprovada pelo administrador.');
    }

    public function missingVariables(array $variables): array
    {
        return array_values(array_filter(self::REQUIRED_VARIABLES, static fn(string $name): bool => !array_key_exists($name,$variables) || $variables[$name] === null || trim((string)$variables[$name]) === ''));
    }

    public function render(string $template, array $variables): string
    {
        $missing=$this->missingVariables($variables);
        if ($missing !== []) throw new RuntimeException('Campos obrigatórios ausentes no contrato: '.implode(', ',$missing).'.');
        $replacements=[];
        foreach($variables as $key=>$value){
            $replacements['{{'.$key.'}}']=in_array($key,self::RAW_VARIABLES,true) ? (string)$value : htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        }
        $rendered=strtr($template,$replacements);
        if (preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',$rendered,$matches)) {
            throw new RuntimeException('Variáveis não resolvidas no contrato: '.implode(', ',array_unique($matches[1])).'.');
        }
        if (preg_match('/«[^»]+»/u',$rendered)) throw new RuntimeException('A versão contém placeholders editoriais e não pode ser assinada.');
        return $rendered;
    }

    public function createReservationContract(int $reservationId, array $variables, ?int $userId): array
    {
        $version=$this->activeVersion();
        $this->db->beginTransaction();
        try {
            $stmt=$this->db->prepare('SELECT COALESCE(MAX(version_no),0)+1 FROM reservation_contracts WHERE reservation_id=? FOR UPDATE'); $stmt->execute([$reservationId]); $reservationVersion=(int)$stmt->fetchColumn();
            $contractNumber=(string)($variables['contract_number'] ?? 'CTR-'.$reservationId.'-'.$reservationVersion);
            $variables['contract_number']=$contractNumber;
            $variables['contract_version']=(string)$reservationVersion;
            $variables['document_hash']=hash('sha256',(string)$version['content_hash'].'|'.json_encode($variables,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
            $html=$this->render((string)$version['body_html'],$variables);
            $contentHash=hash('sha256',$html);
            $stmt=$this->db->prepare("INSERT INTO reservation_contracts (reservation_id,template_version_id,version_no,status,contract_number,variables_snapshot_json,html_snapshot,content_hash,ready_at,generated_by) VALUES (?,?,?,'READY',?,?,?,?,NOW(),?)");
            $stmt->execute([$reservationId,$version['id'],$reservationVersion,$contractNumber,json_encode($variables,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$html,$contentHash,$userId]);
            $contractId=(int)$this->db->lastInsertId();
            $signer=$this->db->prepare('INSERT INTO contract_signers (contract_id,signer_role,name,cpf,email,phone) VALUES (?,?,?,?,?,?)');
            $signer->execute([$contractId,'GUEST',$variables['guest_full_name'],$variables['guest_cpf'],$variables['guest_email'],$variables['guest_phone']]);
            $signer->execute([$contractId,'OWNER',$variables['owner_full_name'],$variables['owner_cpf'],$variables['owner_email'],$variables['owner_phone']]);
            $this->db->prepare("INSERT INTO contract_events (contract_id,event_type,metadata_json,document_hash) VALUES (?,'CONTRACT_READY',?,?)")->execute([$contractId,json_encode(['template_version_id'=>(int)$version['id']],JSON_THROW_ON_ERROR),$contentHash]);
            $this->db->commit();
            return ['id'=>$contractId,'contract_number'=>$contractNumber,'html'=>$html,'content_hash'=>$contentHash,'variables'=>$variables];
        } catch(Throwable $error){
            if($this->db->inTransaction())$this->db->rollBack();
            throw $error;
        }
    }

    public static function guestRows(array $guests): string
    {
        if(count($guests)>10) throw new RuntimeException('O contrato aceita no máximo 10 hóspedes.');
        $rows='';
        for($index=0;$index<10;$index++){
            $guest=$guests[$index]??[];
            $values=[$index+1,$guest['full_name']??'', $guest['cpf']??$guest['document_number']??'', $guest['birth_date']??'', $guest['phone']??''];
            $rows.='<tr>'.implode('',array_map(static fn($value):string=>'<td>'.htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</td>',$values)).'</tr>';
        }
        return $rows;
    }

    public static function genericRows(array $rows, array $columns, string $emptyLabel='Sem registros'): string
    {
        if($rows===[])return '<tr><td colspan="'.count($columns).'">'.htmlspecialchars($emptyLabel,ENT_QUOTES,'UTF-8').'</td></tr>';
        $html=''; foreach($rows as $row){$html.='<tr>';foreach($columns as $column)$html.='<td>'.htmlspecialchars((string)($row[$column]??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</td>';$html.='</tr>';}
        return $html;
    }
}

<?php
declare(strict_types=1);

namespace Refugio\Controllers;

use DateTimeImmutable;
use PDO;
use Refugio\Config\Database;
use Refugio\Services\AuthorizationService;
use Refugio\Services\ContractPdfService;
use Refugio\Services\ContractRevisionService;
use Refugio\Services\ContractSignatureWorkflowService;
use Refugio\Services\ContractTemplateService;
use Refugio\Services\GuestPortalService;
use Refugio\Services\ICalendarService;
use Refugio\Services\JobQueueService;
use Refugio\Services\PreCheckinService;
use Refugio\Services\PropertySettingsService;
use Refugio\Services\QuoteService;
use Refugio\Services\UploadService;
use Refugio\Models\ReservationStatus;
use Refugio\Support\Csrf;
use Refugio\Support\Env;
use Refugio\Support\Security;
use RuntimeException;
use Throwable;

final class OperationsController
{
    private PDO $db;
    public function __construct(private array $config){$this->db=Database::connection();}

    public function pricing():void
    {
        AuthorizationService::requirePermission('pricing.view');$settings=$this->db->query('SELECT * FROM property_pricing_settings WHERE id=1')->fetch();$readiness=(new PropertySettingsService($this->db))->publicPricingReadiness();$seasons=$this->db->query('SELECT * FROM pricing_seasons ORDER BY starts_on DESC')->fetchAll();$rules=$this->db->query('SELECT * FROM pricing_rules ORDER BY priority,id')->fetchAll();$specialDates=$this->db->query('SELECT * FROM pricing_special_dates ORDER BY starts_on DESC')->fetchAll();require BASE_PATH.'/app/Views/admin/pricing.php';
    }
    public function quotes():void{AuthorizationService::requirePermission('quotes.view');$quotes=$this->db->query('SELECT * FROM quotes ORDER BY created_at DESC LIMIT 100')->fetchAll();require BASE_PATH.'/app/Views/admin/quotes.php';}
    public function contracts():void
    {
        AuthorizationService::requirePermission('contracts.view');
        $versions=$this->db->query('SELECT v.*,t.name template_name FROM contract_template_versions v JOIN contract_templates t ON t.id=v.template_id ORDER BY v.version_no DESC')->fetchAll();
        $approvedReservations=$this->db->query("SELECT r.id,r.codigo,r.nome_cliente,r.checkin,r.checkout,r.status,(SELECT t.token_prefix FROM guest_portal_tokens t WHERE t.reservation_id=r.id AND t.status='ACTIVE' AND t.revoked_at IS NULL AND (t.expires_at IS NULL OR t.expires_at>NOW()) ORDER BY t.id DESC LIMIT 1) portal_token_prefix FROM reservas r WHERE r.status IN ('AGUARDANDO_APROVACAO','AGUARDANDO_PAGAMENTO','COMPROVANTE_ENVIADO','PAGAMENTO_CONFIRMADO','RESERVA_CONFIRMADA','FINALIZADA') ORDER BY r.checkin DESC,r.id DESC")->fetchAll();
        $contractReservations=$this->db->query("SELECT r.id,r.codigo,r.nome_cliente,r.checkin,r.checkout,r.status FROM reservas r JOIN precheckins p ON p.reservation_id=r.id WHERE r.status IN ('PAGAMENTO_CONFIRMADO','RESERVA_CONFIRMADA','FINALIZADA') AND p.status IN ('SUBMITTED','UNDER_REVIEW','APPROVED') AND EXISTS (SELECT 1 FROM pagamentos pay WHERE pay.reserva_id=r.id AND pay.status='CONFIRMADO') ORDER BY r.checkin DESC,r.id DESC")->fetchAll();
        $contracts=$this->db->query('SELECT c.*,r.codigo,nome_cliente FROM reservation_contracts c JOIN reservas r ON r.id=c.reservation_id ORDER BY c.created_at DESC LIMIT 100')->fetchAll();
        $signatureDocuments=[];
        $documents=$this->db->query('SELECT d.* FROM contract_signature_documents d JOIN (SELECT contract_id,stage,MAX(revision_no) revision_no FROM contract_signature_documents GROUP BY contract_id,stage) latest ON latest.contract_id=d.contract_id AND latest.stage=d.stage AND latest.revision_no=d.revision_no')->fetchAll();
        foreach($documents as $document)$signatureDocuments[(int)$document['contract_id']][$document['stage']]=$document;
        require BASE_PATH.'/app/Views/admin/contracts.php';
    }
    public function contractDocument(int $contractId):never
    {
        AuthorizationService::requirePermission('contracts.view');
        $stmt=$this->db->prepare('SELECT contract_number,pdf_path FROM reservation_contracts WHERE id=?');
        $stmt->execute([$contractId]);
        $contract=$stmt->fetch()?:throw new RuntimeException('Contrato não encontrado.');
        if(empty($contract['pdf_path']))throw new RuntimeException('PDF ainda não foi gerado.');

        $path=realpath(BASE_PATH.'/'.ltrim((string)$contract['pdf_path'],'/\\'));
        $storage=realpath(BASE_PATH.'/storage/contracts');
        $normalizedPath=$path===false?'':str_replace('\\','/',$path);
        $normalizedStorage=$storage===false?'':rtrim(str_replace('\\','/',$storage),'/');
        if($normalizedPath===''||$normalizedStorage===''||!str_starts_with($normalizedPath,$normalizedStorage.'/')||!is_file($path)){
            throw new RuntimeException('Arquivo PDF não encontrado no armazenamento protegido.');
        }

        $filename='contrato-'.preg_replace('/[^A-Za-z0-9_-]/','-',(string)$contract['contract_number']).'.pdf';
        header('Content-Type: application/pdf');
        header('Content-Length: '.filesize($path));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline; filename="'.$filename.'"');
        readfile($path);
        exit;
    }
    public function contractSignatureDocument(int $contractId,string $kind):never
    {
        AuthorizationService::requirePermission('contracts.view');
        $stage=match($kind){'hospede'=>ContractSignatureWorkflowService::GUEST_SIGNED,'final'=>ContractSignatureWorkflowService::FULLY_SIGNED,default=>throw new RuntimeException('Documento de assinatura invalido.')};
        $stmt=$this->db->prepare('SELECT contract_number FROM reservation_contracts WHERE id=?');
        $stmt->execute([$contractId]);
        $contract=$stmt->fetch()?:throw new RuntimeException('Contrato nao encontrado.');
        $workflow=new ContractSignatureWorkflowService($this->db,new UploadService((int)$this->config['upload_max_bytes']));
        $document=$workflow->latestDocument($contractId,$stage)?:throw new RuntimeException('Documento assinado ainda nao disponivel.');
        $path=$workflow->resolvePath($document);
        $filename='contrato-'.preg_replace('/[^A-Za-z0-9_-]/','-',(string)$contract['contract_number']).'-'.($stage===ContractSignatureWorkflowService::FULLY_SIGNED?'final':'hospede').'.pdf';
        header('Content-Type: application/pdf');
        header('Content-Length: '.filesize($path));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        readfile($path);
        exit;
    }
    public function precheckins():void{AuthorizationService::requirePermission('precheckin.view');$precheckins=$this->db->query('SELECT p.*,r.codigo,r.nome_cliente,r.checkin,r.checkout,(SELECT COUNT(*) FROM reservation_guests g WHERE g.precheckin_id=p.id) guest_count FROM precheckins p JOIN reservas r ON r.id=p.reservation_id ORDER BY r.checkin DESC LIMIT 100')->fetchAll();$ruleVersions=$this->db->query('SELECT * FROM house_rule_versions ORDER BY version_no DESC')->fetchAll();require BASE_PATH.'/app/Views/admin/precheckins.php';}
    public function automations():void{AuthorizationService::requirePermission('automation.view');$rules=$this->db->query('SELECT * FROM automation_rules ORDER BY id')->fetchAll();$runs=$this->db->query('SELECT ar.*,au.name rule_name,r.codigo FROM automation_runs ar JOIN automation_rules au ON au.id=ar.rule_id JOIN reservas r ON r.id=ar.reservation_id ORDER BY ar.created_at DESC LIMIT 100')->fetchAll();require BASE_PATH.'/app/Views/admin/automations.php';}
    public function propertySettings():void{AuthorizationService::requirePermission('property_settings.manage');$settings=(new PropertySettingsService($this->db))->all();$contractMissing=(new PropertySettingsService($this->db))->missing(PropertySettingsService::REQUIRED_FOR_CONTRACT);$pricingReadiness=(new PropertySettingsService($this->db))->publicPricingReadiness();require BASE_PATH.'/app/Views/admin/property-settings.php';}

    public function action(string $action):never
    {
        try{Csrf::verify($_POST['_csrf']??null);$userId=(int)$_SESSION['admin_id'];$redirect='admin';
            match($action){
                'property-update'=>$this->propertyUpdate($userId),
                'pricing-update'=>$this->pricingUpdate($userId),
                'quote-create'=>$this->quoteCreate($userId),
                'calendar-source-create'=>$this->calendarSourceCreate($userId),
                'calendar-source-sync'=>$this->calendarSourceSync(),
                'calendar-export-create'=>$this->calendarExportCreate($userId),
                'calendar-export-revoke'=>$this->calendarExportRevoke(),
                'contract-bootstrap'=>$this->contractBootstrap($userId),
                'contract-approve'=>$this->contractApprove($userId),
                'contract-generate'=>$this->contractGenerate($userId),
                'contract-pdf'=>$this->contractPdf(),
                'contract-revise'=>$this->contractRevise($userId),
                'contract-owner-upload'=>$this->contractOwnerUpload($userId),
                'portal-regenerate'=>$this->portalRegenerate($userId),
                'precheckin-review'=>$this->precheckinReview($userId),
                'rules-bootstrap'=>$this->rulesBootstrap($userId),
                'rules-approve'=>$this->rulesApprove($userId),
                'automation-toggle'=>$this->automationToggle(),
                default=>throw new RuntimeException('Ação operacional inválida.'),
            };
            $redirect=match(true){str_starts_with($action,'property-')=>'admin/configuracoes/propriedade',str_starts_with($action,'pricing-')=>'admin/precos',str_starts_with($action,'quote-')=>'admin/orcamentos',str_starts_with($action,'calendar-')=>'admin/calendario',str_starts_with($action,'contract-')||str_starts_with($action,'portal-')=>'admin/contratos',str_starts_with($action,'precheckin-')||str_starts_with($action,'rules-')=>'admin/pre-checkins',str_starts_with($action,'automation-')=>'admin/automacoes',default=>'admin'};
            $returnTo=(string)($_POST['return_to']??'');
            if($action==='portal-regenerate'&&preg_match('#^admin/reservas/[1-9][0-9]*$#',$returnTo))$redirect=$returnTo;
            if(!isset($_SESSION['_flash']['success']))flash('success','Operação concluída.');
        }catch(Throwable $error){flash('error',$error->getMessage());$redirect=$_SERVER['HTTP_REFERER']??base_url('admin');if(str_starts_with($redirect,'http')){header('Location: '.$redirect,true,303);exit;}}
        redirect(base_url($redirect));
    }

    private function propertyUpdate(int $userId):void{AuthorizationService::requirePermission('property_settings.manage');(new PropertySettingsService($this->db))->update($_POST,$userId);}
    private function pricingUpdate(int $userId):void
    {
        AuthorizationService::requirePermission('pricing.manage');$included=$_POST['guests_included_in_base_rate']??null;$mode=$_POST['extra_guest_fee_mode']??null;
        if($included!==''&&((int)$included<1||(int)$included>10))throw new RuntimeException('Hóspedes incluídos deve ficar entre 1 e 10.');if($mode!==''&&!in_array($mode,['PER_NIGHT','PER_STAY'],true))throw new RuntimeException('Modo do adicional inválido.');
        $enabled=isset($_POST['public_pricing_enabled'])?1:0;if($enabled&&($included===''||$mode===''))throw new RuntimeException('Defina hóspedes incluídos e modo da taxa extra antes de liberar o preço público.');
        $stmt=$this->db->prepare('UPDATE property_pricing_settings SET base_daily_rate=?,cleaning_fee=?,guests_included_in_base_rate=?,extra_guest_fee=?,extra_guest_fee_mode=?,minimum_nights=?,maximum_nights=?,public_pricing_enabled=?,updated_by=? WHERE id=1');
        $stmt->execute([$this->money($_POST['base_daily_rate']??''),$this->money($_POST['cleaning_fee']??'0'),$included!==''?(int)$included:null,$this->money($_POST['extra_guest_fee']??'0'),$mode?:null,$_POST['minimum_nights']!==''?(int)$_POST['minimum_nights']:null,$_POST['maximum_nights']!==''?(int)$_POST['maximum_nights']:null,$enabled,$userId]);
    }
    private function quoteCreate(int $userId):void{AuthorizationService::requirePermission('quotes.manage');$service=new QuoteService($this->db);$calculation=$service->calculate(['checkin'=>$_POST['checkin']??'','checkout'=>$_POST['checkout']??'','guests'=>$_POST['guests']??'','pets'=>$_POST['pets']??0,'coupon'=>$_POST['coupon']??''],false);$hours=(int)((new PropertySettingsService($this->db))->get('DEFAULT_QUOTE_EXPIRATION_HOURS',24));$quote=$service->create(['name'=>$_POST['customer_name']??null,'email'=>$_POST['customer_email']??null,'phone'=>$_POST['customer_phone']??null],$calculation,$hours?:24,$userId);flash('success','Orçamento '.$quote['code'].' criado com snapshot.');}
    private function calendarSourceCreate(int $userId):void
    {
        AuthorizationService::requirePermission('calendar.manage');
        $url=trim((string)($_POST['feed_url']??''));
        if(!filter_var($url,FILTER_VALIDATE_URL))throw new RuntimeException('URL iCal inválida.');
        $provider=in_array($_POST['provider']??'', ['AIRBNB','BOOKING','GOOGLE','OTHER'],true)?$_POST['provider']:'OTHER';
        $stmt=$this->db->prepare('INSERT INTO calendar_sources (nome,provider,feed_url,feed_url_hash,timezone,sync_interval_minutes,proximo_sync_em,criado_por) VALUES (?,?,?,?,?,?,NOW(),?)');
        $stmt->execute([mb_substr(trim((string)$_POST['name']),0,120),$provider,$url,hash('sha256',$url),$_POST['timezone']?:'America/Sao_Paulo',max(5,min(1440,(int)($_POST['interval']??30))),$userId]);
        (new ICalendarService($this->db))->syncSource((int)$this->db->lastInsertId());
    }
    private function calendarSourceSync():void
    {
        AuthorizationService::requirePermission('calendar.sync');
        $id=(int)($_POST['source_id']??0);
        if($id<=0)throw new RuntimeException('Fonte iCal inválida.');
        (new ICalendarService($this->db))->syncSource($id);
    }
    private function calendarExportCreate(int $userId):void
    {
        AuthorizationService::requirePermission('calendar.manage');
        $token=bin2hex(random_bytes(32));
        $name=mb_substr(trim((string)($_POST['name']??'Exportação principal')),0,120);
        if($name==='')$name='Exportação principal';
        $this->db->prepare('INSERT INTO calendar_export_tokens (nome,token_hash,criado_por) VALUES (?,?,?)')->execute([$name,hash('sha256',$token),$userId]);
        flash('calendar_export_url',base_url('calendario/'.$token.'.ics'));
    }
    private function calendarExportRevoke():void
    {
        AuthorizationService::requirePermission('calendar.manage');
        $id=(int)($_POST['export_id']??0);
        if($id<=0)throw new RuntimeException('Link iCal inválido.');
        $stmt=$this->db->prepare('UPDATE calendar_export_tokens SET ativo=0,revoked_at=NOW() WHERE id=? AND ativo=1 AND revoked_at IS NULL');
        $stmt->execute([$id]);
        if($stmt->rowCount()!==1)throw new RuntimeException('O link iCal já foi revogado ou não existe.');
    }
    private function contractBootstrap(int $userId):void{AuthorizationService::requirePermission('contracts.templates.manage');$path=Env::get('CONTRACT_SOURCE_PDF_PATH','');(new ContractTemplateService($this->db))->bootstrapBundledTemplates($userId,$path!==''?$path:null);}
    private function contractApprove(int $userId):void{AuthorizationService::requirePermission('contracts.templates.approve');$missing=(new PropertySettingsService($this->db))->missing(PropertySettingsService::REQUIRED_FOR_CONTRACT);if($missing!==[])throw new RuntimeException('Configuração contratual incompleta: '.implode(', ',$missing).'.');if(!(bool)(new PropertySettingsService($this->db))->get('CANCELLATION_POLICY_APPROVED',false))throw new RuntimeException('A política de cancelamento ainda não foi aprovada.');(new ContractTemplateService($this->db))->approveVersion((int)$_POST['version_id'],$userId);}
    private function contractGenerate(int $userId):void
    {
        AuthorizationService::requirePermission('contracts.generate');$reservationId=(int)($_POST['reservation_id']??0);$stmt=$this->db->prepare('SELECT * FROM reservas WHERE id=?');$stmt->execute([$reservationId]);$r=$stmt->fetch()?:throw new RuntimeException('Reserva não encontrada.');if(!in_array($r['status'],[ReservationStatus::PAGAMENTO_CONFIRMADO->value,ReservationStatus::RESERVA_CONFIRMADA->value,ReservationStatus::FINALIZADA->value],true))throw new RuntimeException('O contrato só pode ser gerado após a confirmação do pagamento.');$guestCpf=preg_replace('/\D/','',(string)($_POST['guest_cpf']??''));if(strlen($guestCpf)!==11)throw new RuntimeException('Informe um CPF com 11 dígitos para o responsável.');$settings=(new PropertySettingsService($this->db))->values();
        if(empty($settings['CANCELLATION_POLICY_APPROVED']))throw new RuntimeException('A política de cancelamento precisa de aprovação antes de gerar contrato.');$preService=new PreCheckinService($this->db);$pre=$preService->ensure($reservationId);$pre=$preService->load($reservationId);if(!in_array($pre['status'],['SUBMITTED','UNDER_REVIEW','APPROVED'],true))throw new RuntimeException('O hóspede precisa enviar o pré-check-in antes da geração do contrato, para que o anexo de hóspedes seja completo.');$payments=$this->rows('SELECT * FROM pagamentos WHERE reserva_id=? ORDER BY id',[$reservationId]);$confirmed=array_sum(array_map(static fn($p)=>(float)($p['status']==='CONFIRMADO'?$p['valor']:0),$payments));if($confirmed<=0)throw new RuntimeException('Nenhum pagamento confirmado foi encontrado.');$first=$payments[0]??[];$nights=(new DateTimeImmutable($r['checkin']))->diff(new DateTimeImmutable($r['checkout']))->days;
        $vars=[
            'owner_full_name'=>$settings['OWNER_FULL_NAME']??null,'owner_nationality'=>$settings['OWNER_NATIONALITY']??null,'owner_marital_status'=>$settings['OWNER_MARITAL_STATUS']??null,'owner_profession'=>$settings['OWNER_PROFESSION']??null,'owner_rg'=>$settings['OWNER_RG']??null,'owner_cpf'=>$settings['OWNER_CPF']??null,'owner_address'=>$settings['OWNER_ADDRESS']??null,'owner_phone'=>$settings['OWNER_PHONE']??null,'owner_email'=>$settings['OWNER_EMAIL']??null,
            'guest_full_name'=>$r['nome_cliente'],'guest_nationality'=>$_POST['guest_nationality']??null,'guest_marital_status'=>$_POST['guest_marital_status']??null,'guest_profession'=>$_POST['guest_profession']??null,'guest_rg'=>null,'guest_cpf'=>$guestCpf,'guest_address'=>$_POST['guest_address']??null,'guest_phone'=>$r['telefone'],'guest_email'=>$r['email'],
            'property_name'=>$settings['PROPERTY_NAME']??null,'property_full_address'=>$settings['PROPERTY_FULL_ADDRESS']??null,'checkin_at'=>date('d/m/Y',strtotime($r['checkin'])).' '.$settings['DEFAULT_CHECKIN_TIME'],'checkout_at'=>date('d/m/Y',strtotime($r['checkout'])).' '.$settings['DEFAULT_CHECKOUT_TIME'],'number_of_nights'=>$nights,
            'total_amount'=>money($r['valor_total']),'rental_amount'=>money(max(0,(float)$r['valor_total']-(float)($settings['CLEANING_FEE']??280))),'cleaning_fee'=>money($settings['CLEANING_FEE']??280),'extra_guest_amount'=>money(0),'pet_fee_amount'=>money(0),'other_charges'=>'R$ 0,00','deposit_amount'=>money($r['valor_sinal']??0),'deposit_due_at'=>!empty($first['data_vencimento'])?date('d/m/Y H:i',strtotime($first['data_vencimento'])):'não definido','balance_amount'=>money($r['valor_restante']??0),'balance_due_at'=>$_POST['balance_due_at']??'não definido','payment_method'=>$settings['PAYMENT_METHOD']??null,
            'unauthorized_visitor_fee'=>money($settings['UNAUTHORIZED_VISITOR_FEE']??0),'security_deposit_description'=>!empty($settings['SECURITY_DEPOSIT_ENABLED'])?'Caução de '.money($settings['SECURITY_DEPOSIT_AMOUNT']??0):'não exigida','cancellation_policy'=>$settings['CANCELLATION_POLICY']??null,'quiet_hours'=>$settings['QUIET_HOURS']??null,'pets_policy'=>!empty($settings['PETS_ALLOWED'])?'Permitidos até '.(int)($settings['MAX_PETS']??0).' pet(s), mediante comunicação.':'Não permitidos.',
            'contract_forum_city'=>$settings['CONTRACT_FORUM_CITY']??null,'contract_city'=>$settings['CONTRACT_CITY']??null,'contract_date_long'=>date('d/m/Y'),'checkin_time'=>$settings['DEFAULT_CHECKIN_TIME']??null,'checkout_time'=>$settings['DEFAULT_CHECKOUT_TIME']??null,'max_guests'=>(int)($settings['MAX_GUESTS']??10),'emergency_contact'=>$settings['EMERGENCY_CONTACT']??null,
            'security_deposit_amount'=>money($settings['SECURITY_DEPOSIT_AMOUNT']??0),'security_deductions'=>'a apurar','security_balance'=>'a apurar','security_return_date'=>'até 3 dias úteis após a vistoria','inventory_rows'=>ContractTemplateService::genericRows([],['item','quantity','condition','value','notes'],'Inventário ainda sem itens cadastrados.'),'guest_rows'=>ContractTemplateService::guestRows($pre['guests'],(int)($settings['MAX_GUESTS']??10)),'vehicle_rows'=>ContractTemplateService::genericRows($pre['vehicles'],['driver_name','make_model','color','plate']),
        ];
        $contract=(new ContractTemplateService($this->db))->createReservationContract($reservationId,$vars,$userId);(new JobQueueService($this->db))->enqueue('CONTRACT_PDF',['contract_id'=>$contract['id']],'contract-pdf:'.$contract['id'],60,3);(new \Refugio\Services\ReservationAutomationService($this->db,$this->config))->emit('CONTRACT_READY',$reservationId,[],'contract:'.$contract['id']);
    }
    private function contractPdf():void
    {
        AuthorizationService::requirePermission('contracts.generate');
        $contractId=(int)($_POST['contract_id']??0);
        if($contractId<=0)throw new RuntimeException('Contrato inválido.');
        (new ContractPdfService($this->db))->generate($contractId);
        flash('success','PDF gerado com sucesso. Use “Abrir PDF” na lista de documentos.');
    }
    private function contractRevise(int $userId):void
    {
        AuthorizationService::requirePermission('contracts.generate');
        $contractId=(int)($_POST['contract_id']??0);
        if($contractId<=0)throw new RuntimeException('Contrato inválido.');
        $revision=(new ContractRevisionService($this->db))->reviseWithCurrentDate($contractId,$userId);
        flash('success','Nova revisão '.$revision['contract_number'].' gerada com data '.$revision['contract_date_long'].'. O PDF anterior foi preservado no histórico.');
    }
    private function contractOwnerUpload(int $userId):void
    {
        AuthorizationService::requirePermission('contracts.signatures.manage');
        if(!isset($_POST['owner_signed_on_gov']))throw new RuntimeException('Confirme que voce assinou o PDF no Gov.br antes de enviar.');
        $contractId=(int)($_POST['contract_id']??0);
        if($contractId<=0)throw new RuntimeException('Contrato invalido.');
        $workflow=new ContractSignatureWorkflowService($this->db,new UploadService((int)$this->config['upload_max_bytes']));
        $document=$workflow->uploadFullySigned($contractId,$_FILES['signed_contract']??[],$userId,Security::clientIp(),$_SERVER['HTTP_USER_AGENT']??'');
        flash('success','Contrato final registrado com sucesso. Revisao '.$document['revision_no'].' e SHA-256 '.substr((string)$document['sha256'],0,12).'...');
    }
    private function portalRegenerate(int $userId):void
    {
        AuthorizationService::requirePermission('guest_portal.manage');
        $reservationId=(int)($_POST['reservation_id']??0);
        if($reservationId<=0)throw new RuntimeException('Reserva inválida.');
        $stmt=$this->db->prepare("SELECT status FROM reservas WHERE id=?");$stmt->execute([$reservationId]);$status=$stmt->fetchColumn();
        if($status===false)throw new RuntimeException('Reserva não encontrada.');
        if(!in_array($status,['AGUARDANDO_APROVACAO','AGUARDANDO_PAGAMENTO','COMPROVANTE_ENVIADO','PAGAMENTO_CONFIRMADO','RESERVA_CONFIRMADA','FINALIZADA'],true))throw new RuntimeException('O portal não pode ser gerado para uma reserva encerrada sem hospedagem.');
        $token=(new GuestPortalService($this->db,$this->config))->regenerate($reservationId,$userId);
        flash('portal_url',base_url('minha-reserva/'.$token));
        flash('success','Novo link do portal gerado. Copie-o antes de sair desta página.');
    }
    private function precheckinReview(int $userId):void{AuthorizationService::requirePermission('precheckin.review');$reservationId=(int)$_POST['reservation_id'];$decision=(string)$_POST['decision'];(new PreCheckinService($this->db))->review($reservationId,$decision,(string)($_POST['message']??''),$userId);if($decision==='approve')(new \Refugio\Services\ReservationAutomationService($this->db,$this->config))->emit('PRECHECKIN_APPROVED',$reservationId,[],'precheckin-approved');}
    private function rulesBootstrap(int $userId):void{AuthorizationService::requirePermission('precheckin.rules.manage');(new PreCheckinService($this->db))->bootstrapHouseRules($userId);}
    private function rulesApprove(int $userId):void{AuthorizationService::requirePermission('precheckin.rules.manage');$this->db->beginTransaction();try{$this->db->prepare("UPDATE house_rule_versions SET status='SUPERSEDED' WHERE status='APPROVED'")->execute();$this->db->prepare("UPDATE house_rule_versions SET status='APPROVED',approved_by=?,approved_at=NOW() WHERE id=? AND status='DRAFT'")->execute([$userId,(int)$_POST['version_id']]);$this->db->commit();}catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}}
    private function automationToggle():void{AuthorizationService::requirePermission('automation.manage');$this->db->prepare('UPDATE automation_rules SET ativo=IF(ativo=1,0,1) WHERE id=?')->execute([(int)$_POST['rule_id']]);}
    private function money(mixed $value):string{$raw=str_replace(['.',','],['','.'],trim((string)$value));if(!is_numeric($raw)||(float)$raw<0)throw new RuntimeException('Valor monetário inválido.');return number_format((float)$raw,2,'.','');}
    private function rows(string $sql,array $params):array{$stmt=$this->db->prepare($sql);$stmt->execute($params);return $stmt->fetchAll();}
}

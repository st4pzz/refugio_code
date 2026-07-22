<?php
declare(strict_types=1);

namespace Refugio\Controllers;

use Refugio\Config\Database;
use Refugio\Services\GuestPortalService;
use Refugio\Services\LocalAuditSignatureProvider;
use Refugio\Services\NotificationService;
use Refugio\Services\PreCheckinService;
use Refugio\Services\RateLimiter;
use Refugio\Support\Csrf;
use Refugio\Support\Security;
use RuntimeException;
use Throwable;

final class GuestPortalController
{
    public function __construct(private array $config)
    {
    }

    public function show(string $token):void
    {
        try{$portal=(new GuestPortalService(Database::connection(),$this->config))->resolve($token);require BASE_PATH.'/app/Views/guest-portal/show.php';}
        catch(Throwable $error){$this->error($error);}
    }

    public function precheckin(string $token):void
    {
        try{$db=Database::connection();$portalService=new GuestPortalService($db,$this->config);$portal=$portalService->resolve($token);$reservationId=$portalService->reservationId($token);$service=new PreCheckinService($db);$service->ensure($reservationId);$precheckin=$service->load($reservationId);require BASE_PATH.'/app/Views/guest-portal/precheckin.php';}
        catch(Throwable $error){$this->error($error);}
    }

    public function savePrecheckin(string $token):never
    {
        try{Csrf::verify($_POST['_csrf']??null);$db=Database::connection();(new RateLimiter($db))->assertAllowed('precheckin|'.Security::clientIp().'|'.hash('sha256',$token),20,3600);$portal=new GuestPortalService($db,$this->config);$reservationId=$portal->reservationId($token);$service=new PreCheckinService($db);$service->save($reservationId,$_POST);if(($_POST['intent']??'save')==='submit'){$accepted=is_array($_POST['rules']??null)?$_POST['rules']:[];$service->submit($reservationId,$accepted,(string)($_POST['responsible_name']??''),(string)($_POST['responsible_cpf']??''),Security::clientIp(),$_SERVER['HTTP_USER_AGENT']??'');flash('success','Pré-check-in enviado para análise.');}else flash('success','Rascunho salvo.');}
        catch(Throwable $error){flash('error',$error->getMessage());}
        redirect(base_url('minha-reserva/'.rawurlencode($token).'/pre-checkin'));
    }

    public function signatureCode(string $token):never
    {
        try{Csrf::verify($_POST['_csrf']??null);$db=Database::connection();(new RateLimiter($db))->assertAllowed('contract-code|'.Security::clientIp().'|'.hash('sha256',$token),5,3600);$portal=new GuestPortalService($db,$this->config);$reservationId=$portal->reservationId($token);$contract=$this->contract($db,$reservationId);$provider=new LocalAuditSignatureProvider($db,(string)$this->config['app_key']);$challenge=$provider->issueChallenge((int)$contract['id'],'GUEST');$stmt=$db->prepare('SELECT * FROM reservas WHERE id=?');$stmt->execute([$reservationId]);(new NotificationService($db))->signatureCode($stmt->fetch(),$challenge['code'],$challenge['expires_at'],'GUEST');flash('success','Código enviado para seus canais cadastrados.');}
        catch(Throwable $error){flash('error',$error->getMessage());}
        redirect(base_url('minha-reserva/'.rawurlencode($token).'#contrato'));
    }

    public function sign(string $token):never
    {
        try{Csrf::verify($_POST['_csrf']??null);$db=Database::connection();(new RateLimiter($db))->assertAllowed('contract-sign|'.Security::clientIp().'|'.hash('sha256',$token),10,3600);$portal=new GuestPortalService($db,$this->config);$reservationId=$portal->reservationId($token);$contract=$this->contract($db,$reservationId);$provider=new LocalAuditSignatureProvider($db,(string)$this->config['app_key']);$provider->sign((int)$contract['id'],'GUEST',(string)($_POST['code']??''),['name'=>$_POST['name']??'','cpf'=>$_POST['cpf']??'','accepted'=>isset($_POST['accepted']),'ip'=>Security::clientIp(),'user_agent'=>$_SERVER['HTTP_USER_AGENT']??'','delivery'=>'EMAIL_OR_WHATSAPP']);(new \Refugio\Services\ReservationAutomationService($db,$this->config))->emit('CONTRACT_SIGNED',$reservationId,[],'contract-signed:'.$contract['id'].':guest');flash('success','Aceite registrado com trilha de auditoria.');}
        catch(Throwable $error){flash('error',$error->getMessage());}
        redirect(base_url('minha-reserva/'.rawurlencode($token).'#contrato'));
    }

    public function contractPdf(string $token):never
    {
        try{$db=Database::connection();$portal=new GuestPortalService($db,$this->config);$reservationId=$portal->reservationId($token);$contract=$this->contract($db,$reservationId);$path=realpath(BASE_PATH.'/'.(string)$contract['pdf_path']);$storage=realpath(BASE_PATH.'/storage/contracts');if(!$path||!$storage||!str_starts_with($path,$storage)||!is_file($path))throw new RuntimeException('PDF ainda não está disponível.');header('Content-Type: application/pdf');header('Content-Length: '.filesize($path));header('Cache-Control: private, no-store');header('Content-Disposition: inline; filename="contrato-'.preg_replace('/[^A-Za-z0-9_-]/','-',$contract['contract_number']).'.pdf"');readfile($path);exit;}
        catch(Throwable $error){$this->error($error);exit;}
    }

    private function contract(\PDO $db,int $reservationId):array{$stmt=$db->prepare("SELECT * FROM reservation_contracts WHERE reservation_id=? AND status NOT IN ('SUPERSEDED','CANCELLED','EXPIRED') ORDER BY version_no DESC LIMIT 1");$stmt->execute([$reservationId]);return $stmt->fetch()?:throw new RuntimeException('Contrato ainda não está disponível.');}
    private function error(Throwable $error):void{http_response_code(404);$message=$error->getMessage();require BASE_PATH.'/app/Views/guest-portal/error.php';}
}

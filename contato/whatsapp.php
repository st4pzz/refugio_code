<?php
declare(strict_types=1);

$config=require dirname(__DIR__).'/bootstrap.php';
$db=Refugio\Config\Database::connection();
$reference=(string)($_SESSION['_whatsapp_reference']??'');$leadId=(int)($_SESSION['_whatsapp_lead_id']??0);
if($reference===''||$leadId<=0){$reference=strtoupper(bin2hex(random_bytes(5)));$leadId=(new Refugio\Repositories\CustomerRepository($db))->ensureLead('WHATSAPP',null,null,'SITE',['reference'=>$reference,'first_click_at'=>date(DATE_ATOM)]);$_SESSION['_whatsapp_reference']=$reference;$_SESSION['_whatsapp_lead_id']=$leadId;}
(new Refugio\Services\AttributionService($db))->linkLead($leadId);
$phone=preg_replace('/\D+/','',(string)$config['contact_whatsapp']);
$text='Olá! Vim pelo site do Refúgio do Cuscuzeiro. Referência: REF-'.$reference;
header('Location: https://wa.me/'.$phone.'?'.http_build_query(['text'=>$text],'','&',PHP_QUERY_RFC3986),true,303);
exit;

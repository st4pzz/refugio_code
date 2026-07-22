<?php
declare(strict_types=1);
$config=require dirname(__DIR__).'/bootstrap.php';
$controller=new Refugio\Controllers\GuestPortalController($config);$token=(string)($_GET['token']??'');$action=(string)($_GET['action']??'show');
if($_SERVER['REQUEST_METHOD']==='POST'){
    match($action){'precheckin-save'=>$controller->savePrecheckin($token),'signature-code'=>$controller->signatureCode($token),'sign'=>$controller->sign($token),default=>$controller->show($token)};
    exit;
}
match($action){'precheckin'=>$controller->precheckin($token),'contract-pdf'=>$controller->contractPdf($token),default=>$controller->show($token)};

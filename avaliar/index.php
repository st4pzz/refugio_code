<?php
declare(strict_types=1);
$config=require dirname(__DIR__).'/bootstrap.php';
$controller=new Refugio\Controllers\PublicReviewController($config);
if (($_GET['page']??'')==='obrigado') $controller->success();
$controller->show((string)($_GET['token']??''));

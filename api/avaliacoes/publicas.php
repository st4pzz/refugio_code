<?php
declare(strict_types=1);
$config=require dirname(__DIR__,2).'/bootstrap.php';
if ($_SERVER['REQUEST_METHOD']!=='GET') { http_response_code(405); header('Allow: GET'); exit; }
(new Refugio\Controllers\PublicReviewController($config))->approved();

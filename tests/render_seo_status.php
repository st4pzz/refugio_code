<?php
declare(strict_types=1);

$route = trim((string) ($argv[1] ?? ''), '/');
$_GET['route'] = $route;
$_SERVER['REQUEST_URI'] = '/' . $route . '/';
ob_start();
require dirname(__DIR__) . '/seo/index.php';
ob_end_clean();
echo http_response_code() . PHP_EOL;


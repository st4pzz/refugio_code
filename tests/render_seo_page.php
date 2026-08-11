<?php
declare(strict_types=1);

$route = trim((string) ($argv[1] ?? ''), '/');
$_GET['route'] = $route;
$_SERVER['REQUEST_URI'] = '/' . $route . '/';
require dirname(__DIR__) . '/seo/index.php';


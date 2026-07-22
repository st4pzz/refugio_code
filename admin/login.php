<?php
declare(strict_types=1);
$config = require dirname(__DIR__) . '/bootstrap.php';
$controller = new Refugio\Controllers\AdminController($config);
if ($_SERVER['REQUEST_METHOD'] === 'POST') $controller->login();
$controller->loginForm();

<?php
declare(strict_types=1);

if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(404);
    exit;
}

$config = require dirname(__DIR__) . '/bootstrap.php';
$config['url'] = 'http://127.0.0.1:8765';
$reservation = [
    'nome_cliente' => 'Maria Aparecida Silva',
    'checkout' => '2026-07-12',
];
$token = str_repeat('a', 64);
$errors = [];
$old = [];
require BASE_PATH . '/app/Views/reviews/form.php';

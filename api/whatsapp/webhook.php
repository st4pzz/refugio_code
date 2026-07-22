<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';
$controller = new Refugio\Controllers\WhatsAppWebhookController();
if ($_SERVER['REQUEST_METHOD'] === 'GET') $controller->verify();
if ($_SERVER['REQUEST_METHOD'] === 'POST') $controller->receive();
http_response_code(405);
header('Allow: GET, POST');

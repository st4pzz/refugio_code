<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';
$result = (new Refugio\Services\WhatsAppService())->ensureBookingBillingTemplate();
echo ($result['created'] ? 'Template criado: ' : 'Template existente: ') . ($result['template']['status'] ?? 'PENDING') . PHP_EOL;

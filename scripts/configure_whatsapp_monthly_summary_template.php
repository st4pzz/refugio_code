<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';

$db = Refugio\Config\Database::connection();
$result = (new Refugio\Services\MonthlyReservationSummaryService($db))->ensureTemplate();
$template = $result['template'] ?? [];
$status = (string) ($template['status'] ?? ($result['created'] ? 'PENDING' : 'UNKNOWN'));
$action = $result['created'] ? 'criado e enviado para analise' : 'ja existente';
fwrite(STDOUT, "Template {$action}. Status: {$status}.\n");

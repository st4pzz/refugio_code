<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$config = require dirname(__DIR__) . '/bootstrap.php';
$result = (new Refugio\Services\ReviewInviteService(
    Refugio\Config\Database::connection(),
    $config
))->runCron();

fwrite(STDOUT, sprintf(
    "Convites enviados: %d; lembretes: %d; expirados: %d; falhas: %d.\n",
    $result['sent'],
    $result['reminders'],
    $result['expired'],
    $result['failed']
));

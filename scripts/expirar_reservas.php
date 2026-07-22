<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$config = require dirname(__DIR__) . '/bootstrap.php';
$service = new Refugio\Services\ReservationService(Refugio\Config\Database::connection(), $config);
$count = $service->expireOverdue();
fwrite(STDOUT, sprintf("%s: %d reserva(s) expirada(s).\n", date('c'), $count));

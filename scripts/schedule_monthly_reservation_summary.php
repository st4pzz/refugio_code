<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';

$db = Refugio\Config\Database::connection();
$count = (new Refugio\Services\MonthlyReservationSummaryService($db))->enqueueDaily();
fwrite(STDOUT, $count . " envio(s) de resumo mensal agendado(s).\n");

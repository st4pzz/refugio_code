<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';

$force = in_array('--force', $argv, true);
if (!$force && (int) date('N') !== 1) {
    fwrite(STDOUT, "Hoje nao e segunda-feira; nenhum resumo foi agendado.\n");
    exit(0);
}

$db = Refugio\Config\Database::connection();
$count = (new Refugio\Services\MonthlyReservationSummaryService($db))->enqueueWeekly();
fwrite(STDOUT, $count . " envio(s) de resumo mensal agendado(s).\n");

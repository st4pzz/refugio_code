<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';
$db = Refugio\Config\Database::connection();
$service = new Refugio\Services\MonthlyReservationSummaryService($db);
echo 'Habilitado: ' . (Refugio\Support\Env::bool('WHATSAPP_MONTHLY_SUMMARY_ENABLED', true) ? 'sim' : 'não') . PHP_EOL;
echo 'Template: ' . Refugio\Support\Env::get('WHATSAPP_MONTHLY_SUMMARY_TEMPLATE', 'resumo_reservas_mensal_v2') . PHP_EOL;
echo 'Destinatários: ' . implode(', ', $service->recipients()) . PHP_EOL;
$jobs = $db->query("SELECT id,status,payload_json,erro_ultimo,finalizado_em FROM jobs WHERE tipo='RESERVATION_MONTHLY_SUMMARY' ORDER BY id DESC LIMIT 20")->fetchAll();
foreach ($jobs as $job) {
    $payload = json_decode((string) $job['payload_json'], true) ?: [];
    echo json_encode(['id' => $job['id'], 'recipient' => $payload['recipient'] ?? null, 'status' => $job['status'], 'finished_at' => $job['finalizado_em'], 'error' => $job['erro_ultimo']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
}
echo "CONCLUIDO indica processamento pelo worker; confira entrega no provedor WhatsApp. Nenhuma mensagem foi enviada por este diagnóstico.\n";

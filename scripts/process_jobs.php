<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';
$db = Refugio\Config\Database::connection();
$queue = new Refugio\Services\JobQueueService($db);
$ical = new Refugio\Services\ICalendarService($db);
$ical->enqueueDue($queue);
$limit = 50;
foreach ($argv as $argument) if (str_starts_with($argument, '--limit=')) $limit = max(1, min(500, (int) substr($argument, 8)));
$worker = gethostname() . ':' . getmypid();
$queue->releaseStale();
$processed = 0;
while ($processed < $limit && ($job = $queue->reserve($worker))) {
    try {
        match ($job['tipo']) {
            'WHATSAPP_WEBHOOK' => (new Refugio\Services\WhatsAppWebhookService($db))->process((int) $job['payload']['event_id']),
            'WHATSAPP_MEDIA' => (new Refugio\Services\WhatsAppWebhookService($db))->downloadMedia((int) $job['payload']['message_id']),
            'MARKETING_SYNC' => (new Refugio\Services\MarketingSyncService($db))->sync((int) $job['payload']['integration_id'], (string) $job['payload']['start'], (string) $job['payload']['end'], isset($job['payload']['user_id']) ? (int) $job['payload']['user_id'] : null),
            'RESERVATION_AUTOMATION' => (new Refugio\Services\ReservationAutomationService($db, require dirname(__DIR__) . '/config/app.php'))->process((int) $job['payload']['run_id']),
            'ICAL_SYNC' => $ical->syncSource((int) $job['payload']['source_id']),
            'CONTRACT_PDF' => (new Refugio\Services\ContractPdfService($db))->generate((int) $job['payload']['contract_id']),
            default => throw new RuntimeException('Tipo de job nao suportado: ' . $job['tipo']),
        };
        $queue->complete((int) $job['id']);
    } catch (Throwable $error) {
        $queue->fail($job, $error);
        error_log('[jobs] ' . $job['tipo'] . ' #' . $job['id'] . ': ' . $error->getMessage());
    }
    $processed++;
}
fwrite(STDOUT, "{$processed} job(s) processado(s).\n");

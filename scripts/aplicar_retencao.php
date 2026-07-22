<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';
$db = Refugio\Config\Database::connection();
$mediaDays = max(1, Refugio\Support\Env::int('WHATSAPP_MEDIA_RETENTION_DAYS', 180));
$webhookDays = max(7, Refugio\Support\Env::int('WEBHOOK_PAYLOAD_RETENTION_DAYS', 90));
$auditDays = max(90, Refugio\Support\Env::int('AUDIT_RETENTION_DAYS', 730));
$jobDays = max(7, Refugio\Support\Env::int('COMPLETED_JOB_RETENTION_DAYS', 30));
$stmt = $db->prepare("SELECT id,media_path FROM mensagens WHERE media_path IS NOT NULL AND created_at<DATE_SUB(NOW(),INTERVAL ? DAY) LIMIT 1000");
$stmt->execute([$mediaDays]);
$storage = realpath(BASE_PATH . '/storage/conversas');
$removed = 0;
foreach ($stmt->fetchAll() as $media) {
    $path = realpath(BASE_PATH . '/' . ltrim((string) $media['media_path'], '/'));
    if ($path && $storage && str_starts_with($path, $storage . DIRECTORY_SEPARATOR) && is_file($path) && unlink($path)) $removed++;
    $db->prepare('UPDATE mensagens SET media_path=NULL WHERE id=?')->execute([$media['id']]);
}
$webhooks = $db->exec("DELETE FROM whatsapp_webhook_eventos WHERE status IN ('PROCESSADO','IGNORADO') AND recebido_em<DATE_SUB(NOW(),INTERVAL {$webhookDays} DAY)");
$audits = $db->exec("DELETE FROM auditoria WHERE created_at<DATE_SUB(NOW(),INTERVAL {$auditDays} DAY)");
$jobs = $db->exec("DELETE FROM jobs WHERE status IN ('CONCLUIDO','CANCELADO') AND finalizado_em<DATE_SUB(NOW(),INTERVAL {$jobDays} DAY)");
fwrite(STDOUT, "Retencao aplicada: {$removed} midia(s), {$webhooks} webhook(s), {$audits} auditoria(s), {$jobs} job(s).\n");

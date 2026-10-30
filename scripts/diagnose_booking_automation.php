<?php
declare(strict_types=1);

// Somente leitura: não chama APIs, não processa jobs e não envia mensagens.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';

use Refugio\Config\Database;
use Refugio\Support\Env;

$problems = 0;
$report = static function (string $label, bool $ok, string $detail = '') use (&$problems): void {
    if (!$ok) $problems++;
    fwrite(STDOUT, ($ok ? '[OK] ' : '[ATENÇÃO] ') . $label . ($detail !== '' ? ': ' . $detail : '') . PHP_EOL);
};
foreach (['OPENAI_CONVERSATIONS_ENABLED', 'OPENAI_CONVERSATIONS_AUTO_REPLY'] as $flag) {
    $report($flag, Env::bool($flag, true));
}
$report('Chave OpenAI configurada', trim(Env::get('OPENAI_API_KEY')) !== '');
$report('Extensão cURL', extension_loaded('curl'));
$report('Extensão mbstring', extension_loaded('mbstring'));
fwrite(STDOUT, 'Modelo de atendimento: ' . Env::get('OPENAI_CONVERSATIONS_MODEL', 'gpt-5.6-luna') . PHP_EOL);
try {
    $db = Database::connection();
    $tables = ['calendar_sources', 'calendar_external_events', 'calendar_sync_logs', 'calendar_export_tokens', 'pricing_special_dates', 'conversation_ai_drafts', 'conversation_auto_replies', 'booking_billing_contacts', 'booking_billing_deliveries', 'jobs'];
    $present = [];
    foreach ($tables as $table) {
        $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
        $stmt->execute([$table]);
        $present[$table] = (int) $stmt->fetchColumn() > 0;
        $report('Tabela ' . $table, $present[$table], $present[$table] ? '' : 'execute php scripts/migrate.php');
    }
    if ($present['jobs']) {
        $rows = $db->query("SELECT tipo,status,COUNT(*) total,MIN(disponivel_em) primeira_disponibilidade,MAX(updated_at) ultima_atualizacao FROM jobs WHERE tipo IN ('CONVERSATION_AI_REPLY','WHATSAPP_WEBHOOK','ICAL_SYNC') GROUP BY tipo,status")->fetchAll(PDO::FETCH_ASSOC);
        fwrite(STDOUT, "Fila (compare os horários com o cron):\n" . json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
        // Não imprimir payloads, mensagens de hóspedes, tokens ou erros brutos.
        $failed = $db->query("SELECT id,tipo,status,tentativas,updated_at FROM jobs WHERE tipo IN ('CONVERSATION_AI_REPLY','WHATSAPP_WEBHOOK','ICAL_SYNC') AND status IN ('FALHOU','PROCESSANDO') ORDER BY id DESC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
        fwrite(STDOUT, "Jobs para investigar no painel/log do servidor:\n" . json_encode($failed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    }
    if ($present['calendar_sources']) {
        $rows = $db->query('SELECT id,provider,ativo,ultimo_status,ultimo_sync_em,proximo_sync_em FROM calendar_sources ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        fwrite(STDOUT, "Sincronizações (sem URLs privadas):\n" . json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    }
    $rows = $db->query("SELECT status,COUNT(*) total FROM conversas WHERE janela_atendimento_ate>NOW() GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
    fwrite(STDOUT, "Conversas na janela de 24h (AGUARDANDO_EQUIPE pausa a IA):\n" . json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
} catch (Throwable $error) {
    $report('Diagnóstico do banco', false, 'falha de consulta; consulte o log privado do servidor');
    error_log('[diagnose-booking-automation] ' . $error->getMessage());
}
exit($problems > 0 ? 1 : 0);

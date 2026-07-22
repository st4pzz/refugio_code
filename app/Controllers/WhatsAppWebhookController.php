<?php
declare(strict_types=1);

namespace Refugio\Controllers;

use JsonException;
use Refugio\Config\Database;
use Refugio\Services\WhatsAppWebhookService;
use Refugio\Support\Env;
use Throwable;

final class WhatsAppWebhookController
{
    public function verify(): never
    {
        $mode = (string) ($_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '');
        $token = (string) ($_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '');
        $challenge = (string) ($_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '');
        $expected = Env::get('WHATSAPP_VERIFY_TOKEN');
        if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
            http_response_code(200); header('Content-Type: text/plain; charset=UTF-8'); echo $challenge; exit;
        }
        http_response_code(403); header('Content-Type: text/plain; charset=UTF-8'); echo 'Forbidden'; exit;
    }

    public function receive(): never
    {
        $raw = file_get_contents('php://input') ?: '';
        $signature = (string) ($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '');
        if (!WhatsAppWebhookService::validSignature($raw, $signature)) {
            http_response_code(401); header('Content-Type: application/json'); echo '{"error":"invalid signature"}'; exit;
        }
        try {
            (new WhatsAppWebhookService(Database::connection()))->accept($raw);
            http_response_code(200); header('Content-Type: text/plain; charset=UTF-8'); echo 'EVENT_RECEIVED';
        } catch (JsonException) {
            http_response_code(400); header('Content-Type: application/json'); echo '{"error":"invalid payload"}';
        } catch (Throwable $error) {
            error_log('[whatsapp-webhook] ' . $error->getMessage());
            http_response_code(500); header('Content-Type: application/json'); echo '{"error":"temporary failure"}';
        }
        exit;
    }
}

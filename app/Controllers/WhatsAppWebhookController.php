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
    $debug = static function (string $message): void {
        error_log('[whatsapp-webhook-debug] ' . $message);
    };

    $debug(
        'POST RECEBIDO host=' . ($_SERVER['HTTP_HOST'] ?? '') .
        ' uri=' . ($_SERVER['REQUEST_URI'] ?? '')
    );

    $raw = file_get_contents('php://input') ?: '';

    $signature = (string) (
        $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? ''
    );

    $appSecret = Env::get('WHATSAPP_APP_SECRET');

    $debug(
        'body_bytes=' . strlen($raw) .
        ' signature=' . ($signature !== '' ? 'PRESENTE' : 'AUSENTE') .
        ' app_secret=' . ($appSecret !== '' ? 'PRESENTE' : 'AUSENTE')
    );

    $signatureValid = WhatsAppWebhookService::validSignature(
        $raw,
        $signature,
        $appSecret
    );

    $debug(
        'signature_valid=' . ($signatureValid ? 'SIM' : 'NAO')
    );

    if (!$signatureValid) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo '{"error":"invalid signature"}';
        exit;
    }

    try {
        $db = Database::connection();

        $databaseName = (string) $db
            ->query('SELECT DATABASE()')
            ->fetchColumn();

        $debug('database=' . $databaseName);

        $service = new WhatsAppWebhookService($db);

        $eventId = $service->accept($raw);

        $debug('evento_gravado id=' . $eventId);

        $service->process($eventId);

        $debug('evento_processado id=' . $eventId);

        http_response_code(200);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'EVENT_RECEIVED';

    } catch (JsonException $error) {

        $debug('JSON_INVALIDO');

        http_response_code(400);
        header('Content-Type: application/json');
        echo '{"error":"invalid payload"}';

    } catch (Throwable $error) {

        $debug(
            'ERRO=' .
            get_class($error) .
            ': ' .
            $error->getMessage()
        );

        error_log(
            '[whatsapp-webhook] ' .
            $error->getMessage()
        );

        http_response_code(500);
        header('Content-Type: application/json');
        echo '{"error":"temporary failure"}';
    }

    exit;
}
}

<?php
declare(strict_types=1);

namespace Refugio\Controllers;

use InvalidArgumentException;
use JsonException;
use Refugio\Config\Database;
use Refugio\Services\GoogleAdsScriptImportService;
use Refugio\Support\Env;
use Throwable;

final class GoogleAdsScriptWebhookController
{
    public function receive(): never
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Allow: POST');
            $this->json(['error' => 'method_not_allowed'], 405);
        }
        $raw = file_get_contents('php://input') ?: '';
        $maxBytes = max(1024, min(5 * 1024 * 1024, Env::int('GOOGLE_ADS_SCRIPT_MAX_PAYLOAD_BYTES', 1048576)));
        if ($raw === '' || strlen($raw) > $maxBytes) {
            $this->json(['error' => 'invalid_payload_size'], 413);
        }
        $secret = trim(Env::get('GOOGLE_ADS_SCRIPT_SECRET'));
        if (strlen($secret) < 32) {
            error_log('[google-ads-script] GOOGLE_ADS_SCRIPT_SECRET ausente ou fraco.');
            $this->json(['error' => 'integration_not_configured'], 503);
        }
        $timestamp = (string) ($_SERVER['HTTP_X_REFUGIO_TIMESTAMP'] ?? '');
        $signature = (string) ($_SERVER['HTTP_X_REFUGIO_SIGNATURE'] ?? '');
        $maxAge = max(30, min(1800, Env::int('GOOGLE_ADS_SCRIPT_MAX_AGE_SECONDS', 300)));
        if (!GoogleAdsScriptImportService::validSignature($raw, $timestamp, $signature, $secret, null, $maxAge)) {
            $this->json(['error' => 'invalid_signature'], 401);
        }

        try {
            $result = (new GoogleAdsScriptImportService(Database::connection()))->import(
                $raw,
                (string) ($_SERVER['REMOTE_ADDR'] ?? '')
            );
            $this->json($result, 200);
        } catch (JsonException) {
            $this->json(['error' => 'invalid_json'], 400);
        } catch (InvalidArgumentException $error) {
            $this->json(['error' => 'invalid_payload', 'message' => $error->getMessage()], 422);
        } catch (Throwable $error) {
            error_log('[google-ads-script] ' . $error->getMessage());
            $this->json(['error' => 'temporary_failure'], 500);
        }
    }

    private function json(array $payload, int $status): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: private, no-store');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

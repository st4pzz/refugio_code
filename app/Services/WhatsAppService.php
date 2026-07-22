<?php
declare(strict_types=1);

namespace Refugio\Services;

use Refugio\Support\Env;
use RuntimeException;

final class WhatsAppService
{
    public function sendTemplate(string $to, string $template, array $parameters): string
    {
        $phoneId = Env::get('WHATSAPP_PHONE_NUMBER_ID');
        $token = Env::get('WHATSAPP_ACCESS_TOKEN');
        if ($phoneId === '' || $token === '' || $template === '') throw new RuntimeException('WhatsApp Cloud API nao configurada.');
        $url = sprintf('https://graph.facebook.com/%s/%s/messages', rawurlencode(Env::get('WHATSAPP_API_VERSION', 'v23.0')), rawurlencode($phoneId));
        $body = [
            'messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'template',
            'template' => ['name' => $template, 'language' => ['code' => Env::get('WHATSAPP_TEMPLATE_LANGUAGE', 'pt_BR')]],
        ];
        if ($parameters) {
            $body['template']['components'] = [['type' => 'body', 'parameters' => array_map(static fn($value) => ['type' => 'text', 'text' => (string) $value], $parameters)]];
        }
        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'], CURLOPT_POSTFIELDS => $json]);
            $response = curl_exec($curl); $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE); $error = curl_error($curl); curl_close($curl);
            if ($response === false || $status >= 300) throw new RuntimeException('Falha na API do WhatsApp: ' . ($error ?: 'HTTP ' . $status));
        } else {
            $context = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 20, 'ignore_errors' => true, 'header' => "Authorization: Bearer {$token}\r\nContent-Type: application/json\r\n", 'content' => $json]]);
            $response = @file_get_contents($url, false, $context);
            if ($response === false) throw new RuntimeException('Falha na API do WhatsApp.');
        }
        $decoded = json_decode((string) $response, true, 512, JSON_THROW_ON_ERROR);
        if (!empty($decoded['error'])) throw new RuntimeException('WhatsApp recusou a mensagem: ' . ($decoded['error']['message'] ?? 'erro desconhecido'));
        return (string) ($decoded['messages'][0]['id'] ?? 'sem-id');
    }
}

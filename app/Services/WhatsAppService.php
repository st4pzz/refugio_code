<?php
declare(strict_types=1);

namespace Refugio\Services;

use CURLFile;
use Refugio\Support\Env;
use RuntimeException;

final class WhatsAppService
{
    public function sendText(string $to, string $text, ?string $replyToExternalId = null): string
    {
        $body = ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $to, 'type' => 'text', 'text' => ['preview_url' => false, 'body' => $text]];
        if ($replyToExternalId) {
            $body['context'] = ['message_id' => $replyToExternalId];
        }
        return $this->sendPayload($body);
    }

    public function sendTemplate(string $to, string $template, array $parameters): string
    {
        $body = [
            'messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'template',
            'template' => ['name' => $template, 'language' => ['code' => Env::get('WHATSAPP_TEMPLATE_LANGUAGE', 'pt_BR')]],
        ];
        if ($parameters) {
            $body['template']['components'] = [['type' => 'body', 'parameters' => array_map(static fn($value) => ['type' => 'text', 'text' => (string) $value], $parameters)]];
        }
        return $this->sendPayload($body);
    }

    public function sendMedia(string $to, string $type, string $mediaId, ?string $caption = null, ?string $filename = null): string
    {
        if (!in_array($type, ['image','document','audio','video'], true)) {
            throw new RuntimeException('Tipo de midia nao suportado pelo WhatsApp.');
        }
        $media = ['id' => $mediaId];
        if ($caption !== null && in_array($type, ['image','document','video'], true)) $media['caption'] = $caption;
        if ($filename !== null && $type === 'document') $media['filename'] = $filename;
        return $this->sendPayload(['messaging_product' => 'whatsapp','to' => $to,'type' => $type,$type => $media]);
    }

    public function uploadMedia(string $path, string $mime, string $filename): string
    {
        $this->configured();
        if (!function_exists('curl_init') || !is_file($path)) {
            throw new RuntimeException('Upload de midia indisponivel.');
        }
        $curl = curl_init($this->baseUrl() . '/' . rawurlencode($this->phoneId()) . '/media');
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->token()],
            CURLOPT_POSTFIELDS => ['messaging_product' => 'whatsapp','file' => new CURLFile($path, $mime, $filename)],
        ]);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        $decoded = $this->decodeResponse($response, $status, $error);
        $id = (string) ($decoded['id'] ?? '');
        if ($id === '') throw new RuntimeException('WhatsApp nao retornou o identificador da midia.');
        return $id;
    }

    public function downloadMedia(string $mediaId): array
    {
        $metadata = $this->requestJson('GET', $this->baseUrl() . '/' . rawurlencode($mediaId));
        $url = (string) ($metadata['url'] ?? '');
        if ($url === '' || !str_starts_with($url, 'https://')) {
            throw new RuntimeException('URL de midia invalida retornada pelo WhatsApp.');
        }
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true,CURLOPT_FOLLOWLOCATION => true,CURLOPT_MAXREDIRS => 3,CURLOPT_TIMEOUT => 60,CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->token()]]);
        $content = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $mime = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($content === false || $status >= 300) throw new RuntimeException('Falha ao baixar midia do WhatsApp: ' . ($error ?: 'HTTP ' . $status));
        return ['content' => $content, 'mime' => $mime ?: (string) ($metadata['mime_type'] ?? 'application/octet-stream'), 'sha256' => (string) ($metadata['sha256'] ?? hash('sha256', $content)), 'size' => strlen($content)];
    }

    public function listTemplates(int $limit = 100): array
    {
        $waba = Env::get('WHATSAPP_BUSINESS_ACCOUNT_ID');
        if ($waba === '') throw new RuntimeException('WHATSAPP_BUSINESS_ACCOUNT_ID nao configurado.');
        $url = $this->baseUrl() . '/' . rawurlencode($waba) . '/message_templates?' . http_build_query(['fields' => 'id,name,language,status,category,components','limit' => max(1, min(100, $limit))]);
        $templates = [];
        do {
            $response = $this->requestJson('GET', $url);
            foreach (($response['data'] ?? []) as $item) if (is_array($item)) $templates[] = $item;
            $url = (string) ($response['paging']['next'] ?? '');
        } while ($url !== '' && count($templates) < 500);
        return $templates;
    }

    private function sendPayload(array $body): string
    {
        $decoded = $this->requestJson('POST', $this->baseUrl() . '/' . rawurlencode($this->phoneId()) . '/messages', $body);
        $id = (string) ($decoded['messages'][0]['id'] ?? '');
        if ($id === '') throw new RuntimeException('WhatsApp nao retornou o identificador da mensagem.');
        return $id;
    }

    private function requestJson(string $method, string $url, ?array $body = null): array
    {
        $this->configured();
        if (!function_exists('curl_init')) throw new RuntimeException('A extensao cURL e obrigatoria para a WhatsApp Cloud API.');
        $curl = curl_init($url);
        $headers = ['Authorization: Bearer ' . $this->token(), 'Accept: application/json'];
        $options = [CURLOPT_RETURNTRANSFER => true,CURLOPT_TIMEOUT => 30,CURLOPT_HTTPHEADER => $headers];
        if ($method === 'POST') {
            $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $json;
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_HTTPHEADER] = $headers;
        }
        curl_setopt_array($curl, $options);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        return $this->decodeResponse($response, $status, $error);
    }

    private function decodeResponse(string|false $response, int $status, string $transportError): array
    {
        if ($response === false) throw new RuntimeException('Falha de transporte na API do WhatsApp: ' . ($transportError ?: 'erro desconhecido'));
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) throw new RuntimeException('Resposta invalida da API do WhatsApp.');
        if ($status >= 300 || !empty($decoded['error'])) {
            $code = (string) ($decoded['error']['code'] ?? $status);
            $message = mb_substr((string) ($decoded['error']['message'] ?? 'requisicao recusada'), 0, 500);
            throw new RuntimeException("WhatsApp recusou a requisicao ({$code}): {$message}");
        }
        return $decoded;
    }

    private function configured(): void
    {
        if ($this->phoneId() === '' || $this->token() === '') throw new RuntimeException('WhatsApp Cloud API nao configurada.');
    }

    private function baseUrl(): string
    {
        return 'https://graph.facebook.com/' . rawurlencode(Env::get('WHATSAPP_API_VERSION', 'v24.0'));
    }

    private function phoneId(): string { return Env::get('WHATSAPP_PHONE_NUMBER_ID'); }
    private function token(): string { return Env::get('WHATSAPP_ACCESS_TOKEN'); }
}

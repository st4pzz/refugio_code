<?php
declare(strict_types=1);

namespace Refugio\Services;

use Refugio\Support\Env;
use RuntimeException;

final class OpenAiResponsesClient
{
    private const ENDPOINT = 'https://api.openai.com/v1/responses';

    public function create(array $payload): array
    {
        $apiKey = trim(Env::get('OPENAI_API_KEY'));
        if ($apiKey === '') {
            throw new RuntimeException('Configure OPENAI_API_KEY antes de solicitar uma analise com IA.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('A extensao PHP cURL e obrigatoria para usar a OpenAI.');
        }

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ];
        $organization = trim(Env::get('OPENAI_ORGANIZATION'));
        $project = trim(Env::get('OPENAI_PROJECT'));
        if ($organization !== '') {
            $headers[] = 'OpenAI-Organization: ' . $organization;
        }
        if ($project !== '') {
            $headers[] = 'OpenAI-Project: ' . $project;
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timeout = max(15, min(120, Env::int('OPENAI_TIMEOUT_SECONDS', 90)));
        $maxRetries = max(0, min(3, Env::int('OPENAI_MAX_RETRIES', 2)));

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            $responseHeaders = [];
            $curl = curl_init(self::ENDPOINT);
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'RefugioCuscuzeiro/1.0 marketing-analysis',
                CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$responseHeaders): int {
                    $length = strlen($line);
                    $parts = explode(':', $line, 2);
                    if (count($parts) === 2) {
                        $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                    }
                    return $length;
                },
            ]);
            $raw = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $transportError = curl_error($curl);
            curl_close($curl);

            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($decoded) && $status >= 200 && $status < 300) {
                $decoded['_request_id'] = $responseHeaders['x-request-id'] ?? null;
                return $decoded;
            }

            $retryable = $raw === false || $status === 408 || $status === 409 || $status === 429 || $status >= 500;
            if ($retryable && $attempt < $maxRetries) {
                usleep((int) (300000 * (2 ** $attempt)));
                continue;
            }

            $message = is_array($decoded)
                ? (string) ($decoded['error']['message'] ?? 'Requisicao recusada pela OpenAI.')
                : ($transportError !== '' ? $transportError : 'Resposta invalida da OpenAI.');
            $requestId = $responseHeaders['x-request-id'] ?? null;
            $suffix = $requestId ? ' Request ID: ' . mb_substr((string) $requestId, 0, 190) . '.' : '';
            throw new RuntimeException('Falha na OpenAI (HTTP ' . $status . '): ' . mb_substr($message, 0, 500) . $suffix);
        }

        throw new RuntimeException('Falha na OpenAI apos novas tentativas.');
    }

    public static function outputText(array $response): string
    {
        if (isset($response['output_text']) && is_string($response['output_text']) && trim($response['output_text']) !== '') {
            return trim($response['output_text']);
        }

        $texts = [];
        foreach ($response['output'] ?? [] as $item) {
            if (!is_array($item) || ($item['type'] ?? null) !== 'message') {
                continue;
            }
            foreach ($item['content'] ?? [] as $content) {
                if (!is_array($content)) {
                    continue;
                }
                if (($content['type'] ?? null) === 'refusal') {
                    throw new RuntimeException('A OpenAI recusou a analise: ' . mb_substr((string) ($content['refusal'] ?? 'sem detalhes'), 0, 500));
                }
                if (($content['type'] ?? null) === 'output_text' && isset($content['text'])) {
                    $texts[] = (string) $content['text'];
                }
            }
        }

        $text = trim(implode("\n", $texts));
        if ($text === '') {
            throw new RuntimeException('A OpenAI concluiu a requisicao sem retornar uma analise textual.');
        }
        return $text;
    }
}

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
        return $this->request('POST', self::ENDPOINT, $payload);
    }

    public function createInBackgroundAndWait(array $payload): array
    {
        return $this->waitForBackground($this->createInBackground($payload));
    }

    public function createInBackground(array $payload): array
    {
        $payload['background'] = true;
        return $this->create($payload);
    }

    public function waitForBackground(array $response): array
    {
        if (!self::isPending($response)) {
            return $response;
        }

        $responseId = trim((string) ($response['id'] ?? ''));
        if ($responseId === '') {
            throw new RuntimeException('A OpenAI iniciou a analise sem retornar um identificador para acompanhamento.');
        }

        $timeout = max(30, min(1800, Env::int('OPENAI_BACKGROUND_TIMEOUT_SECONDS', 600)));
        $pollInterval = max(1, min(10, Env::int('OPENAI_BACKGROUND_POLL_SECONDS', 2)));
        $deadline = microtime(true) + $timeout;
        do {
            sleep($pollInterval);
            $response = $this->retrieve($responseId);
        } while (self::isPending($response) && microtime(true) < $deadline);

        if (self::isPending($response)) {
            throw new RuntimeException('A OpenAI ainda estava processando a analise apos ' . $timeout . ' segundo(s).');
        }
        return $response;
    }

    public function retrieve(string $responseId): array
    {
        $responseId = trim($responseId);
        if ($responseId === '' || strlen($responseId) > 190 || !preg_match('/^[A-Za-z0-9_-]+$/', $responseId)) {
            throw new RuntimeException('Identificador de resposta da OpenAI invalido.');
        }
        return $this->request('GET', self::ENDPOINT . '/' . rawurlencode($responseId));
    }

    private function request(string $method, string $url, ?array $payload = null): array
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

        $body = $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timeout = max(15, min(120, Env::int('OPENAI_TIMEOUT_SECONDS', 90)));
        $maxRetries = max(0, min(3, Env::int('OPENAI_MAX_RETRIES', 2)));

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            $responseHeaders = [];
            $curl = curl_init($url);
            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_HTTPHEADER => $headers,
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
            ];
            if ($method === 'POST') {
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = $body;
            } else {
                $options[CURLOPT_HTTPGET] = true;
            }
            curl_setopt_array($curl, $options);
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

    public static function isPending(array $response): bool
    {
        return in_array((string) ($response['status'] ?? ''), ['queued', 'in_progress'], true);
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

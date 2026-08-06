<?php
declare(strict_types=1);

namespace Refugio\Services;

use PDO;
use Refugio\Repositories\MarketingAiRepository;
use Refugio\Support\Env;
use RuntimeException;

final class MarketingAiAnalysisJobService
{
    public const DEFERRED = 'MARKETING_AI_ANALYSIS_DEFERRED';

    public function __construct(private PDO $db)
    {
    }

    public function process(array $job, JobQueueService $queue): int|string
    {
        $jobId = (int) ($job['id'] ?? 0);
        $payload = is_array($job['payload'] ?? null) ? $job['payload'] : [];
        $start = (string) ($payload['start'] ?? '');
        $end = (string) ($payload['end'] ?? '');
        $filters = is_array($payload['filters'] ?? null) ? $payload['filters'] : [];
        $userId = (int) ($payload['user_id'] ?? 0);
        if ($jobId <= 0 || $start === '' || $end === '' || $userId <= 0) {
            throw new RuntimeException('Payload da analise de marketing com IA invalido.');
        }

        $service = new OpenAiMarketingAnalysisService($this->db);
        $background = is_array($payload['openai_background'] ?? null) ? $payload['openai_background'] : [];
        $responseId = trim((string) ($background['response_id'] ?? ''));
        $context = is_array($background['context'] ?? null) ? $background['context'] : [];
        $startedAt = (int) ($background['started_at'] ?? 0);

        if ($responseId === '') {
            $started = $service->startBackground($start, $end, $filters, $userId);
            $response = $started['response'];
            $context = $started['context'];
            $responseId = trim((string) ($response['id'] ?? ''));
            if ($responseId === '') {
                throw new RuntimeException('A OpenAI nao retornou o identificador da analise em background.');
            }
            $startedAt = time();
            $payload['openai_background'] = [
                'response_id' => $responseId,
                'started_at' => $startedAt,
                'context' => $context,
            ];
        } else {
            $timeout = max(30, min(1800, Env::int('OPENAI_BACKGROUND_TIMEOUT_SECONDS', 600)));
            if ($startedAt <= 0 || time() - $startedAt > $timeout) {
                throw new RuntimeException('A analise em background excedeu o tempo limite de ' . $timeout . ' segundo(s).');
            }
            $response = $service->retrieveBackground($responseId);
        }

        if (OpenAiResponsesClient::isPending($response)) {
            $pollInterval = max(5, min(300, Env::int('OPENAI_BACKGROUND_POLL_SECONDS', 10)));
            $queue->defer($jobId, $payload, $pollInterval);
            return self::DEFERRED;
        }

        $analysisId = $service->completeBackground($response, $context);
        $row = (new MarketingAiRepository($this->db))->find($analysisId);
        (new AuditService($this->db))->record(
            'MARKETING',
            'GERAR_ANALISE_IA',
            'marketing_analises_ia',
            $analysisId,
            null,
            [
                'periodo' => [$start, $end],
                'filtros' => $filters,
                'modelo' => $row['modelo'] ?? Env::get('OPENAI_MARKETING_MODEL', 'gpt-5.6-sol'),
                'input_tokens' => $row['input_tokens'] ?? null,
                'output_tokens' => $row['output_tokens'] ?? null,
            ],
            [],
            $userId
        );
        return $analysisId;
    }
}

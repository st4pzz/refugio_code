<?php
declare(strict_types=1);

namespace Refugio\Services;

use PDO;
use Refugio\Repositories\MarketingAiRepository;
use Refugio\Support\Env;
use RuntimeException;

final class MarketingAiAnalysisJobService
{
    public function __construct(private PDO $db)
    {
    }

    public function process(array $payload): int
    {
        $start = (string) ($payload['start'] ?? '');
        $end = (string) ($payload['end'] ?? '');
        $filters = is_array($payload['filters'] ?? null) ? $payload['filters'] : [];
        $userId = (int) ($payload['user_id'] ?? 0);
        if ($start === '' || $end === '' || $userId <= 0) {
            throw new RuntimeException('Payload da analise de marketing com IA invalido.');
        }

        $analysisId = (new OpenAiMarketingAnalysisService($this->db))->analyze($start, $end, $filters, $userId);
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

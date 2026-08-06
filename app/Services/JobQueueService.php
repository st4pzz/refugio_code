<?php
declare(strict_types=1);

namespace Refugio\Services;

use DateTimeImmutable;
use PDO;
use Throwable;

final class JobQueueService
{
    public function __construct(private PDO $db)
    {
    }

    public function enqueue(string $type, array $payload, ?string $uniqueKey = null, int $priority = 100, int $maxAttempts = 5, ?DateTimeImmutable $availableAt = null): int
    {
        $stmt = $this->db->prepare("INSERT INTO jobs (tipo,payload_json,chave_unica,prioridade,max_tentativas,disponivel_em) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
        $stmt->execute([$type, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $uniqueKey, $priority, $maxAttempts, ($availableAt ?? new DateTimeImmutable())->format('Y-m-d H:i:s')]);
        return (int) $this->db->lastInsertId();
    }

    public function reserve(string $worker): ?array
    {
        $this->db->beginTransaction();
        try {
            $this->db->exec("UPDATE jobs SET status='FALHOU',erro_ultimo=COALESCE(erro_ultimo,'Limite de tentativas atingido.'),finalizado_em=NOW() WHERE status='PENDENTE' AND tentativas>=max_tentativas");
            $job = $this->db->query("SELECT * FROM jobs WHERE status='PENDENTE' AND disponivel_em<=NOW() ORDER BY prioridade,id LIMIT 1 FOR UPDATE")->fetch();
            if (!$job) {
                $this->db->commit();
                return null;
            }
            $stmt = $this->db->prepare("UPDATE jobs SET status='PROCESSANDO',tentativas=tentativas+1,bloqueado_em=NOW(),bloqueado_por=? WHERE id=?");
            $stmt->execute([mb_substr($worker, 0, 100), $job['id']]);
            $this->db->commit();
            $job['tentativas'] = (int) $job['tentativas'] + 1;
            $job['payload'] = json_decode((string) $job['payload_json'], true, 512, JSON_THROW_ON_ERROR);
            return $job;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    public function complete(int $id): void
    {
        $this->db->prepare("UPDATE jobs SET status='CONCLUIDO',finalizado_em=NOW(),bloqueado_em=NULL,bloqueado_por=NULL WHERE id=? AND status='PROCESSANDO'")->execute([$id]);
    }

    public function defer(int $id, array $payload, int $delaySeconds = 10): void
    {
        $delaySeconds = max(5, min(300, $delaySeconds));
        $stmt = $this->db->prepare("UPDATE jobs
            SET status='PENDENTE',payload_json=?,disponivel_em=DATE_ADD(NOW(),INTERVAL ? SECOND),
                tentativas=GREATEST(tentativas-1,0),bloqueado_em=NULL,bloqueado_por=NULL
            WHERE id=? AND status='PROCESSANDO'");
        $stmt->execute([
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $delaySeconds,
            $id,
        ]);
    }

    public function fail(array $job, Throwable|string $error): void
    {
        $message = mb_substr($error instanceof Throwable ? $error->getMessage() : $error, 0, 4000);
        $terminal = (int) $job['tentativas'] >= (int) $job['max_tentativas'];
        $delay = min(3600, 30 * (2 ** max(0, (int) $job['tentativas'] - 1)));
        $stmt = $this->db->prepare("UPDATE jobs SET status=?,erro_ultimo=?,disponivel_em=DATE_ADD(NOW(),INTERVAL ? SECOND),bloqueado_em=NULL,bloqueado_por=NULL,finalizado_em=? WHERE id=?");
        $stmt->execute([$terminal ? 'FALHOU' : 'PENDENTE', $message, $delay, $terminal ? date('Y-m-d H:i:s') : null, $job['id']]);
    }

    public function releaseStale(int $minutes = 15): int
    {
        $minutes = max(5, min(120, $minutes));
        return $this->db->exec("UPDATE jobs
            SET status=IF(tentativas>=max_tentativas,'FALHOU','PENDENTE'),
                erro_ultimo=IF(tentativas>=max_tentativas,COALESCE(erro_ultimo,'Processamento interrompido antes da conclusao.'),erro_ultimo),
                finalizado_em=IF(tentativas>=max_tentativas,NOW(),NULL),
                bloqueado_em=NULL,bloqueado_por=NULL,disponivel_em=NOW()
            WHERE status='PROCESSANDO' AND bloqueado_em<DATE_SUB(NOW(),INTERVAL {$minutes} MINUTE)");
    }

    public function failStaleAiWithoutResponseId(int $minutes = 5): int
    {
        $minutes = max(2, min(15, $minutes));
        return $this->db->exec("UPDATE jobs
            SET status='FALHOU',erro_ultimo='O worker foi interrompido antes de salvar o identificador da resposta da OpenAI.',
                finalizado_em=NOW(),bloqueado_em=NULL,bloqueado_por=NULL
            WHERE tipo='MARKETING_AI_ANALYSIS' AND status='PROCESSANDO'
              AND bloqueado_em<DATE_SUB(NOW(),INTERVAL {$minutes} MINUTE)
              AND JSON_EXTRACT(payload_json,'$.openai_background.response_id') IS NULL");
    }
}

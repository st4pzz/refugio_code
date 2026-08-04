<?php
declare(strict_types=1);

namespace Refugio\Repositories;

use PDO;

final class MarketingAiRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function dataset(string $start, string $end, array $filters): array
    {
        $dashboard = (new MarketingRepository($this->db))->dashboard($start, $end, $filters);

        $campaignWhere = ['1=1'];
        $campaignParams = [$start, $end];
        if (!empty($filters['provider'])) {
            $campaignWhere[] = 'c.provider=?';
            $campaignParams[] = $filters['provider'];
        }
        if (!empty($filters['integracao_id'])) {
            $campaignWhere[] = 'c.integracao_id=?';
            $campaignParams[] = (int) $filters['integracao_id'];
        }
        if (!empty($filters['campanha_id'])) {
            $campaignWhere[] = 'c.id=?';
            $campaignParams[] = (int) $filters['campanha_id'];
        }
        $campaignSql = "SELECT c.id,c.provider,c.nome,c.objetivo,c.status,c.orcamento_diario,c.orcamento_total,c.moeda,i.nome conta,
            COALESCE(SUM(m.gasto),0) gasto,SUM(m.impressoes) impressoes,SUM(m.alcance) alcance,SUM(m.cliques) cliques,
            SUM(m.conversoes) conversoes,SUM(m.leads) leads,SUM(m.valor_conversao) valor_conversao,
            ROUND(SUM(m.cliques)*100/NULLIF(SUM(m.impressoes),0),4) ctr_calculado,
            ROUND(SUM(m.gasto)/NULLIF(SUM(m.cliques),0),4) cpc_calculado,
            ROUND(SUM(m.gasto)*1000/NULLIF(SUM(m.impressoes),0),4) cpm_calculado,
            ROUND(SUM(m.gasto)/NULLIF(SUM(m.leads),0),4) cpl_calculado
            FROM marketing_campanhas c
            JOIN marketing_integracoes i ON i.id=c.integracao_id
            LEFT JOIN marketing_metricas_diarias m ON m.campanha_id=c.id AND m.nivel='CAMPANHA' AND m.data BETWEEN ? AND ?
            WHERE " . implode(' AND ', $campaignWhere) . "
            GROUP BY c.id,c.provider,c.nome,c.objetivo,c.status,c.orcamento_diario,c.orcamento_total,c.moeda,i.nome
            ORDER BY gasto DESC,impressoes DESC,c.id DESC LIMIT 60";
        $campaignStmt = $this->db->prepare($campaignSql);
        $campaignStmt->execute($campaignParams);
        $campaigns = $campaignStmt->fetchAll();

        $creativeWhere = ['1=1'];
        $creativeParams = [];
        if (!empty($filters['provider'])) {
            $creativeWhere[] = 'i.provider=?';
            $creativeParams[] = $filters['provider'];
        }
        if (!empty($filters['integracao_id'])) {
            $creativeWhere[] = 'a.integracao_id=?';
            $creativeParams[] = (int) $filters['integracao_id'];
        }
        if (!empty($filters['campanha_id'])) {
            $creativeWhere[] = 'c.id=?';
            $creativeParams[] = (int) $filters['campanha_id'];
        }
        $creativeSql = "SELECT i.provider,c.nome campanha,g.nome grupo,a.nome anuncio,a.status,a.creative_name,a.creative_url,a.last_synced_at
            FROM marketing_anuncios a
            JOIN marketing_integracoes i ON i.id=a.integracao_id
            LEFT JOIN marketing_grupos_anuncios g ON g.id=a.grupo_anuncio_id
            LEFT JOIN marketing_campanhas c ON c.id=g.campanha_id
            WHERE " . implode(' AND ', $creativeWhere) . "
            ORDER BY a.last_synced_at DESC,a.id DESC LIMIT 80";
        $creativeStmt = $this->db->prepare($creativeSql);
        $creativeStmt->execute($creativeParams);
        $creatives = $creativeStmt->fetchAll();
        foreach ($creatives as &$creative) {
            $creative['creative_url'] = self::safeCreativeUrl($creative['creative_url'] ?? null);
        }
        unset($creative);

        $daily = array_values($dashboard['daily'] ?? []);
        $dailyWasLimited = count($daily) > 120;
        if ($dailyWasLimited) {
            $daily = array_slice($daily, -120);
        }

        return [
            'periodo' => ['inicio' => $start, 'fim' => $end],
            'filtros' => $filters,
            'totais' => $dashboard['metrics'] ?? [],
            'atribuicao' => $dashboard['attribution'] ?? [],
            'canais' => array_values($dashboard['channels'] ?? []),
            'serie_diaria' => $daily,
            'campanhas' => $campaigns,
            'inventario_criativos' => $creatives,
            'qualidade_entrada' => [
                'serie_diaria_limitada' => $dailyWasLimited,
                'observacao_criativos' => 'O inventario de criativos nao possui desempenho individual; as metricas sincronizadas estao no nivel de campanha.',
            ],
            'contagens' => [
                'campanhas' => count($campaigns),
                'criativos' => count($creatives),
                'pontos_serie_diaria' => count($daily),
            ],
        ];
    }

    public function create(array $record): int
    {
        $stmt = $this->db->prepare('INSERT INTO marketing_analises_ia
            (data_inicio,data_fim,filtros_json,entrada_hash,entrada_resumo_json,resposta_json,resumo_executivo,nivel_confianca,modelo,openai_response_id,input_tokens,output_tokens,created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $record['start'],
            $record['end'],
            self::json($record['filters']),
            $record['input_hash'],
            self::json($record['dataset']),
            self::json($record['analysis']),
            $record['analysis']['resumo_executivo'],
            $record['analysis']['nivel_confianca'],
            $record['model'],
            $record['response_id'] ?: null,
            $record['input_tokens'],
            $record['output_tokens'],
            $record['created_by'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function latest(int $limit = 8): array
    {
        $limit = max(1, min(30, $limit));
        return $this->db->query("SELECT id,data_inicio,data_fim,filtros_json,resposta_json,resumo_executivo,nivel_confianca,modelo,input_tokens,output_tokens,created_by,created_at FROM marketing_analises_ia ORDER BY id DESC LIMIT {$limit}")->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM marketing_analises_ia WHERE id=?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function secondsSinceLastByUser(int $userId): ?int
    {
        $stmt = $this->db->prepare('SELECT TIMESTAMPDIFF(SECOND,created_at,NOW()) FROM marketing_analises_ia WHERE created_by=? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$userId]);
        $seconds = $stmt->fetchColumn();
        return $seconds === false ? null : (int) $seconds;
    }

    public static function decode(array $row): array
    {
        $row['filters'] = json_decode((string) ($row['filtros_json'] ?? '{}'), true) ?: [];
        $row['analysis'] = json_decode((string) ($row['resposta_json'] ?? '{}'), true) ?: [];
        unset($row['filtros_json'], $row['resposta_json'], $row['entrada_resumo_json']);
        return $row;
    }

    private static function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function safeCreativeUrl(mixed $value): ?string
    {
        $url = trim((string) $value);
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true) || empty($parts['host'])) {
            return null;
        }
        $safe = strtolower((string) $parts['scheme']) . '://' . $parts['host'] . ($parts['path'] ?? '');
        return mb_substr($safe, 0, 1000);
    }
}

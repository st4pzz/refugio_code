<?php
declare(strict_types=1);

namespace Refugio\Services;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;
use PDO;
use PDOException;
use Refugio\Marketing\AbstractAdsProvider;
use Refugio\Support\Env;
use RuntimeException;
use Throwable;

final class GoogleAdsScriptImportService
{
    private const DEFAULT_CAMPAIGNS = ['Leads-Search-1', 'Leads-Performance Max-2'];

    public function __construct(private PDO $db)
    {
    }

    public static function validSignature(
        string $rawBody,
        string $timestamp,
        string $signature,
        string $secret,
        ?int $now = null,
        int $maxAgeSeconds = 300
    ): bool {
        if (strlen($secret) < 32 || !preg_match('/^\d{10}$/', $timestamp)) {
            return false;
        }
        $now ??= time();
        if (abs($now - (int) $timestamp) > max(30, $maxAgeSeconds)) {
            return false;
        }
        $expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
        return hash_equals($expected, strtolower(trim($signature)));
    }

    public static function campaignNames(?string $configured = null): array
    {
        $configured ??= Env::get('GOOGLE_ADS_SCRIPT_CAMPAIGNS');
        $names = array_values(array_unique(array_filter(
            array_map('trim', explode(',', $configured)),
            static fn(string $name): bool => $name !== '' && mb_strlen($name) <= 255
        )));
        return $names ?: self::DEFAULT_CAMPAIGNS;
    }

    /** @throws JsonException */
    public function import(string $rawBody, string $sourceIp = ''): array
    {
        $payload = json_decode($rawBody, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            throw new InvalidArgumentException('O payload precisa ser um objeto JSON.');
        }
        $data = $this->normalize($payload);
        $payloadHash = hash('sha256', $rawBody);

        $this->db->beginTransaction();
        try {
            $integrationId = $this->upsertIntegration($data['account']);
            try {
                $request = $this->db->prepare('INSERT INTO marketing_google_ads_script_imports
                    (integracao_id,request_id,payload_sha256,data_inicio,data_fim,campanhas_processadas,metricas_processadas,source_ip)
                    VALUES (?,?,?,?,?,?,?,?)');
                $request->execute([
                    $integrationId,
                    $data['request_id'],
                    $payloadHash,
                    $data['start'],
                    $data['end'],
                    count($data['campaigns']),
                    count($data['metrics']),
                    $sourceIp !== '' ? mb_substr($sourceIp, 0, 45) : null,
                ]);
            } catch (PDOException $error) {
                if ((string) $error->getCode() === '23000' && (int) ($error->errorInfo[1] ?? 0) === 1062) {
                    $this->db->rollBack();
                    $existing = $this->db->prepare('SELECT payload_sha256 FROM marketing_google_ads_script_imports WHERE request_id=?');
                    $existing->execute([$data['request_id']]);
                    $existingHash = $existing->fetchColumn();
                    if (!is_string($existingHash) || !hash_equals($existingHash, $payloadHash)) {
                        throw new InvalidArgumentException('request_id ja utilizado com outro payload.');
                    }
                    return ['accepted' => true, 'duplicate' => true, 'request_id' => $data['request_id']];
                }
                throw $error;
            }

            $stats = ['processed' => 0, 'created' => 0, 'updated' => 0];
            foreach ($data['campaigns'] as $campaign) {
                $this->upsertCampaign($integrationId, $data['account'], $campaign, $stats);
            }
            foreach ($data['metrics'] as $metric) {
                $this->upsertMetric($integrationId, $data['account'], $metric, $stats);
            }

            $sync = $this->db->prepare("INSERT INTO marketing_sincronizacoes
                (integracao_id,tipo,data_inicio,data_fim,status,registros_processados,registros_criados,registros_atualizados,iniciada_em,finalizada_em)
                VALUES (?,?,?,?, 'CONCLUIDA',?,?,?,NOW(),NOW())");
            $sync->execute([$integrationId, 'GOOGLE_ADS_SCRIPT', $data['start'], $data['end'], $stats['processed'], $stats['created'], $stats['updated']]);
            $this->db->prepare("UPDATE marketing_integracoes SET status='CONECTADA',ultima_sincronizacao_em=NOW(),erro_ultima_sincronizacao=NULL WHERE id=?")
                ->execute([$integrationId]);
            $this->db->commit();

            return [
                'accepted' => true,
                'duplicate' => false,
                'request_id' => $data['request_id'],
                'integration_id' => $integrationId,
                'campaigns' => count($data['campaigns']),
                'metrics' => count($data['metrics']),
            ];
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    private function normalize(array $payload): array
    {
        if (($payload['schema_version'] ?? null) !== 1) {
            throw new InvalidArgumentException('Versao de payload nao suportada.');
        }
        $requestId = trim((string) ($payload['request_id'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9_-]{16,100}$/', $requestId)) {
            throw new InvalidArgumentException('request_id invalido.');
        }
        $accountInput = is_array($payload['account'] ?? null) ? $payload['account'] : [];
        $customerId = preg_replace('/\D/', '', (string) ($accountInput['customer_id'] ?? ''));
        if (!is_string($customerId) || !preg_match('/^\d{6,20}$/', $customerId)) {
            throw new InvalidArgumentException('Conta Google Ads invalida.');
        }
        $currency = strtoupper(trim((string) ($accountInput['currency_code'] ?? '')));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Moeda da conta invalida.');
        }
        $account = [
            'customer_id' => $customerId,
            'name' => $this->text($accountInput['name'] ?? ('Google Ads ' . $customerId), 120, 'Nome da conta invalido.'),
            'currency' => $currency,
            'timezone' => $this->text($accountInput['time_zone'] ?? 'America/Sao_Paulo', 80, 'Timezone da conta invalido.'),
        ];

        $allowedNames = self::campaignNames();
        $campaignInput = $payload['campaigns'] ?? null;
        if (!is_array($campaignInput) || $campaignInput === [] || count($campaignInput) > 20) {
            throw new InvalidArgumentException('Lista de campanhas invalida.');
        }
        $campaigns = [];
        $campaignIds = [];
        foreach ($campaignInput as $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Campanha invalida.');
            }
            $id = $this->digits($item['id'] ?? null, 190, 'ID de campanha invalido.');
            $name = $this->text($item['name'] ?? '', 255, 'Nome de campanha invalido.');
            if (!in_array($name, $allowedNames, true)) {
                throw new InvalidArgumentException('Campanha nao autorizada pelo servidor: ' . $name);
            }
            if (isset($campaignIds[$id])) {
                throw new InvalidArgumentException('Campanha duplicada no payload.');
            }
            $campaignIds[$id] = true;
            $campaigns[] = [
                'id' => $id,
                'name' => $name,
                'status' => $this->optionalText($item['status'] ?? null, 80),
                'channel_type' => $this->optionalText($item['advertising_channel_type'] ?? null, 120),
                'daily_budget' => $this->micros($item['daily_budget_micros'] ?? null),
            ];
        }

        $metricInput = $payload['metrics'] ?? null;
        $maxRows = max(1, min(10000, Env::int('GOOGLE_ADS_SCRIPT_MAX_ROWS', 2000)));
        if (!is_array($metricInput) || count($metricInput) > $maxRows) {
            throw new InvalidArgumentException('Lista de metricas invalida ou acima do limite.');
        }
        $metrics = [];
        $metricKeys = [];
        $dates = [];
        foreach ($metricInput as $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Metrica invalida.');
            }
            $campaignId = $this->digits($item['campaign_id'] ?? null, 190, 'ID da metrica invalido.');
            if (!isset($campaignIds[$campaignId])) {
                throw new InvalidArgumentException('Metrica sem campanha autorizada.');
            }
            $date = $this->date($item['date'] ?? null);
            $key = $campaignId . '|' . $date;
            if (isset($metricKeys[$key])) {
                throw new InvalidArgumentException('Metrica diaria duplicada no payload.');
            }
            $metricKeys[$key] = true;
            $dates[] = $date;
            $conversions = $this->decimal($item['conversions'] ?? 0, 4, 'Conversoes invalidas.');
            $metrics[] = [
                'campaign_id' => $campaignId,
                'date' => $date,
                'spend' => $this->micros($item['cost_micros'] ?? 0) ?? '0',
                'impressions' => $this->unsignedInteger($item['impressions'] ?? 0, 'Impressoes invalidas.'),
                'clicks' => $this->unsignedInteger($item['clicks'] ?? 0, 'Cliques invalidos.'),
                'conversions' => $conversions,
                'all_conversions' => $this->decimal($item['all_conversions'] ?? $conversions, 4, 'Todas as conversoes invalidas.'),
                'conversion_value' => $this->decimal($item['conversions_value'] ?? 0, 2, 'Valor de conversao invalido.'),
            ];
        }
        sort($dates);

        return [
            'request_id' => $requestId,
            'account' => $account,
            'campaigns' => $campaigns,
            'metrics' => $metrics,
            'start' => $dates[0] ?? null,
            'end' => $dates ? $dates[array_key_last($dates)] : null,
        ];
    }

    private function upsertIntegration(array $account): int
    {
        $config = json_encode([
            'connection_mode' => 'GOOGLE_ADS_SCRIPT',
            'google_ads_script' => true,
            'campaign_names' => self::campaignNames(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $stmt = $this->db->prepare("INSERT INTO marketing_integracoes
            (provider,nome,conta_externa_id,status,moeda,timezone,config_json)
            VALUES ('GOOGLE',?,?, 'CONECTADA',?,?,?)
            ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),nome=VALUES(nome),moeda=VALUES(moeda),timezone=VALUES(timezone),
                status='CONECTADA',config_json=JSON_MERGE_PATCH(COALESCE(config_json,JSON_OBJECT()),VALUES(config_json)),erro_ultima_sincronizacao=NULL");
        $stmt->execute([$account['name'], $account['customer_id'], $account['currency'], $account['timezone'], $config]);
        $id = (int) $this->db->lastInsertId();
        if ($id <= 0) {
            throw new RuntimeException('Nao foi possivel identificar a integracao Google Ads.');
        }
        return $id;
    }

    private function upsertCampaign(int $integrationId, array $account, array $campaign, array &$stats): void
    {
        $extra = json_encode(['source' => 'GOOGLE_ADS_SCRIPT'], JSON_THROW_ON_ERROR);
        $stmt = $this->db->prepare('INSERT INTO marketing_campanhas
            (integracao_id,provider,external_id,nome,objetivo,status,orcamento_diario,moeda,dados_extras_json,last_synced_at)
            VALUES (?,\'GOOGLE\',?,?,?,?,?,?,?,NOW())
            ON DUPLICATE KEY UPDATE nome=VALUES(nome),objetivo=VALUES(objetivo),status=VALUES(status),orcamento_diario=VALUES(orcamento_diario),
                moeda=VALUES(moeda),dados_extras_json=VALUES(dados_extras_json),last_synced_at=NOW()');
        $stmt->execute([$integrationId, $campaign['id'], $campaign['name'], $campaign['channel_type'], $campaign['status'], $campaign['daily_budget'], $account['currency'], $extra]);
        $this->stat($stmt, $stats);
    }

    private function upsertMetric(int $integrationId, array $account, array $metric, array &$stats): void
    {
        $campaign = $this->db->prepare('SELECT id FROM marketing_campanhas WHERE integracao_id=? AND external_id=?');
        $campaign->execute([$integrationId, $metric['campaign_id']]);
        $campaignId = $campaign->fetchColumn();
        if ($campaignId === false) {
            throw new RuntimeException('Campanha da metrica nao foi encontrada.');
        }
        $extra = json_encode(['source' => 'GOOGLE_ADS_SCRIPT', 'all_conversions' => $metric['all_conversions']], JSON_THROW_ON_ERROR);
        $stmt = $this->db->prepare('INSERT INTO marketing_metricas_diarias
            (integracao_id,campanha_id,provider,nivel,external_entity_id,data,moeda,gasto,impressoes,cliques,conversoes,leads,valor_conversao,dados_extras_json)
            VALUES (?,?,\'GOOGLE\',\'CAMPANHA\',?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE campanha_id=VALUES(campanha_id),moeda=VALUES(moeda),gasto=VALUES(gasto),impressoes=VALUES(impressoes),
                cliques=VALUES(cliques),conversoes=VALUES(conversoes),leads=VALUES(leads),valor_conversao=VALUES(valor_conversao),
                dados_extras_json=VALUES(dados_extras_json),updated_at=NOW()');
        $stmt->execute([
            $integrationId,
            (int) $campaignId,
            $metric['campaign_id'],
            $metric['date'],
            $account['currency'],
            $metric['spend'],
            $metric['impressions'],
            $metric['clicks'],
            $metric['conversions'],
            $metric['conversions'],
            $metric['conversion_value'],
            $extra,
        ]);
        $this->stat($stmt, $stats);
    }

    private function stat(\PDOStatement $stmt, array &$stats): void
    {
        $stats['processed']++;
        if ($stmt->rowCount() === 1) {
            $stats['created']++;
        } else {
            $stats['updated']++;
        }
    }

    private function date(mixed $value): string
    {
        $text = (string) $value;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $text);
        if (!$date || $date->format('Y-m-d') !== $text) {
            throw new InvalidArgumentException('Data de metrica invalida.');
        }
        return $text;
    }

    private function micros(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $digits = (string) $value;
        if (!preg_match('/^\d{1,20}$/', $digits)) {
            throw new InvalidArgumentException('Valor em micros invalido.');
        }
        return AbstractAdsProvider::microsToDecimal($digits);
    }

    private function decimal(mixed $value, int $scale, string $message): string
    {
        $text = (string) $value;
        if (!preg_match('/^\d{1,16}(?:\.\d{1,' . $scale . '})?$/', $text)) {
            throw new InvalidArgumentException($message);
        }
        return $text;
    }

    private function unsignedInteger(mixed $value, string $message): string
    {
        $digits = (string) $value;
        if (!preg_match('/^\d{1,20}$/', $digits)) {
            throw new InvalidArgumentException($message);
        }
        return $digits;
    }

    private function digits(mixed $value, int $maxLength, string $message): string
    {
        $digits = (string) $value;
        if ($digits === '' || strlen($digits) > $maxLength || !ctype_digit($digits)) {
            throw new InvalidArgumentException($message);
        }
        return $digits;
    }

    private function text(mixed $value, int $maxLength, string $message): string
    {
        $text = trim((string) $value);
        if ($text === '' || mb_strlen($text) > $maxLength || preg_match('/[\x00-\x1F\x7F]/u', $text)) {
            throw new InvalidArgumentException($message);
        }
        return $text;
    }

    private function optionalText(mixed $value, int $maxLength): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        return $this->text($value, $maxLength, 'Texto de campanha invalido.');
    }
}

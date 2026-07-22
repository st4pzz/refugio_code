<?php
declare(strict_types=1);

namespace Refugio\Services;

use PDO;
use RuntimeException;

final class PropertySettingsService
{
    public const REQUIRED_FOR_PUBLIC_PRICING = [
        'GUESTS_INCLUDED_IN_BASE_RATE',
        'EXTRA_GUEST_FEE_MODE',
    ];

    public const REQUIRED_FOR_CONTRACT = [
        'PROPERTY_NAME', 'PROPERTY_CITY', 'PROPERTY_STATE', 'DEFAULT_CHECKIN_TIME', 'DEFAULT_CHECKOUT_TIME',
        'QUIET_HOURS', 'MINIMUM_NIGHTS', 'MAXIMUM_NIGHTS', 'OWNER_FULL_NAME', 'OWNER_CPF',
        'OWNER_ADDRESS', 'PROPERTY_FULL_ADDRESS', 'CONTRACT_CITY', 'CONTRACT_FORUM_CITY',
        'OWNER_NATIONALITY','OWNER_MARITAL_STATUS','OWNER_PROFESSION','OWNER_RG','OWNER_PHONE','OWNER_EMAIL',
        'EMERGENCY_CONTACT','UNAUTHORIZED_VISITOR_FEE','PAYMENT_METHOD','CANCELLATION_POLICY_APPROVED',
    ];

    public function __construct(private PDO $db)
    {
    }

    public function all(string $namespace = 'property'): array
    {
        $stmt = $this->db->prepare('SELECT chave,valor_json,updated_at FROM configuracoes_sistema WHERE namespace=? ORDER BY chave');
        $stmt->execute([$namespace]);
        $settings = [];
        foreach ($stmt->fetchAll() as $row) {
            $decoded = json_decode((string) $row['valor_json'], true);
            $settings[(string) $row['chave']] = is_array($decoded)
                ? $decoded + ['updated_at' => $row['updated_at']]
                : ['value' => $decoded, 'configured' => $decoded !== null, 'updated_at' => $row['updated_at']];
        }
        return $settings;
    }

    public function values(string $namespace = 'property'): array
    {
        $values = [];
        foreach ($this->all($namespace) as $key => $setting) {
            $values[$key] = $setting['value'] ?? null;
        }
        return $values;
    }

    public function get(string $key, mixed $default = null, string $namespace = 'property'): mixed
    {
        $stmt = $this->db->prepare('SELECT valor_json FROM configuracoes_sistema WHERE namespace=? AND chave=?');
        $stmt->execute([$namespace, $key]);
        $raw = $stmt->fetchColumn();
        if ($raw === false) {
            return $default;
        }
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) && array_key_exists('value', $decoded) ? $decoded['value'] : $decoded;
    }

    public function missing(array $keys, string $namespace = 'property'): array
    {
        $all = $this->all($namespace);
        return array_values(array_filter($keys, static function (string $key) use ($all): bool {
            if (!isset($all[$key])) {
                return true;
            }
            $value = $all[$key]['value'] ?? null;
            return ($all[$key]['configured'] ?? $value !== null) !== true || $value === null || $value === '';
        }));
    }

    public function publicPricingReadiness(): array
    {
        $pricing = $this->db->query('SELECT * FROM property_pricing_settings WHERE id=1')->fetch() ?: [];
        $missing = [];
        if ($pricing === [] || $pricing['guests_included_in_base_rate'] === null) {
            $missing[] = 'GUESTS_INCLUDED_IN_BASE_RATE';
        }
        if ($pricing === [] || $pricing['extra_guest_fee_mode'] === null || $pricing['extra_guest_fee_mode'] === '') {
            $missing[] = 'EXTRA_GUEST_FEE_MODE';
        }
        return [
            'ready' => $missing === [] && (int) ($pricing['public_pricing_enabled'] ?? 0) === 1,
            'configured' => $missing === [],
            'enabled' => (int) ($pricing['public_pricing_enabled'] ?? 0) === 1,
            'missing' => $missing,
        ];
    }

    public function update(array $input, int $userId): void
    {
        $allowed = $this->all('property');
        $this->db->beginTransaction();
        try {
            foreach ($allowed as $key => $metadata) {
                if (!array_key_exists($key, $input)) {
                    continue;
                }
                $value = $this->normalize($input[$key], (string) ($metadata['type'] ?? 'string'), $key);
                $metadata['value'] = $value;
                $metadata['configured'] = $value !== null && $value !== '';
                unset($metadata['updated_at']);
                $stmt = $this->db->prepare('UPDATE configuracoes_sistema SET valor_json=?,atualizado_por=? WHERE namespace=\'property\' AND chave=?');
                $stmt->execute([json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $userId, $key]);
            }
            $this->db->commit();
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    private function normalize(mixed $value, string $type, string $key): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
        }
        if ($value === '' || $value === null) {
            return null;
        }
        return match ($type) {
            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false && (int) $value >= 0
                ? (int) $value : throw new RuntimeException("{$key}: informe um número inteiro válido."),
            'boolean' => in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true),
            'money' => is_numeric(str_replace(',', '.', (string) $value)) && (float) str_replace(',', '.', (string) $value) >= 0
                ? number_format((float) str_replace(',', '.', (string) $value), 2, '.', '')
                : throw new RuntimeException("{$key}: informe um valor monetário válido."),
            'time' => preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string) $value) ? (string) $value
                : throw new RuntimeException("{$key}: use o formato HH:MM."),
            'timezone' => in_array((string) $value, timezone_identifiers_list(), true) ? (string) $value
                : throw new RuntimeException("{$key}: timezone inválido."),
            'currency' => preg_match('/^[A-Z]{3}$/', (string) $value) ? (string) $value
                : throw new RuntimeException("{$key}: moeda inválida."),
            default => mb_substr(strip_tags((string) $value), 0, 2000),
        };
    }
}

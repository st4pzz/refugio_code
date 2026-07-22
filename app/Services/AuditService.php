<?php
declare(strict_types=1);

namespace Refugio\Services;

use PDO;
use Refugio\Support\Security;

final class AuditService
{
    public function __construct(private PDO $db)
    {
    }

    public function record(string $module, string $action, ?string $entity = null, int|string|null $entityId = null, ?array $before = null, ?array $after = null, array $metadata = [], ?int $userId = null): void
    {
        $correlationId = self::correlationId();
        $stmt = $this->db->prepare('INSERT INTO auditoria (usuario_id,modulo,acao,entidade,entidade_id,antes_json,depois_json,metadados_json,ip,user_agent,correlation_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $userId ?? (!empty($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null),
            mb_substr($module, 0, 40),
            mb_substr($action, 0, 80),
            $entity !== null ? mb_substr($entity, 0, 80) : null,
            $entityId !== null ? mb_substr((string) $entityId, 0, 80) : null,
            $before !== null ? self::json(self::sanitize($before)) : null,
            $after !== null ? self::json(self::sanitize($after)) : null,
            $metadata ? self::json(self::sanitize($metadata)) : null,
            Security::clientIp(),
            mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'cli'), 0, 255),
            $correlationId,
        ]);
    }

    public static function correlationId(): string
    {
        static $id;
        return $id ??= bin2hex(random_bytes(16));
    }

    public static function sanitize(array $data): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            $name = strtolower((string) $key);
            if (preg_match('/token|secret|password|senha|authorization|cookie|app_key|smtp/i', $name)) {
                $clean[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $clean[$key] = self::sanitize($value);
            } elseif (is_string($value) && strlen($value) > 4000) {
                $clean[$key] = substr($value, 0, 4000) . '[truncated]';
            } else {
                $clean[$key] = $value;
            }
        }
        return $clean;
    }

    private static function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}

<?php
declare(strict_types=1);

namespace Refugio\Services;

use PDO;
use Refugio\Config\Database;

final class AuthorizationService
{
    private ?array $permissions = null;

    public function __construct(private PDO $db, private int $userId)
    {
    }

    public function permissions(): array
    {
        if ($this->permissions !== null) {
            return $this->permissions;
        }
        $stmt = $this->db->prepare('SELECT p.codigo,p.permissoes_json FROM usuarios_admin_perfis up JOIN perfis_admin p ON p.id=up.perfil_id WHERE up.usuario_id=?');
        $stmt->execute([$this->userId]);
        $permissions = [];
        $roles = [];
        foreach ($stmt->fetchAll() as $row) {
            $roles[] = (string) $row['codigo'];
            $items = json_decode((string) $row['permissoes_json'], true);
            if (is_array($items)) {
                foreach ($items as $permission) {
                    if (is_string($permission) && $permission !== '') {
                        $permissions[$permission] = true;
                    }
                }
            }
        }
        $this->permissions = array_keys($permissions);
        if ($this->userId === (int) ($_SESSION['admin_id'] ?? 0)) {
            $_SESSION['admin_permissions'] = $this->permissions;
            $_SESSION['admin_roles'] = $roles;
        }
        return $this->permissions;
    }

    public function allows(string $permission): bool
    {
        foreach ($this->permissions() as $granted) {
            if (self::permissionMatches($granted, $permission)) {
                return true;
            }
        }
        return false;
    }

    public static function permissionMatches(string $granted, string $requested): bool
    {
        return $granted === '*' || $granted === $requested || (str_ends_with($granted, '.*') && str_starts_with($requested, substr($granted, 0, -1)));
    }

    public function require(string $permission): void
    {
        if (!$this->allows($permission)) {
            self::deny();
        }
    }

    public static function forCurrentUser(): self
    {
        AuthService::requireAdmin();
        return new self(Database::connection(), (int) $_SESSION['admin_id']);
    }

    public static function requirePermission(string $permission): void
    {
        self::forCurrentUser()->require($permission);
    }

    public static function currentAllows(string $permission): bool
    {
        if (empty($_SESSION['admin_id'])) {
            return false;
        }
        $permissions = $_SESSION['admin_permissions'] ?? null;
        if (!is_array($permissions)) {
            try {
                $permissions = self::forCurrentUser()->permissions();
            } catch (\Throwable) {
                return false;
            }
        }
        foreach ($permissions as $granted) {
            if (is_string($granted) && self::permissionMatches($granted, $permission)) {
                return true;
            }
        }
        return false;
    }

    private static function deny(): never
    {
        http_response_code(403);
        $title = 'Acesso negado';
        require BASE_PATH . '/app/Views/admin/forbidden.php';
        exit;
    }
}

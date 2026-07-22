<?php
declare(strict_types=1);

namespace Refugio\Services;

use PDO;
use Refugio\Support\Security;

final class AuthService
{
    public function __construct(private PDO $db) {}

    public function attempt(string $email, string $password): bool
    {
        $identity = 'login|' . Security::clientIp() . '|' . strtolower($email);
        $limiter = new RateLimiter($this->db);
        $limiter->assertAllowed($identity, 8, 900);
        $stmt = $this->db->prepare('SELECT * FROM usuarios_admin WHERE email=? AND ativo=1 LIMIT 1');
        $stmt->execute([strtolower(trim($email))]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['senha_hash'])) return false;
        if (password_needs_rehash($user['senha_hash'], PASSWORD_DEFAULT)) {
            $this->db->prepare('UPDATE usuarios_admin SET senha_hash=? WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $user['id'];
        $_SESSION['admin_name'] = $user['nome'];
        $authorization = new AuthorizationService($this->db, (int) $user['id']);
        if ($authorization->permissions() === []) {
            self::logout();
            return false;
        }
        $limiter->clear($identity);
        $this->db->prepare('UPDATE usuarios_admin SET ultimo_login_em=NOW() WHERE id=?')->execute([$user['id']]);
        return true;
    }

    public static function requireAdmin(): void
    {
        if (empty($_SESSION['admin_id'])) redirect(base_url('admin/login.php'));
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}

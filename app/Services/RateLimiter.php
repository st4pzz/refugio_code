<?php
declare(strict_types=1);

namespace Refugio\Services;

use PDO;
use Refugio\Support\Env;
use RuntimeException;

final class RateLimiter
{
    public function __construct(private PDO $db) {}

    public function assertAllowed(string $identity, int $limit, int $windowSeconds): void
    {
        if (random_int(1, 100) === 1) {
            $this->db->exec('DELETE FROM rate_limits WHERE updated_at < DATE_SUB(NOW(), INTERVAL 2 DAY)');
        }
        $key = $this->key($identity);
        $sql = "INSERT INTO rate_limits (chave, tentativas, janela_inicio) VALUES (?, 1, NOW())
            ON DUPLICATE KEY UPDATE
            tentativas = IF(janela_inicio < DATE_SUB(NOW(), INTERVAL ? SECOND), 1, tentativas + 1),
            janela_inicio = IF(janela_inicio < DATE_SUB(NOW(), INTERVAL ? SECOND), NOW(), janela_inicio)";
        $this->db->prepare($sql)->execute([$key, $windowSeconds, $windowSeconds]);
        $stmt = $this->db->prepare('SELECT tentativas FROM rate_limits WHERE chave = ?');
        $stmt->execute([$key]);
        if ((int) $stmt->fetchColumn() > $limit) {
            throw new RuntimeException('Muitas tentativas. Aguarde alguns minutos antes de tentar novamente.');
        }
    }

    public function clear(string $identity): void
    {
        $this->db->prepare('DELETE FROM rate_limits WHERE chave=?')->execute([$this->key($identity)]);
    }

    private function key(string $identity): string
    {
        $secret = Env::get('APP_KEY', 'refugio-rate-limit');
        return hash_hmac('sha256', $identity, $secret);
    }
}

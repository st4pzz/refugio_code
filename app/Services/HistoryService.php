<?php
declare(strict_types=1);

namespace Refugio\Services;

use PDO;
use Refugio\Support\Security;

final class HistoryService
{
    public function __construct(private PDO $db) {}

    public function log(int $reservationId, string $action, ?string $before, ?string $after, array $details = [], ?int $userId = null): void
    {
        $stmt = $this->db->prepare('INSERT INTO historico_reserva (reserva_id,usuario_id,acao,status_anterior,status_novo,detalhes,ip) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([$reservationId, $userId, $action, $before, $after, $details ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null, Security::clientIp()]);
    }
}

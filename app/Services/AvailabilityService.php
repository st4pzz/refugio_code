<?php
declare(strict_types=1);

namespace Refugio\Services;

use PDO;
use Refugio\Models\ReservationStatus;

final class AvailabilityService
{
    public function __construct(private PDO $db) {}

    public function lockApprovalMutex(): void
    {
        $this->db->query('SELECT id FROM reserva_mutex WHERE id=1 FOR UPDATE')->fetchColumn();
    }

    public static function overlaps(string $existingCheckin, string $existingCheckout, string $newCheckin, string $newCheckout): bool
    {
        return $existingCheckin < $newCheckout && $existingCheckout > $newCheckin;
    }

    public function conflicts(string $checkin, string $checkout, ?int $excludeReservationId = null, bool $forUpdate = false): array
    {
        $statuses = ReservationStatus::blocking();
        $marks = implode(',', array_fill(0, count($statuses), '?'));
        $params = [$checkout, $checkin, ...$statuses];
        $exclude = '';
        if ($excludeReservationId) { $exclude = ' AND id <> ?'; $params[] = $excludeReservationId; }
        $lock = $forUpdate ? ' FOR UPDATE' : '';
        $stmt = $this->db->prepare("SELECT id,codigo,nome_cliente,checkin,checkout,status FROM reservas WHERE checkin < ? AND checkout > ? AND status IN ({$marks}){$exclude}{$lock}");
        $stmt->execute($params);
        $reservations = $stmt->fetchAll();

        $params = [$checkout, $checkin];
        $exclude = '';
        if ($excludeReservationId) { $exclude = ' AND (reserva_id IS NULL OR reserva_id <> ?)'; $params[] = $excludeReservationId; }
        $stmt = $this->db->prepare("SELECT id,data_inicio checkin,data_fim checkout,motivo,origem FROM datas_bloqueadas WHERE data_inicio < ? AND data_fim > ?{$exclude}{$lock}");
        $stmt->execute($params);
        return ['reservas' => $reservations, 'bloqueios' => $stmt->fetchAll()];
    }

    public function pendingConflicts(string $checkin, string $checkout, int $exclude): array
    {
        $stmt = $this->db->prepare("SELECT id,codigo,nome_cliente,checkin,checkout FROM reservas WHERE id<>? AND checkin < ? AND checkout > ? AND status='AGUARDANDO_APROVACAO'");
        $stmt->execute([$exclude, $checkout, $checkin]);
        return $stmt->fetchAll();
    }
}

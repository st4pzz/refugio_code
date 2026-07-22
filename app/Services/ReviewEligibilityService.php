<?php
declare(strict_types=1);

namespace Refugio\Services;

use DateTimeImmutable;
use PDO;

final class ReviewEligibilityService
{
    public function __construct(private PDO $db, private array $config) {}

    public function check(array $reservation, ?array $existingReview = null, ?DateTimeImmutable $now = null): array
    {
        $payment = $this->db->prepare("SELECT 1 FROM pagamentos WHERE reserva_id=? AND status='CONFIRMADO' LIMIT 1");
        $payment->execute([$reservation['id']]);
        return self::evaluate($reservation, (bool) $payment->fetchColumn(), $existingReview !== null, $now ?? new DateTimeImmutable());
    }

    public static function evaluate(array $reservation, bool $hasConfirmedPayment, bool $hasReview, DateTimeImmutable $now): array
    {
        $errors = [];
        $status = (string) ($reservation['status'] ?? '');
        $checkout = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($reservation['checkout'] ?? ''));
        if (!in_array($status, ['FINALIZADA','RESERVA_CONFIRMADA'], true)) $errors[] = 'A hospedagem nao foi concluida.';
        if (!$checkout || $checkout >= new DateTimeImmutable($now->format('Y-m-d'))) $errors[] = 'O check-out ainda nao passou.';
        $externalValidated = in_array((string) ($reservation['origem'] ?? ''), ['AIRBNB','BOOKING','MANUAL'], true) && $status === 'FINALIZADA';
        if (!$hasConfirmedPayment && !$externalValidated) $errors[] = 'O pagamento nao foi confirmado.';
        if ($hasReview) $errors[] = 'Esta reserva ja possui avaliacao.';
        return ['eligible' => $errors === [], 'errors' => $errors];
    }

    public function invitationWindow(array $reservation, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable();
        $checkout = new DateTimeImmutable($reservation['checkout'] . ' 00:00:00');
        $availableAt = $checkout->modify('+' . (int) $this->config['review_delay_hours'] . ' hours');
        $expiresAt = $checkout->modify('+' . (int) $this->config['review_expiration_days'] . ' days')->setTime(23, 59, 59);
        return ['available' => $now >= $availableAt && $now < $expiresAt, 'available_at' => $availableAt, 'expires_at' => $expiresAt];
    }
}

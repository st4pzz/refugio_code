<?php
declare(strict_types=1);

namespace Refugio\Services;

use DateTimeImmutable;
use PDO;
use Refugio\Models\ReviewInvitationStatus;
use Refugio\Repositories\ReviewRepository;
use RuntimeException;
use Throwable;

final class ReviewInviteService
{
    private ReviewRepository $repository;
    private HistoryService $history;
    private NotificationService $notifications;
    private ReviewEligibilityService $eligibility;

    public function __construct(private PDO $db, private array $config)
    {
        $this->repository = new ReviewRepository($db);
        $this->history = new HistoryService($db);
        $this->notifications = new NotificationService($db);
        $this->eligibility = new ReviewEligibilityService($db, $config);
    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function shouldSkipExistingInvite(?array $invite, bool $force, ?DateTimeImmutable $now = null): bool
    {
        if ($force || !$invite) return false;
        $now ??= new DateTimeImmutable();
        $expiresAt = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', (string) ($invite['expira_em'] ?? ''));
        if ($expiresAt === false || $expiresAt <= $now) return false;
        if (($invite['status'] ?? '') === ReviewInvitationStatus::ENVIADO->value) return true;
        if (($invite['status'] ?? '') !== ReviewInvitationStatus::PENDENTE->value) return false;
        $updatedAt = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', (string) ($invite['updated_at'] ?? ''));
        return $updatedAt !== false && $updatedAt >= $now->modify('-10 minutes');
    }

    public function send(int $reservationId, bool $force = false, bool $reminder = false, ?int $userId = null): array
    {
        $this->db->beginTransaction();
        try {
            $reservation = $this->repository->reservation($reservationId, true) ?? throw new RuntimeException('Reserva nao encontrada.');
            $review = $this->repository->reviewByReservation($reservationId);
            $eligibility = $this->eligibility->check($reservation, $review);
            if (!$eligibility['eligible']) throw new RuntimeException(implode(' ', $eligibility['errors']));
            $window = $this->eligibility->invitationWindow($reservation);
            if (!$window['available']) throw new RuntimeException('A reserva esta fora da janela configurada para avaliacao.');
            $existing = $this->repository->invitationByReservation($reservationId, true);
            if ($existing && $existing['status'] === ReviewInvitationStatus::UTILIZADO->value) throw new ReviewAccessException(true);
            if ($reminder && (!$existing || $existing['status'] !== ReviewInvitationStatus::ENVIADO->value || $existing['lembrete_enviado_em'])) throw new RuntimeException('Esta reserva nao esta elegivel para lembrete.');
            if (self::shouldSkipExistingInvite($existing, $force)) {
                $this->db->commit();
                return ['skipped' => true, 'reason' => 'Convite válido já enviado ou em processamento.'];
            }
            $rawToken = self::generateToken();
            $inviteId = $this->repository->saveInvitation($reservationId, self::hashToken($rawToken), $window['expires_at']->format('Y-m-d H:i:s'));
            $event = $existing ? 'CONVITE_AVALIACAO_REENVIADO' : 'CONVITE_AVALIACAO_GERADO';
            if ($reminder) $event = 'LEMBRETE_AVALIACAO_GERADO';
            $this->history->log($reservationId, $event, $reservation['status'], $reservation['status'], ['convite_id'=>$inviteId,'expira_em'=>$window['expires_at']->format('c'),'token_rotacionado'=>(bool)$existing], $userId);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }

        $link = base_url('avaliar/' . $rawToken);
        $delivery = $this->notifications->reviewInvitation($reservation, $link, $window['expires_at']->format('Y-m-d H:i:s'), $reminder);
        $this->repository->recordDelivery($inviteId, $delivery['email'], $delivery['whatsapp'], $reminder);
        $this->history->log($reservationId, $reminder ? 'LEMBRETE_AVALIACAO_ENVIADO' : 'CONVITE_AVALIACAO_ENVIADO', $reservation['status'], $reservation['status'], ['email'=>$delivery['email'],'whatsapp'=>$delivery['whatsapp']], $userId);
        return ['skipped'=>false,'invite_id'=>$inviteId,'email'=>$delivery['email'],'whatsapp'=>$delivery['whatsapp'],'expires_at'=>$window['expires_at']->format('Y-m-d H:i:s'),'link'=>$link];
    }

    public function revoke(int $reservationId, int $userId): array
    {
        $this->db->beginTransaction();
        try {
            $reservation = $this->repository->reservation($reservationId, true) ?? throw new RuntimeException('Reserva nao encontrada.');
            $invite = $this->repository->invitationByReservation($reservationId, true) ?? throw new RuntimeException('Convite nao encontrado.');
            if ($invite['status'] === ReviewInvitationStatus::UTILIZADO->value) throw new RuntimeException('Uma avaliacao ja foi enviada e o convite nao pode ser revogado.');
            $this->db->prepare("UPDATE convites_avaliacao SET status='REVOGADO',revogado_em=NOW() WHERE id=?")->execute([$invite['id']]);
            $this->history->log($reservationId, 'CONVITE_AVALIACAO_REVOGADO', $reservation['status'], $reservation['status'], ['convite_id'=>$invite['id']], $userId);
            $this->db->commit();
            return ['revoked'=>true];
        } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    public function runCron(): array
    {
        $expired = $this->expireInvitations();
        $threshold = (new DateTimeImmutable())->modify('-' . (int) $this->config['review_delay_hours'] . ' hours')->format('Y-m-d H:i:s');
        $sent=0; $failed=0; $reminders=0;
        foreach ($this->repository->invitationCandidates($threshold) as $id) {
            try { $result=$this->send($id); if (empty($result['skipped'])) $sent++; } catch (Throwable $e) { error_log('[avaliacoes-cron] convite reserva '.$id.': '.$e->getMessage()); $failed++; }
        }
        $reminderThreshold=(new DateTimeImmutable())->modify('-'.(int)$this->config['review_reminder_days'].' days')->format('Y-m-d H:i:s');
        foreach ($this->repository->reminderCandidates($reminderThreshold) as $id) {
            try { $this->send($id,true,true); $reminders++; } catch (Throwable $e) { error_log('[avaliacoes-cron] lembrete reserva '.$id.': '.$e->getMessage()); $failed++; }
        }
        return compact('sent','reminders','expired','failed');
    }

    private function expireInvitations(): int
    {
        $ids=$this->db->query("SELECT reserva_id FROM convites_avaliacao WHERE status IN ('PENDENTE','ENVIADO') AND expira_em<NOW()")->fetchAll(PDO::FETCH_COLUMN);
        if (!$ids) return 0;
        $this->db->exec("UPDATE convites_avaliacao SET status='EXPIRADO' WHERE status IN ('PENDENTE','ENVIADO') AND expira_em<NOW()");
        foreach ($ids as $id) $this->history->log((int)$id,'CONVITE_AVALIACAO_EXPIRADO',null,null);
        return count($ids);
    }
}

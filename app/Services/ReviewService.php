<?php
declare(strict_types=1);

namespace Refugio\Services;

use DateTimeImmutable;
use PDO;
use Refugio\Models\ReviewInvitationStatus;
use Refugio\Models\ReviewStatus;
use Refugio\Repositories\ReviewRepository;
use Refugio\Support\ReviewValidator;
use RuntimeException;
use Throwable;

final class ReviewService
{
    private ReviewRepository $repository;
    private ReviewEligibilityService $eligibility;
    private HistoryService $history;

    public function __construct(private PDO $db, private array $config)
    {
        $this->repository=new ReviewRepository($db);
        $this->eligibility=new ReviewEligibilityService($db,$config);
        $this->history=new HistoryService($db);
    }

    public static function validTokenFormat(string $token): bool
    {
        return (bool) preg_match('/^[a-f0-9]{64}$/', $token);
    }

    public static function usableInvitation(array $invite, ?DateTimeImmutable $now = null): bool
    {
        $now ??= new DateTimeImmutable();
        $expiresAt = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', (string) ($invite['expira_em'] ?? ''));
        return in_array((string) ($invite['status'] ?? ''), [ReviewInvitationStatus::PENDENTE->value, ReviewInvitationStatus::ENVIADO->value], true)
            && empty($invite['revogado_em'])
            && $expiresAt !== false
            && $expiresAt >= $now;
    }

    public function access(string $token): array
    {
        if (!self::validTokenFormat($token)) throw new ReviewAccessException();
        $invite=$this->repository->invitationByToken($token);
        if (!$invite) throw new ReviewAccessException();
        if ($invite['status']===ReviewInvitationStatus::UTILIZADO->value) throw new ReviewAccessException(true);
        if (!self::usableInvitation($invite)) throw new ReviewAccessException();
        $reservation=$this->repository->reservation((int)$invite['reserva_id']) ?? throw new ReviewAccessException();
        $review=$this->repository->reviewByReservation((int)$reservation['id']);
        if ($review) throw new ReviewAccessException(true);
        if (!$this->eligibility->check($reservation,$review)['eligible']) throw new ReviewAccessException();
        return ['invite'=>$invite,'reservation'=>$reservation];
    }

    public function submit(string $token, array $input): int
    {
        $access=$this->access($token);
        $validated=ReviewValidator::validate($input,$access['reservation']);
        if ($validated['errors']) throw new ReviewValidationException($validated['errors']);
        $this->db->beginTransaction();
        try {
            $invite=$this->repository->invitationByToken($token,true) ?? throw new ReviewAccessException();
            if (!self::usableInvitation($invite)) throw new ReviewAccessException($invite['status']===ReviewInvitationStatus::UTILIZADO->value);
            $reservation=$this->repository->reservation((int)$invite['reserva_id'],true) ?? throw new ReviewAccessException();
            $existing=$this->repository->reviewByReservation((int)$reservation['id']);
            if ($existing) throw new ReviewAccessException(true);
            if (!$this->eligibility->check($reservation,$existing)['eligible']) throw new ReviewAccessException();
            $reviewId=$this->repository->createReview((int)$reservation['id'],(int)$invite['id'],$validated['data']);
            $this->db->prepare("UPDATE convites_avaliacao SET status='UTILIZADO',utilizado_em=NOW() WHERE id=?")->execute([$invite['id']]);
            $this->history->log((int)$reservation['id'],'AVALIACAO_ENVIADA',$reservation['status'],$reservation['status'],['avaliacao_id'=>$reviewId,'convite_id'=>$invite['id']]);
            $this->db->commit();
        } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
        (new NotificationService($this->db))->admin($reservation,'NOVA_AVALIACAO','Uma avaliacao verificada aguarda moderacao.','admin/avaliacoes/'.$reviewId);
        return $reviewId;
    }

    public function moderate(int $reviewId, string $action, array $input, int $userId): void
    {
        $this->db->beginTransaction();
        try {
            $stmt=$this->db->prepare('SELECT a.*,r.status reserva_status FROM avaliacoes a JOIN reservas r ON r.id=a.reserva_id WHERE a.id=? FOR UPDATE');
            $stmt->execute([$reviewId]); $review=$stmt->fetch() ?: throw new RuntimeException('Avaliacao nao encontrada.');
            if ($action==='responder') {
                $response=ReviewValidator::cleanText((string)($input['resposta_administrador']??''),1000);
                $this->db->prepare('UPDATE avaliacoes SET resposta_administrador=? WHERE id=?')->execute([$response?:null,$reviewId]);
                $this->history->log((int)$review['reserva_id'],'RESPOSTA_ADMINISTRADOR_ADICIONADA',$review['reserva_status'],$review['reserva_status'],['avaliacao_id'=>$reviewId,'resposta_removida'=>$response===''],$userId);
                $this->db->commit(); return;
            }
            $next=match($action){'aprovar','republicar'=>ReviewStatus::APROVADA,'rejeitar'=>ReviewStatus::REJEITADA,'ocultar'=>ReviewStatus::OCULTA,default=>throw new RuntimeException('Acao de moderacao invalida.')};
            $current=ReviewStatus::from($review['status']); $current->assertTransitionTo($next);
            $reason=ReviewValidator::cleanText((string)($input['motivo']??''),1000);
            if (in_array($action,['rejeitar','ocultar'],true) && $reason==='') throw new RuntimeException('Informe o motivo interno da moderacao.');
            $timestamps=match($next){ReviewStatus::APROVADA=>"aprovada_em=NOW(),rejeitada_em=NULL,ocultada_em=NULL,aprovada_por_usuario_id=?",ReviewStatus::REJEITADA=>"rejeitada_em=NOW()",ReviewStatus::OCULTA=>"ocultada_em=NOW()",default=>''};
            $params=$next===ReviewStatus::APROVADA?[$next->value,$reason?:null,$userId,$reviewId]:[$next->value,$reason?:null,$reviewId];
            $this->db->prepare("UPDATE avaliacoes SET status=?,motivo_moderacao=?,{$timestamps} WHERE id=?")->execute($params);
            $event=match($action){'aprovar'=>'AVALIACAO_APROVADA','rejeitar'=>'AVALIACAO_REJEITADA','ocultar'=>'AVALIACAO_OCULTADA','republicar'=>'AVALIACAO_REPUBLICADA'};
            $this->history->log((int)$review['reserva_id'],$event,$review['reserva_status'],$review['reserva_status'],['avaliacao_id'=>$reviewId,'status_anterior'=>$current->value,'status_novo'=>$next->value,'motivo'=>$reason],$userId);
            $this->db->commit();
        } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }
}

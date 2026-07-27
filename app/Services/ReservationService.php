<?php
declare(strict_types=1);

namespace Refugio\Services;

use DateTimeImmutable;
use DomainException;
use PDO;
use Refugio\Models\PaymentStatus;
use Refugio\Models\ReservationStatus;
use Refugio\Repositories\CustomerRepository;
use Refugio\Repositories\ReservationRepository;
use Refugio\Support\ReservationValidator;
use Refugio\Support\Money;
use RuntimeException;
use Throwable;

final class ReservationService
{
    private ReservationRepository $reservations;
    private AvailabilityService $availability;
    private HistoryService $history;
    private NotificationService $notifications;

    public function __construct(private PDO $db, private array $config)
    {
        $this->reservations = new ReservationRepository($db);
        $this->availability = new AvailabilityService($db);
        $this->history = new HistoryService($db);
        $this->notifications = new NotificationService($db);
    }

    public function request(array $input, string $idempotency): array
    {
        $validated = ReservationValidator::validate($input, $this->config);
        if ($validated['errors']) throw new ValidationException($validated['errors']);
        $idempotency = hash('sha256', $idempotency);
        if ($existing = $this->reservations->findByIdempotency($idempotency)) return $existing;
        $token = self::token();
        $data = array_merge($validated['data'], [
            'codigo' => $this->code(), 'token_publico' => $token, 'idempotency_key' => $idempotency,
            'status' => ReservationStatus::AGUARDANDO_APROVACAO->value,
            'finalidade_coleta' => 'Processar a solicitacao, o pagamento e a comunicacao sobre a hospedagem.',
        ]);
        $this->db->beginTransaction();
        try {
            $id = $this->reservations->create($data);
            $this->history->log($id, 'SOLICITACAO_CRIADA', null, $data['status'], ['origem' => 'SITE_DIRETO']);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            if ($existing = $this->reservations->findByIdempotency($idempotency)) return $existing;
            throw $e;
        }
        $reservation = $this->reservations->find($id);
        $this->notifications->admin($reservation, 'NOVA_SOLICITACAO', 'Ha uma nova solicitacao aguardando analise.');
        $this->emitAutomation('RESERVATION_REQUEST_CREATED',(int)$reservation['id']);
        return $reservation;
    }

    public function createManualProposal(array $input, string $idempotency, int $userId): array
    {
        $validated = ReservationValidator::validate($input + [
            'regras_aceitas' => '1',
            'cancelamento_aceito' => '1',
            'contato_autorizado' => '1',
        ], $this->config);
        if ($validated['errors']) throw new ValidationException($validated['errors']);

        try {
            $lodging = Money::toCents((string) ($input['valor_hospedagem'] ?? ''));
            $cleaning = Money::toCents((string) ($input['taxa_limpeza'] ?? '0'));
            $extras = Money::toCents((string) ($input['outros_valores'] ?? '0'));
            $discount = Money::toCents((string) ($input['desconto'] ?? '0'));
        } catch (\InvalidArgumentException) {
            throw new ValidationException(['valores' => 'Informe os valores no formato 0,00.']);
        }
        if ($lodging <= 0 || $cleaning < 0 || $extras < 0 || $discount < 0 || $discount > ($lodging + $cleaning + $extras)) {
            throw new ValidationException(['valores' => 'A hospedagem deve ter valor positivo e o desconto não pode superar o subtotal.']);
        }
        $total = $lodging + $cleaning + $extras - $discount;
        if ($total <= 0) throw new ValidationException(['valores' => 'O valor total do pedido deve ser positivo.']);

        $validUntil = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', (string) ($input['validade_proposta'] ?? ''));
        if (!$validUntil || $validUntil <= new DateTimeImmutable()) {
            throw new ValidationException(['validade_proposta' => 'Informe uma validade futura para a proposta.']);
        }
        $commercialNotes = mb_substr(trim(strip_tags((string) ($input['observacoes_comerciais'] ?? ''))), 0, 3000);
        $cancellationPolicy = mb_substr(trim(strip_tags((string) ($input['politica_cancelamento'] ?? ''))), 0, 5000);
        if ($cancellationPolicy === '') {
            throw new ValidationException(['politica_cancelamento' => 'Informe a política de cancelamento aplicável.']);
        }

        $idempotency = hash('sha256', $idempotency);
        if ($existing = $this->reservations->findByIdempotency($idempotency)) {
            return $existing + ['_proposal' => $this->proposalContext($lodging, $cleaning, $extras, $discount, $validUntil)];
        }

        $data = array_merge($validated['data'], [
            'codigo' => $this->code(),
            'token_publico' => self::token(),
            'idempotency_key' => $idempotency,
            'valor_total' => Money::fromCents($total),
            'valor_restante' => Money::fromCents($total),
            'status' => ReservationStatus::AGUARDANDO_APROVACAO->value,
            'observacoes_cobranca' => $commercialNotes ?: null,
            'politica_cancelamento' => $cancellationPolicy,
            'finalidade_coleta' => 'Emitir e acompanhar pedido de reserva solicitado no atendimento via WhatsApp.',
        ]);
        $data['regras_aceitas'] = 0;
        $data['cancelamento_aceito'] = 0;
        $data['whatsapp_autorizado'] = 0;

        $this->db->beginTransaction();
        try {
            $this->availability->lockApprovalMutex();
            $conflicts = $this->availability->conflicts($data['checkin'], $data['checkout'], null, true);
            if (AvailabilityService::hasConflicts($conflicts)) throw new ConflictException($conflicts);
            $id = $this->reservations->createManualProposal($data);
            $this->history->log($id, 'PEDIDO_WHATSAPP_CRIADO', null, $data['status'], [
                'origem' => 'MANUAL',
                'valor_total' => $data['valor_total'],
                'validade_proposta' => $validUntil->format('Y-m-d H:i:s'),
            ], $userId);
            $this->db->commit();
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            if ($existing = $this->reservations->findByIdempotency($idempotency)) {
                return $existing + ['_proposal' => $this->proposalContext($lodging, $cleaning, $extras, $discount, $validUntil)];
            }
            throw $error;
        }

        $reservation = $this->reservations->find($id) ?? throw new RuntimeException('Pedido de reserva não encontrado após a criação.');
        try {
            (new CustomerRepository($this->db))->syncFromReservation($reservation);
            (new AuditService($this->db))->record('RESERVAS', 'CRIAR_PEDIDO_WHATSAPP', 'reservas', $id, null, [
                'codigo' => $reservation['codigo'],
                'status' => $reservation['status'],
                'valor_total' => $reservation['valor_total'],
            ], [], $userId);
        } catch (Throwable $error) {
            error_log('[pedido-whatsapp-cliente] #' . $id . ': ' . $error->getMessage());
        }
        return $reservation + ['_proposal' => $this->proposalContext($lodging, $cleaning, $extras, $discount, $validUntil)];
    }

    public function approve(int $id, array $input, ?array $qrFile, int $userId): array
    {
        $billing = $this->validateBilling($input, $qrFile);
        $storedQr = null;
        $this->db->beginTransaction();
        try {
            $this->availability->lockApprovalMutex();
            $reservation = $this->reservations->findForUpdate($id) ?? throw new RuntimeException('Solicitacao nao encontrada.');
            ReservationStatus::from($reservation['status'])->assertTransitionTo(ReservationStatus::AGUARDANDO_PAGAMENTO);
            $conflicts = $this->availability->conflicts($reservation['checkin'], $reservation['checkout'], $id, true);
            if (AvailabilityService::hasConflicts($conflicts)) throw new ConflictException($conflicts);
            if ($qrFile && ($qrFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $storedQr = (new UploadService($this->config['upload_max_bytes']))->qrCode($qrFile);
            }
            $this->db->prepare("UPDATE reservas SET valor_total=?,valor_sinal=?,valor_restante=?,prazo_pagamento=?,observacoes_cobranca=?,politica_cancelamento=?,status=? WHERE id=?")
                ->execute([$billing['total'], $billing['signal'], $billing['remaining'], $billing['deadline'], $billing['notes'], $billing['policy'], ReservationStatus::AGUARDANDO_PAGAMENTO->value, $id]);
            $paymentType = $billing['mode'] === 'INTEGRAL' ? 'INTEGRAL' : 'SINAL';
            $paymentValue = $paymentType === 'INTEGRAL' ? $billing['total'] : $billing['signal'];
            $this->createPayment($id, $paymentType, $paymentValue, $billing['pix'], $storedQr['path'] ?? null, $billing['deadline'], $billing['notes']);
            if ($billing['mode'] === 'SINAL_SALDO' && $billing['remaining'] > 0) {
                $balanceDate = new DateTimeImmutable($reservation['checkin'] . ' 12:00:00');
                if ($balanceDate <= new DateTimeImmutable()) $balanceDate = (new DateTimeImmutable($billing['deadline']))->modify('+7 days');
                $balanceDue = $balanceDate->format('Y-m-d H:i:s');
                $this->createPayment($id, 'SALDO', $billing['remaining'], $billing['pix'], $storedQr['path'] ?? null, $balanceDue, 'Saldo da hospedagem. Confirme os dados com o anfitriao antes de pagar.');
            }
            $this->db->prepare("INSERT INTO datas_bloqueadas (data_inicio,data_fim,motivo,reserva_id,origem) VALUES (?,?,?,?,'RESERVA_TEMPORARIA')")
                ->execute([$reservation['checkin'], $reservation['checkout'], 'Aguardando pagamento ' . $reservation['codigo'], $id]);
            $this->history->log($id, 'SOLICITACAO_APROVADA', $reservation['status'], ReservationStatus::AGUARDANDO_PAGAMENTO->value, ['valor_total' => $billing['total'], 'prazo' => $billing['deadline']], $userId);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            if ($storedQr && is_file(BASE_PATH . '/' . $storedQr['path'])) @unlink(BASE_PATH . '/' . $storedQr['path']);
            throw $e;
        }
        $reservation = $this->reservations->find($id);
        $this->syncFinancials($id, $userId);
        $this->emitAutomation('RESERVATION_APPROVED',$id,['payment_due'=>$billing['deadline']]);
        $this->emitAutomation('PAYMENT_REQUEST_CREATED',$id,['payment_due'=>$billing['deadline']]);
        return $reservation;
    }

    public function refuse(int $id, string $reason, int $userId): void
    {
        $this->changeStatus($id, ReservationStatus::RECUSADA, 'SOLICITACAO_RECUSADA', ['motivo' => mb_substr(trim(strip_tags($reason)), 0, 1000)], $userId);
        $this->emitAutomation('RESERVATION_REJECTED',$id);
    }

    public function uploadReceipt(array $reservation, int $paymentId, array $file): void
    {
        $current = ReservationStatus::from($reservation['status']);
        if (!in_array($current, [ReservationStatus::AGUARDANDO_PAGAMENTO, ReservationStatus::COMPROVANTE_ENVIADO, ReservationStatus::RESERVA_CONFIRMADA], true)) throw new DomainException('Esta solicitacao nao aceita comprovante agora.');
        $stored = (new UploadService($this->config['upload_max_bytes']))->receipt($file);
        $this->db->beginTransaction();
        try {
            $locked = $this->reservations->findForUpdate((int) $reservation['id']);
            $stmt = $this->db->prepare("SELECT * FROM pagamentos WHERE id=? AND reserva_id=? AND status IN ('PENDENTE','COMPROVANTE_ENVIADO') FOR UPDATE");
            $stmt->execute([$paymentId, $reservation['id']]);
            if (!$stmt->fetch()) throw new RuntimeException('Cobranca indisponivel para envio.');
            $this->db->prepare("UPDATE pagamentos SET comprovante_path=?,comprovante_nome_original=?,comprovante_mime=?,status=? WHERE id=?")
                ->execute([$stored['path'], $stored['name'], $stored['mime'], PaymentStatus::COMPROVANTE_ENVIADO->value, $paymentId]);
            if ($locked['status'] === ReservationStatus::AGUARDANDO_PAGAMENTO->value) {
                $this->db->prepare('UPDATE reservas SET status=? WHERE id=?')->execute([ReservationStatus::COMPROVANTE_ENVIADO->value, $reservation['id']]);
            }
            $newStatus = $locked['status'] === ReservationStatus::AGUARDANDO_PAGAMENTO->value ? ReservationStatus::COMPROVANTE_ENVIADO->value : $locked['status'];
            $this->history->log((int) $reservation['id'], 'COMPROVANTE_ENVIADO', $locked['status'], $newStatus, ['pagamento_id' => $paymentId, 'mime' => $stored['mime']]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            @unlink(BASE_PATH . '/' . $stored['path']);
            throw $e;
        }
        $updated = $this->reservations->find((int) $reservation['id']);
        $this->notifications->customer($updated, 'COMPROVANTE_RECEBIDO');
        $this->notifications->admin($updated, 'NOVO_COMPROVANTE', 'Um cliente enviou comprovante e aguarda verificacao manual.');
    }

    public function confirmPayment(int $reservationId, int $paymentId, string $notes, int $userId): void
    {
        $this->db->beginTransaction();
        try {
            $reservation = $this->reservations->findForUpdate($reservationId) ?? throw new RuntimeException('Reserva nao encontrada.');
            $current = ReservationStatus::from($reservation['status']);
            if (!in_array($current, [ReservationStatus::AGUARDANDO_PAGAMENTO, ReservationStatus::COMPROVANTE_ENVIADO, ReservationStatus::RESERVA_CONFIRMADA], true)) throw new DomainException('A reserva nao aguarda confirmacao de pagamento.');
            $stmt = $this->db->prepare("SELECT * FROM pagamentos WHERE id=? AND reserva_id=? AND status IN ('PENDENTE','COMPROVANTE_ENVIADO') FOR UPDATE");
            $stmt->execute([$paymentId, $reservationId]); $payment = $stmt->fetch();
            if (!$payment) throw new RuntimeException('Cobranca pendente nao encontrada.');
            $this->db->prepare("UPDATE pagamentos SET status='CONFIRMADO',data_confirmacao=NOW(),observacoes=CONCAT_WS('\n',observacoes,?) WHERE id=?")->execute([mb_substr(trim(strip_tags($notes)), 0, 1000), $paymentId]);
            $sum = $this->db->prepare("SELECT COALESCE(SUM(valor),0) FROM pagamentos WHERE reserva_id=? AND status='CONFIRMADO'");
            $sum->execute([$reservationId]);
            $remaining = Money::fromCents(max(0, Money::toCents((string) $reservation['valor_total']) - Money::toCents((string) $sum->fetchColumn())));
            $this->db->prepare('UPDATE reservas SET valor_restante=? WHERE id=?')->execute([$remaining, $reservationId]);
            if ($current === ReservationStatus::RESERVA_CONFIRMADA) {
                $this->history->log($reservationId, 'SALDO_CONFIRMADO', $current->value, $current->value, ['pagamento_id' => $paymentId, 'valor' => $payment['valor']], $userId);
                $this->db->commit();
                $this->syncFinancials($reservationId, $userId);
                $this->emitAutomation('PAYMENT_CONFIRMED',$reservationId,[],'payment:'.$paymentId);
                $this->releasePostPaymentJourney($reservationId);
                return;
            }
            $current->assertTransitionTo(ReservationStatus::PAGAMENTO_CONFIRMADO);
            $this->db->prepare('UPDATE reservas SET status=? WHERE id=?')->execute([ReservationStatus::PAGAMENTO_CONFIRMADO->value, $reservationId]);
            $this->history->log($reservationId, 'PAGAMENTO_CONFIRMADO', $current->value, ReservationStatus::PAGAMENTO_CONFIRMADO->value, ['pagamento_id' => $paymentId, 'valor' => $payment['valor']], $userId);
            ReservationStatus::PAGAMENTO_CONFIRMADO->assertTransitionTo(ReservationStatus::RESERVA_CONFIRMADA);
            $this->db->prepare('UPDATE reservas SET status=? WHERE id=?')->execute([ReservationStatus::RESERVA_CONFIRMADA->value, $reservationId]);
            $this->db->prepare("UPDATE datas_bloqueadas SET origem='RESERVA_CONFIRMADA',motivo=? WHERE reserva_id=?")->execute(['Reserva confirmada ' . $reservation['codigo'], $reservationId]);
            $this->history->log($reservationId, 'RESERVA_CONFIRMADA', ReservationStatus::PAGAMENTO_CONFIRMADO->value, ReservationStatus::RESERVA_CONFIRMADA->value, [], $userId);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
        $this->syncFinancials($reservationId, $userId);
        $this->emitAutomation('PAYMENT_CONFIRMED',$reservationId,[],'payment:'.$paymentId);
        $this->releasePostPaymentJourney($reservationId);
        $this->scheduleMilestones($reservationId);
    }

    public function rejectReceipt(int $reservationId, int $paymentId, string $reason, int $userId): void
    {
        $this->db->beginTransaction();
        try {
            $r = $this->reservations->findForUpdate($reservationId) ?? throw new RuntimeException('Reserva nao encontrada.');
            ReservationStatus::from($r['status'])->assertTransitionTo(ReservationStatus::AGUARDANDO_PAGAMENTO);
            $payment = $this->db->prepare("SELECT id FROM pagamentos WHERE id=? AND reserva_id=? AND status='COMPROVANTE_ENVIADO' FOR UPDATE");
            $payment->execute([$paymentId, $reservationId]);
            if (!$payment->fetchColumn()) throw new RuntimeException('Comprovante pendente nao encontrado.');
            $this->db->prepare("UPDATE pagamentos SET status='RECUSADO',observacoes=CONCAT_WS('\n',observacoes,?) WHERE id=? AND reserva_id=? AND status='COMPROVANTE_ENVIADO'")->execute([mb_substr(trim(strip_tags($reason)), 0, 1000), $paymentId, $reservationId]);
            $this->db->prepare("UPDATE reservas SET status='AGUARDANDO_PAGAMENTO' WHERE id=?")->execute([$reservationId]);
            $this->history->log($reservationId, 'COMPROVANTE_RECUSADO', $r['status'], ReservationStatus::AGUARDANDO_PAGAMENTO->value, ['pagamento_id' => $paymentId, 'motivo' => $reason], $userId);
            $this->db->commit();
        } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    public function cancel(int $id, string $reason, int $userId): void
    {
        $this->changeStatus($id, ReservationStatus::CANCELADA, 'RESERVA_CANCELADA', ['motivo' => mb_substr(trim(strip_tags($reason)), 0, 1000)], $userId, true);
        $this->notifications->customer($this->reservations->find($id), 'RESERVA_CANCELADA');
    }

    public function finish(int $id, int $userId): void
    {
        $this->changeStatus($id, ReservationStatus::FINALIZADA, 'RESERVA_FINALIZADA', [], $userId, true);
        $this->emitAutomation('RESERVATION_COMPLETED',$id);
    }

    public function updateInternalNotes(int $id, string $notes, int $userId): void
    {
        $clean = mb_substr(trim(strip_tags($notes)), 0, 5000);
        $this->db->prepare('UPDATE reservas SET observacoes_internas=? WHERE id=?')->execute([$clean, $id]);
        $this->history->log($id, 'OBSERVACOES_INTERNAS_ALTERADAS', null, null, [], $userId);
    }

    public function addPayment(int $reservationId, array $input, ?array $qrFile, int $userId): void
    {
        $type = (string) ($input['tipo'] ?? '');
        $value = self::decimal($input['valor'] ?? '');
        $deadline = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', (string) ($input['data_vencimento'] ?? ''));
        $pix = mb_substr(trim((string) ($input['pix_copia_cola'] ?? '')), 0, 5000);
        if (!in_array($type, ['SINAL','SALDO','INTEGRAL','CAUCAO','OUTRO'], true) || $value <= 0 || !$deadline || $deadline <= new DateTimeImmutable()) throw new ValidationException(['pagamento' => 'Informe tipo, valor e vencimento futuro validos.']);
        $hasQr = $qrFile && ($qrFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
        if ($pix === '' && !$hasQr) throw new ValidationException(['pix' => 'Informe o codigo Pix ou um QR Code.']);
        $stored = $hasQr ? (new UploadService($this->config['upload_max_bytes']))->qrCode($qrFile) : null;
        $this->db->beginTransaction();
        try {
            $r = $this->reservations->findForUpdate($reservationId) ?? throw new RuntimeException('Reserva nao encontrada.');
            if (!in_array($r['status'], [ReservationStatus::AGUARDANDO_PAGAMENTO->value, ReservationStatus::COMPROVANTE_ENVIADO->value, ReservationStatus::RESERVA_CONFIRMADA->value], true)) throw new DomainException('Nao e possivel criar cobranca neste status.');
            $this->createPayment($reservationId, $type, $value, $pix, $stored['path'] ?? null, $deadline->format('Y-m-d H:i:s'), mb_substr(trim(strip_tags((string) ($input['observacoes'] ?? ''))), 0, 3000));
            $this->history->log($reservationId, 'COBRANCA_CRIADA', $r['status'], $r['status'], ['tipo' => $type, 'valor' => $value], $userId);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            if ($stored) @unlink(BASE_PATH . '/' . $stored['path']);
            throw $e;
        }
        $this->syncFinancials($reservationId, $userId);
    }

    public function updateReservation(int $id, array $input, int $userId): void
    {
        $checkin = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($input['checkin'] ?? ''));
        $checkout = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($input['checkout'] ?? ''));
        $total = self::decimal($input['valor_total'] ?? '0');
        if (!$checkin || !$checkout || $checkout <= $checkin || $checkin < new DateTimeImmutable('today')) throw new ValidationException(['datas' => 'Informe datas futuras validas.']);
        $this->db->beginTransaction();
        try {
            $this->availability->lockApprovalMutex();
            $r = $this->reservations->findForUpdate($id) ?? throw new RuntimeException('Reserva nao encontrada.');
            if (in_array($r['status'], ReservationStatus::blocking(), true)) {
                $conflicts = $this->availability->conflicts($checkin->format('Y-m-d'), $checkout->format('Y-m-d'), $id, true);
                if (AvailabilityService::hasConflicts($conflicts)) throw new ConflictException($conflicts);
                $this->db->prepare('UPDATE datas_bloqueadas SET data_inicio=?,data_fim=? WHERE reserva_id=?')->execute([$checkin->format('Y-m-d'), $checkout->format('Y-m-d'), $id]);
            }
            if ($r['status'] !== ReservationStatus::AGUARDANDO_APROVACAO->value && $total <= 0) throw new ValidationException(['valor_total' => 'O valor total deve ser positivo para uma reserva aprovada.']);
            $confirmed = $this->db->prepare("SELECT COALESCE(SUM(valor),0) FROM pagamentos WHERE reserva_id=? AND status='CONFIRMADO'");
            $confirmed->execute([$id]);
            $remaining = $total > 0 ? max(0, $total - (float) $confirmed->fetchColumn()) : null;
            $this->db->prepare('UPDATE reservas SET checkin=?,checkout=?,valor_total=?,valor_restante=?,observacoes_cliente=? WHERE id=?')->execute([$checkin->format('Y-m-d'), $checkout->format('Y-m-d'), $total ?: null, $remaining, mb_substr(trim(strip_tags((string) ($input['observacoes_cliente'] ?? ''))), 0, 3000), $id]);
            $this->history->log($id, 'DADOS_ALTERADOS', $r['status'], $r['status'], ['checkin_anterior' => $r['checkin'], 'checkout_anterior' => $r['checkout'], 'valor_anterior' => $r['valor_total'], 'checkin' => $checkin->format('Y-m-d'), 'checkout' => $checkout->format('Y-m-d'), 'valor' => $total], $userId);
            $this->db->commit();
        } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    public function expireOverdue(): int
    {
        $statuses = $this->config['keep_receipt_after_expiry'] ? "('AGUARDANDO_PAGAMENTO')" : "('AGUARDANDO_PAGAMENTO','COMPROVANTE_ENVIADO')";
        $paymentStatuses = $this->config['keep_receipt_after_expiry'] ? "('PENDENTE')" : "('PENDENTE','COMPROVANTE_ENVIADO')";
        $ids = $this->db->query("SELECT DISTINCT r.id FROM reservas r JOIN pagamentos p ON p.reserva_id=r.id WHERE r.status IN {$statuses} AND p.status IN {$paymentStatuses} AND p.data_vencimento<NOW()")->fetchAll(PDO::FETCH_COLUMN);
        $count = 0;
        foreach ($ids as $id) {
            $this->db->beginTransaction();
            try {
                $r = $this->reservations->findForUpdate((int) $id);
                if (!$r || !in_array($r['status'], $this->config['keep_receipt_after_expiry'] ? ['AGUARDANDO_PAGAMENTO'] : ['AGUARDANDO_PAGAMENTO','COMPROVANTE_ENVIADO'], true)) { $this->db->rollBack(); continue; }
                ReservationStatus::from($r['status'])->assertTransitionTo(ReservationStatus::EXPIRADA);
                $this->db->prepare("UPDATE reservas SET status='EXPIRADA' WHERE id=?")->execute([$id]);
                $this->db->prepare("UPDATE pagamentos SET status='EXPIRADO' WHERE reserva_id=? AND status IN {$paymentStatuses}")->execute([$id]);
                $this->db->prepare('DELETE FROM datas_bloqueadas WHERE reserva_id=?')->execute([$id]);
                $this->history->log((int) $id, 'PAGAMENTO_EXPIRADO', $r['status'], ReservationStatus::EXPIRADA->value);
                $this->db->commit();
                $this->emitAutomation('PAYMENT_EXPIRED',(int)$id);
                $count++;
            } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
        }
        return $count;
    }

    private function syncFinancials(int $reservationId, int $userId): void
    {
        try {
            (new FinancialService($this->db))->syncReservationPayments($reservationId, $userId);
        } catch (Throwable $error) {
            error_log('[financeiro-reserva] ' . $error->getMessage());
        }
    }

    private function emitAutomation(string $event,int $reservationId,array $context=[],?string $eventKey=null):void
    {
        try{(new ReservationAutomationService($this->db,$this->config))->emit($event,$reservationId,$context,$eventKey);}catch(Throwable $error){error_log('[automation-reservation] '.$event.' #'.$reservationId.': '.$error->getMessage());}
    }

    private function scheduleMilestones(int $reservationId):void
    {
        try{(new ReservationAutomationService($this->db,$this->config))->scheduleMilestones($reservationId);}catch(Throwable $error){error_log('[automation-milestones] #'.$reservationId.': '.$error->getMessage());}
    }

    private function releasePostPaymentJourney(int $reservationId):void
    {
        try{
            (new PreCheckinService($this->db))->ensure($reservationId);
            $this->emitAutomation('PRECHECKIN_AVAILABLE',$reservationId,[],'precheckin-available');

            $contract=$this->db->prepare('SELECT id FROM contracts WHERE reservation_id=? LIMIT 1');
            $contract->execute([$reservationId]);
            if(!$contract->fetchColumn()&&($reservation=$this->reservations->find($reservationId))){
                $missing=(new PropertySettingsService($this->db))->missing(PropertySettingsService::REQUIRED_FOR_CONTRACT);
                $details='Pagamento confirmado, mas o contrato ainda requer os dados jurídicos do locatário '
                    .'(nacionalidade, estado civil, profissão, RG e endereço completo) e a validação do inventário/anexos.';
                if($missing!==[])$details.=' Configurações pendentes: '.implode(', ',$missing).'.';
                $this->notifications->admin($reservation,'CONTRACT_PREPARATION_REQUIRED',$details,'admin/contratos');
            }
        }catch(Throwable $error){error_log('[post-payment-journey] #'.$reservationId.': '.$error->getMessage());}
    }

    private function changeStatus(int $id, ReservationStatus $next, string $action, array $details, int $userId, bool $releaseBlock = false): void
    {
        $this->db->beginTransaction();
        try {
            $r = $this->reservations->findForUpdate($id) ?? throw new RuntimeException('Reserva nao encontrada.');
            $current = ReservationStatus::from($r['status']); $current->assertTransitionTo($next);
            $this->db->prepare('UPDATE reservas SET status=? WHERE id=?')->execute([$next->value, $id]);
            if ($releaseBlock) $this->db->prepare('DELETE FROM datas_bloqueadas WHERE reserva_id=?')->execute([$id]);
            $this->history->log($id, $action, $current->value, $next->value, $details, $userId);
            $this->db->commit();
        } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    private function createPayment(int $reservationId, string $type, float $value, string $pix, ?string $qr, string $deadline, string $notes): void
    {
        $this->db->prepare("INSERT INTO pagamentos (reserva_id,tipo,valor,pix_copia_cola,qr_code_path,status,data_vencimento,observacoes) VALUES (?,?,?,?,?,'PENDENTE',?,?)")
            ->execute([$reservationId, $type, $value, $pix, $qr, $deadline, $notes]);
    }

    private function validateBilling(array $input, ?array $qrFile): array
    {
        $total = self::decimal($input['valor_total'] ?? ''); $signal = self::decimal($input['valor_sinal'] ?? '0');
        $mode = (string) ($input['forma_cobranca'] ?? '');
        if ($total <= 0 || !in_array($mode, ['INTEGRAL','SINAL','SINAL_SALDO'], true)) throw new ValidationException(['cobranca' => 'Informe valor e forma de cobranca validos.']);
        if ($mode === 'INTEGRAL') $signal = $total;
        if ($mode !== 'INTEGRAL' && ($signal <= 0 || $signal > $total)) throw new ValidationException(['valor_sinal' => 'O valor do sinal deve ser positivo e nao pode superar o total.']);
        $deadlineRaw = (string) ($input['prazo_pagamento'] ?? '');
        $deadline = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $deadlineRaw);
        if (!$deadline || $deadline <= new DateTimeImmutable()) throw new ValidationException(['prazo_pagamento' => 'Informe um prazo futuro.']);
        $pix = trim((string) ($input['pix_copia_cola'] ?? ''));
        $hasQr = $qrFile && ($qrFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
        if ($pix === '' && !$hasQr) throw new ValidationException(['pix' => 'Informe o Pix Copia e Cola ou envie o QR Code.']);
        return ['total' => $total, 'signal' => $signal, 'remaining' => max(0, $total - $signal), 'mode' => $mode, 'deadline' => $deadline->format('Y-m-d H:i:s'), 'pix' => mb_substr($pix, 0, 5000), 'notes' => mb_substr(trim(strip_tags((string) ($input['observacoes_cobranca'] ?? ''))), 0, 3000), 'policy' => mb_substr(trim(strip_tags((string) ($input['politica_cancelamento'] ?? ''))), 0, 5000)];
    }

    private static function decimal(mixed $value): float
    {
        $normalized = str_replace(['.', ','], ['', '.'], trim((string) $value));
        if (substr_count((string) $value, '.') === 1 && !str_contains((string) $value, ',')) $normalized = (string) $value;
        return round((float) $normalized, 2);
    }

    private function proposalContext(int $lodging, int $cleaning, int $extras, int $discount, DateTimeImmutable $validUntil): array
    {
        $items = [
            ['description' => 'Hospedagem', 'amount' => Money::fromCents($lodging)],
        ];
        if ($cleaning > 0) $items[] = ['description' => 'Taxa de limpeza', 'amount' => Money::fromCents($cleaning)];
        if ($extras > 0) $items[] = ['description' => 'Outros serviços', 'amount' => Money::fromCents($extras)];
        if ($discount > 0) $items[] = ['description' => 'Desconto', 'amount' => Money::fromCents(-$discount)];
        return ['items' => $items, 'valid_until' => $validUntil->format('Y-m-d H:i:s')];
    }

    private function code(): string
    {
        do { $code = 'RDC-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6)); $stmt = $this->db->prepare('SELECT 1 FROM reservas WHERE codigo=?'); $stmt->execute([$code]); } while ($stmt->fetchColumn());
        return $code;
    }

    private static function token(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}

final class ValidationException extends RuntimeException
{
    public function __construct(public readonly array $errors) { parent::__construct('Verifique os campos informados.'); }
}

final class ConflictException extends RuntimeException
{
    public function __construct(public readonly array $conflicts) { parent::__construct('As datas deixaram de estar disponiveis.'); }
}

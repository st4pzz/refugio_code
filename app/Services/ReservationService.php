<?php
declare(strict_types=1);

namespace Refugio\Services;

use DateTimeImmutable;
use DomainException;
use PDO;
use Refugio\Models\PaymentStatus;
use Refugio\Models\ReservationStatus;
use Refugio\Repositories\ReservationRepository;
use Refugio\Support\ReservationValidator;
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
        $this->notifications->customer($reservation, 'SOLICITACAO_RECEBIDA', ['link' => base_url('reserva/' . $token)]);
        $this->notifications->admin($reservation, 'NOVA_SOLICITACAO', 'Ha uma nova solicitacao aguardando analise.');
        return $reservation;
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
            if ($conflicts['reservas'] || $conflicts['bloqueios']) throw new ConflictException($conflicts);
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
        $this->notifications->customer($reservation, 'RESERVA_APROVADA', ['valor' => $paymentValue, 'link' => base_url('reserva/' . $reservation['token_publico'])]);
        return $reservation;
    }

    public function refuse(int $id, string $reason, int $userId): void
    {
        $this->changeStatus($id, ReservationStatus::RECUSADA, 'SOLICITACAO_RECUSADA', ['motivo' => mb_substr(trim(strip_tags($reason)), 0, 1000)], $userId);
        $this->notifications->customer($this->reservations->find($id), 'RESERVA_RECUSADA');
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
            $remaining = max(0, (float) $reservation['valor_total'] - (float) $sum->fetchColumn());
            $this->db->prepare('UPDATE reservas SET valor_restante=? WHERE id=?')->execute([$remaining, $reservationId]);
            if ($current === ReservationStatus::RESERVA_CONFIRMADA) {
                $this->history->log($reservationId, 'SALDO_CONFIRMADO', $current->value, $current->value, ['pagamento_id' => $paymentId, 'valor' => $payment['valor']], $userId);
                $this->db->commit();
                $this->notifications->customer($this->reservations->find($reservationId), 'PAGAMENTO_CONFIRMADO');
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
        $this->notifications->customer($this->reservations->find($reservationId), 'RESERVA_CONFIRMADA');
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
                if ($conflicts['reservas'] || $conflicts['bloqueios']) throw new ConflictException($conflicts);
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
                $this->notifications->customer($this->reservations->find((int) $id), 'PAGAMENTO_EXPIRADO');
                $count++;
            } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
        }
        return $count;
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

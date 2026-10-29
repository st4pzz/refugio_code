<?php
declare(strict_types=1);
namespace Refugio\Services;

use DateTimeImmutable;
use PDO;
use Refugio\Support\Env;
use Refugio\Support\Money;
use RuntimeException;
use Throwable;

final class BookingBillingService
{
    public function __construct(private PDO $db) {}

    public static function validateContact(array $input): array
    {
        $name = trim(strip_tags((string) ($input['guest_name'] ?? '')));
        $phone = preg_replace('/\D+/', '', (string) ($input['phone'] ?? '')) ?? '';
        if (!str_starts_with(trim((string) ($input['phone'] ?? '')), '+') && (strlen($phone) === 10 || strlen($phone) === 11)) $phone = '55' . $phone;
        $email = trim((string) ($input['email'] ?? ''));
        if ($name === '' || mb_strlen($name) > 160) throw new RuntimeException('Informe o nome do hóspede (até 160 caracteres).');
        if ($phone !== '' && !preg_match('/^[1-9][0-9]{10,14}$/', $phone)) throw new RuntimeException('Informe telefone com DDD; para estrangeiros, inclua + e código do país.');
        if ($email !== '' && (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL))) throw new RuntimeException('E-mail inválido.');
        if ($phone === '' && $email === '') throw new RuntimeException('Informe ao menos telefone ou e-mail.');
        $amount = trim((string) ($input['amount'] ?? ''));
        $amount = $amount === '' ? null : Money::normalize($amount);
        if ($amount !== null && (Money::toCents($amount) <= 0 || Money::toCents($amount) > 999999999999)) throw new RuntimeException('Informe um valor positivo válido.');
        $due = trim((string) ($input['due_on'] ?? ''));
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $due);
        if ($due !== '' && (!$date || $date->format('Y-m-d') !== $due)) throw new RuntimeException('Vencimento inválido.');
        return ['guest_name' => $name, 'phone' => $phone ?: null, 'email' => $email ?: null, 'amount' => $amount, 'due_on' => $due ?: null, 'paid' => empty($input['paid']) ? 0 : 1];
    }

    public function forMonth(string $start, string $end): array
    {
        $stmt = $this->db->prepare("SELECT e.id,e.summary,e.starts_at,e.ends_at,c.guest_name,c.phone,c.email,c.amount,c.due_on,c.paid FROM calendar_external_events e JOIN calendar_sources s ON s.id=e.source_id LEFT JOIN booking_billing_contacts c ON c.event_id=e.id WHERE s.provider='BOOKING' AND s.ativo=1 AND e.deleted_at IS NULL AND e.status<>'CANCELLED' AND e.starts_at<? AND e.ends_at>? ORDER BY e.starts_at,e.id");
        $stmt->execute([$end, $start]);
        $events = $stmt->fetchAll();
        $history = $this->db->prepare('SELECT channel,recipient,status,error_message,created_at,sent_at FROM booking_billing_deliveries WHERE event_id=? ORDER BY id DESC LIMIT 6');
        foreach ($events as &$event) { $history->execute([$event['id']]); $event['deliveries'] = $history->fetchAll(); }
        return $events;
    }

    public function save(int $eventId, array $input, int $userId, bool $send): int
    {
        $contact = self::validateContact($input);
        $key = (string) ($input['request_key'] ?? '');
        if ($send && (!preg_match('/^[a-f0-9]{32}$/', $key) || empty($input['confirmed_booking']))) throw new RuntimeException('Confirme na plataforma que é uma reserva real com pagamento devido diretamente ao anfitrião.');
        $channels = array_values(array_intersect(['WHATSAPP','EMAIL'], (array) ($input['channels'] ?? [])));
        if ($send && ($channels === [] || !$contact['amount'] || !$contact['due_on'] || $contact['due_on'] < date('Y-m-d') || $contact['paid'])) throw new RuntimeException('Selecione um canal, valor e vencimento atual/futuro. Não é possível cobrar uma reserva marcada como paga.');
        foreach ($send ? $channels : [] as $channel) if (!$contact[$channel === 'EMAIL' ? 'email' : 'phone']) throw new RuntimeException('Preencha o contato do canal selecionado.');
        $this->db->beginTransaction();
        try {
            $event = $this->activeEvent($eventId, true);
            $stmt = $this->db->prepare('INSERT INTO booking_billing_contacts (event_id,guest_name,phone,email,amount,due_on,paid,updated_by) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE guest_name=VALUES(guest_name),phone=VALUES(phone),email=VALUES(email),amount=VALUES(amount),due_on=VALUES(due_on),paid=VALUES(paid),updated_by=VALUES(updated_by)');
            $stmt->execute([$eventId, ...array_values($contact), $userId]);
            $count = 0;
            foreach ($send ? $channels : [] as $channel) {
                $snapshot = $contact + ['starts_at' => $event['starts_at'], 'ends_at' => $event['ends_at']];
                $recipient = $contact[$channel === 'EMAIL' ? 'email' : 'phone'];
                $stmt = $this->db->prepare("INSERT IGNORE INTO booking_billing_deliveries (event_id,request_key,channel,recipient,snapshot_json,created_by) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$eventId, $key, $channel, $recipient, json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $userId]);
                if ($stmt->rowCount() === 0) continue;
                $deliveryId = (int) $this->db->lastInsertId();
                (new JobQueueService($this->db))->enqueue('BOOKING_BILLING', ['delivery_id' => $deliveryId], 'booking-billing:' . $deliveryId, 50, 1);
                $count++;
            }
            (new AuditService($this->db))->record('RESERVAS', $send ? 'BOOKING_COBRANCA_AGENDADA' : 'BOOKING_CONTATO_SALVO', 'calendar_external_events', $eventId, null, ['channels' => $send ? $channels : [], 'paid' => $contact['paid']], [], $userId);
            $this->db->commit();
            return $count;
        } catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }

    public function process(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE booking_billing_deliveries SET status='SENDING' WHERE id=? AND status='PENDING'");
        $stmt->execute([$id]);
        if ($stmt->rowCount() !== 1) return;
        try {
            $stmt = $this->db->prepare('SELECT * FROM booking_billing_deliveries WHERE id=?'); $stmt->execute([$id]); $delivery = $stmt->fetch();
            $snapshot = json_decode($delivery['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
            $event = $this->activeEvent((int) $delivery['event_id']);
            $stmt = $this->db->prepare('SELECT * FROM booking_billing_contacts WHERE event_id=?'); $stmt->execute([$delivery['event_id']]); $current = $stmt->fetch();
            foreach ($snapshot as $field => $value) {
                $actual = in_array($field, ['starts_at','ends_at'], true) ? $event[$field] : ($current[$field] ?? null);
                if ((string) $actual !== (string) $value) throw new RuntimeException('Dados alterados após agendamento. Revise a cobrança antes de reenviar.');
            }
            if ($current['paid'] || $current['due_on'] < date('Y-m-d')) throw new RuntimeException('Cobrança paga ou vencida; envio cancelado.');
            $parameters = self::parameters($snapshot);
            if ($delivery['channel'] === 'WHATSAPP') {
                $externalId = (new WhatsAppService())->sendTemplate($delivery['recipient'], Env::get('WHATSAPP_BOOKING_BILLING_TEMPLATE', 'booking_cobranca_pix_v1'), $parameters);
            } else {
                $body = self::message($parameters);
                $externalId = (new SmtpClient())->send($delivery['recipient'], 'Pagamento da sua reserva Booking — Refúgio do Cuscuzeiro', '<div style="font-family:Arial,sans-serif;line-height:1.7">' . nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')) . '</div>');
            }
            $this->db->prepare("UPDATE booking_billing_deliveries SET status='SENT',provider_message_id=?,sent_at=NOW() WHERE id=?")->execute([$externalId,$id]);
        } catch (Throwable $error) {
            $this->db->prepare("UPDATE booking_billing_deliveries SET status='REVIEW_REQUIRED',error_message=? WHERE id=?")->execute([mb_substr($error->getMessage(),0,2000),$id]);
            throw $error;
        }
    }

    public static function parameters(array $data): array
    {
        return [$data['guest_name'], date('d/m/Y', strtotime($data['starts_at'])), date('d/m/Y', strtotime($data['ends_at'])), money($data['amount']), date('d/m/Y', strtotime($data['due_on']))];
    }

    public static function templateBody(): string
    {
        return "Olá, {{1}}! Seguem os dados de pagamento da sua reserva feita pelo Booking no Refúgio do Cuscuzeiro.\n\nEntrada: {{2}}\nSaída: {{3}}\nValor desta cobrança: {{4}}\nPagar até: {{5}}\n\nPix por chave CPF: 74373137820\nFavorecido: Sergio Ramella\nBanco: Safra\n\nConfira o favorecido no seu banco e envie o comprovante pelo atendimento. Se já pagou, não pague novamente; fale conosco para conferência.";
    }

    public static function message(array $parameters): string
    {
        $replace = []; foreach ($parameters as $index => $value) $replace['{{' . ($index+1) . '}}'] = (string) $value;
        return strtr(self::templateBody(), $replace);
    }

    private function activeEvent(int $id, bool $lock = false): array
    {
        $stmt = $this->db->prepare("SELECT e.* FROM calendar_external_events e JOIN calendar_sources s ON s.id=e.source_id WHERE e.id=? AND s.provider='BOOKING' AND s.ativo=1 AND e.deleted_at IS NULL AND e.status<>'CANCELLED'" . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute([$id]);
        return $stmt->fetch() ?: throw new RuntimeException('Reserva Booking inativa ou não encontrada.');
    }
}

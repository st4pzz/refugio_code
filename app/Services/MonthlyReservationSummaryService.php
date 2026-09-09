<?php
declare(strict_types=1);

namespace Refugio\Services;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Refugio\Support\Env;
use RuntimeException;

final class MonthlyReservationSummaryService
{
    private const JOB_TYPE = 'RESERVATION_MONTHLY_SUMMARY';

    public function __construct(private PDO $db, private ?WhatsAppService $whatsApp = null)
    {
        $this->whatsApp ??= new WhatsAppService();
    }

    public function enqueueWeekly(?DateTimeImmutable $now = null): int
    {
        $now ??= $this->now();
        return $this->enqueueMonth($now->modify('first day of this month'), 'weekly', $now->format('o-W'));
    }

    public function enqueueManual(string $requestKey, ?DateTimeImmutable $now = null): int
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $requestKey)) {
            throw new RuntimeException('Identificador invalido para o envio manual do resumo.');
        }
        $now ??= $this->now();
        return $this->enqueueMonth($now->modify('first day of this month'), 'manual', $requestKey);
    }

    public function enqueueForConfirmedReservation(int $reservationId): int
    {
        $stmt = $this->db->prepare("SELECT checkin FROM reservas WHERE id=? AND status='RESERVA_CONFIRMADA'");
        $stmt->execute([$reservationId]);
        $checkin = $stmt->fetchColumn();
        if (!$checkin) return 0;
        return $this->enqueueMonth(new DateTimeImmutable((string) $checkin, $this->timezone()), 'direct', (string) $reservationId);
    }

    public function enqueueForConfirmedExternalEvent(int $eventId): int
    {
        return $this->enqueueForConfirmedExternalEvents([$eventId]);
    }

    public function enqueueForConfirmedExternalEvents(array $eventIds): int
    {
        $eventIds = array_values(array_unique(array_filter(array_map('intval', $eventIds), static fn(int $id): bool => $id > 0)));
        if ($eventIds === []) return 0;
        $marks = implode(',', array_fill(0, count($eventIds), '?'));
        $stmt = $this->db->prepare("SELECT e.id,e.starts_at FROM calendar_external_events e JOIN calendar_sources s ON s.id=e.source_id WHERE e.id IN ({$marks}) AND e.status='CONFIRMED' AND e.deleted_at IS NULL AND s.ativo=1 AND s.provider IN ('AIRBNB','BOOKING') ORDER BY e.id");
        $stmt->execute($eventIds);
        $months = [];
        foreach ($stmt->fetchAll() as $event) {
            $date = new DateTimeImmutable((string) $event['starts_at'], $this->timezone());
            $month = $date->format('Y-m-01');
            $months[$month][] = (int) $event['id'];
        }
        $count = 0;
        foreach ($months as $month => $ids) {
            $count += $this->enqueueMonth(new DateTimeImmutable($month, $this->timezone()), 'external', hash('sha256', implode(',', $ids)));
        }
        return $count;
    }

    public function process(array $payload): void
    {
        if (!Env::bool('WHATSAPP_MONTHLY_SUMMARY_ENABLED', true)) return;
        $month = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($payload['month'] ?? ''), $this->timezone());
        if (!$month || $month->format('d') !== '01') throw new RuntimeException('Mes invalido no job de resumo de reservas.');
        $recipient = $this->normalizePhone((string) ($payload['recipient'] ?? ''));
        if ($recipient === '' || !in_array($recipient, $this->recipients(), true)) {
            throw new RuntimeException('Destinatario invalido no job de resumo de reservas.');
        }
        $summary = $this->summary($month);
        $template = Env::get('WHATSAPP_MONTHLY_SUMMARY_TEMPLATE', 'resumo_reservas_mensal');
        $now = $this->now();
        $this->whatsApp->sendTemplate($recipient, $template, [
            $this->monthLabel($month),
            'Atualizado em ' . $now->format('d/m/Y') . ' às ' . $now->format('H:i') . ".\n"
                . $summary['details'] . "\nTotal de estadias no mês: " . $summary['total'] . '.',
        ]);
    }

    public function ensureTemplate(): array
    {
        return $this->whatsApp->ensureMonthlyReservationSummaryTemplate(
            Env::get('WHATSAPP_MONTHLY_SUMMARY_TEMPLATE', 'resumo_reservas_mensal')
        );
    }

    public function summary(DateTimeImmutable $month): array
    {
        $start = $month->modify('first day of this month')->setTime(0, 0);
        $end = $start->modify('first day of next month');
        $groups = ['DIRETA' => [], 'AIRBNB' => [], 'BOOKING' => []];

        $stmt = $this->db->prepare("SELECT codigo,checkin,checkout FROM reservas WHERE status IN ('RESERVA_CONFIRMADA','FINALIZADA') AND checkin<? AND checkout>? ORDER BY checkin,id");
        $stmt->execute([$end->format('Y-m-d'), $start->format('Y-m-d')]);
        foreach ($stmt->fetchAll() as $reservation) {
            $groups['DIRETA'][] = $this->period((string) $reservation['checkin'], (string) $reservation['checkout']) . ' - ' . mb_substr((string) $reservation['codigo'], 0, 32);
        }

        $stmt = $this->db->prepare("SELECT s.provider,e.starts_at,e.ends_at FROM calendar_external_events e JOIN calendar_sources s ON s.id=e.source_id WHERE s.ativo=1 AND s.provider IN ('AIRBNB','BOOKING') AND e.status='CONFIRMED' AND e.deleted_at IS NULL AND e.starts_at<? AND e.ends_at>? ORDER BY e.starts_at,e.id");
        $stmt->execute([$end->format('Y-m-d H:i:s'), $start->format('Y-m-d H:i:s')]);
        foreach ($stmt->fetchAll() as $event) {
            $groups[(string) $event['provider']][] = $this->period((string) $event['starts_at'], (string) $event['ends_at']);
        }

        $labels = ['DIRETA' => 'Diretas', 'AIRBNB' => 'Airbnb', 'BOOKING' => 'Booking'];
        $total = array_sum(array_map('count', $groups));
        if ($total === 0) return ['details' => 'Nenhuma reserva confirmada para este mês.', 'total' => 0, 'groups' => $groups];

        $lines = [];
        $omitted = 0;
        foreach ($groups as $provider => $items) {
            if ($items === []) continue;
            $lines[] = $labels[$provider] . ' (' . count($items) . '):';
            foreach ($items as $item) {
                $candidate = '- ' . $item;
                if (mb_strlen(implode("\n", [...$lines, $candidate])) <= 620) $lines[] = $candidate;
                else $omitted++;
            }
        }
        if ($omitted > 0) $lines[] = '+ ' . $omitted . ' outra(s) reserva(s) no calendario.';
        return ['details' => implode("\n", $lines), 'total' => $total, 'groups' => $groups];
    }

    private function enqueueMonth(DateTimeImmutable $date, string $trigger, string $reference): int
    {
        if (!Env::bool('WHATSAPP_MONTHLY_SUMMARY_ENABLED', true)) return 0;
        $month = $date->modify('first day of this month')->format('Y-m-d');
        $queue = new JobQueueService($this->db);
        $count = 0;
        foreach ($this->recipients() as $recipient) {
            $key = 'reservation-summary:' . $trigger . ':' . $reference . ':' . $month . ':' . hash('sha256', $recipient);
            $queue->enqueue(self::JOB_TYPE, ['month' => $month, 'trigger' => $trigger, 'reference' => $reference, 'recipient' => $recipient], $key, 75, 8);
            $count++;
        }
        return $count;
    }

    private function recipients(): array
    {
        $values = explode(',', Env::get('WHATSAPP_MONTHLY_SUMMARY_RECIPIENTS', '5519999725599,5519999925015'));
        return array_values(array_unique(array_filter(array_map(fn(string $phone): string => $this->normalizePhone($phone), $values))));
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) >= 10 && strlen($digits) <= 15) return $digits;
        return '';
    }

    private function period(string $start, string $end): string
    {
        return (new DateTimeImmutable($start, $this->timezone()))->format('d/m') . '-' . (new DateTimeImmutable($end, $this->timezone()))->format('d/m');
    }

    private function monthLabel(DateTimeImmutable $date): string
    {
        $months = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        return $months[(int) $date->format('n')] . ' de ' . $date->format('Y');
    }

    private function timezone(): DateTimeZone
    {
        return new DateTimeZone(Env::get('APP_TIMEZONE', 'America/Sao_Paulo'));
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->timezone());
    }
}

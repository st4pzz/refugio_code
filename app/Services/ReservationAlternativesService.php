<?php
declare(strict_types=1);

namespace Refugio\Services;

use DateTimeImmutable;
use DomainException;
use PDO;

final class ReservationAlternativesService
{
    public const SEARCH_DAYS = 30;

    public function __construct(private PDO $db) {}

    public function find(array $request): array
    {
        $start = new DateTimeImmutable($request['checkin']);
        $end = new DateTimeImmutable($request['checkout']);
        $nights = (int) $start->diff($end)->days;
        if ($end <= $start || $nights > 365) return [];
        $availability = new AvailabilityService($this->db);
        $availability->assertFreshExternalCalendars();
        $today = new DateTimeImmutable('today');
        $from = max($today->format('Y-m-d'), $start->modify('-' . self::SEARCH_DAYS . ' days')->format('Y-m-d'));
        $until = $end->modify('+' . self::SEARCH_DAYS . ' days')->format('Y-m-d');
        $conflicts = $availability->conflicts($from, $until);
        $blocked = [];
        foreach (['reservas', 'bloqueios', 'externos', 'holds'] as $type) {
            foreach ($conflicts[$type] ?? [] as $item) $blocked[] = ['start' => $item['checkin'], 'end' => $item['checkout']];
        }
        $quotes = new QuoteService($this->db);
        $alternatives = [];
        foreach (self::candidatePeriods($start, $end, $today, $blocked) as $period) {
            try {
                $quote = $quotes->calculate(array_replace($request, $period), true);
                if ((float) $quote['total'] <= 0) continue;
            } catch (DomainException) {
                // A free period may still violate minimum nights or pricing rules.
                continue;
            }
            $alternatives[] = [
                'checkin' => $period['checkin'], 'checkout' => $period['checkout'],
                'nights' => $quote['nights'], 'guests' => $quote['guests'], 'pets' => $quote['pets'],
                'total' => $quote['total'], 'currency' => $quote['currency'],
            ];
            if (count($alternatives) === 3) break;
        }
        $availability->assertFreshExternalCalendars();
        return $alternatives;
    }

    /** Nearest first, future first on ties; all end dates are exclusive. */
    public static function candidatePeriods(DateTimeImmutable $start, DateTimeImmutable $end, DateTimeImmutable $today, array $blocked): array
    {
        if ($end <= $start) return [];
        $nights = (int) $start->diff($end)->days;
        if ($nights > 365) return [];
        $periods = [];
        for ($distance = 1; $distance <= self::SEARCH_DAYS; $distance++) {
            foreach ([$distance, -$distance] as $offset) {
                $checkin = $start->modify(sprintf('%+d days', $offset));
                if ($checkin < $today) continue;
                $checkout = $checkin->modify('+' . $nights . ' days');
                foreach ($blocked as $range) {
                    // Keep timed external events precise instead of truncating to dates.
                    $rangeStart = strlen($range['start']) === 10 ? $range['start'] . ' 00:00:00' : $range['start'];
                    $rangeEnd = strlen($range['end']) === 10 ? $range['end'] . ' 00:00:00' : $range['end'];
                    if (AvailabilityService::overlaps($rangeStart, $rangeEnd, $checkin->format('Y-m-d H:i:s'), $checkout->format('Y-m-d H:i:s'))) continue 2;
                }
                $periods[] = ['checkin' => $checkin->format('Y-m-d'), 'checkout' => $checkout->format('Y-m-d')];
            }
        }
        return $periods;
    }
}

<?php
declare(strict_types=1);

namespace Refugio\Services;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DomainException;
use Refugio\Support\Money;

final class PricingEngine
{
    /**
     * @param array<string,mixed> $request
     * @param array<string,mixed> $settings
     * @param array<int,array<string,mixed>> $seasons
     * @param array<int,array<string,mixed>> $specialDates
     * @param array<int,array<string,mixed>> $rules
     */
    public function calculate(array $request, array $settings, array $seasons = [], array $specialDates = [], array $rules = [], ?array $coupon = null, bool $public = true): array
    {
        $checkin = $this->date((string) ($request['checkin'] ?? ''), 'check-in');
        $checkout = $this->date((string) ($request['checkout'] ?? ''), 'check-out');
        if ($checkout <= $checkin) {
            throw new DomainException('O check-out deve ser posterior ao check-in.');
        }
        $nights = (int) $checkin->diff($checkout)->days;
        $guests = filter_var($request['guests'] ?? null, FILTER_VALIDATE_INT);
        if ($guests === false || $guests < 1 || $guests > (int) ($settings['max_guests'] ?? 10)) {
            throw new DomainException('A quantidade de hóspedes deve ficar entre 1 e 10.');
        }
        $minimum = isset($settings['minimum_nights']) ? (int) $settings['minimum_nights'] : null;
        $maximum = isset($settings['maximum_nights']) ? (int) $settings['maximum_nights'] : null;
        if ($minimum !== null && $minimum > 0 && $nights < $minimum) {
            throw new DomainException("A estadia mínima é de {$minimum} noite(s).");
        }
        if ($maximum !== null && $maximum > 0 && $nights > $maximum) {
            throw new DomainException("A estadia máxima é de {$maximum} noite(s).");
        }
        $missing = [];
        foreach (['guests_included_in_base_rate','extra_guest_fee_mode'] as $key) {
            if (!array_key_exists($key, $settings) || $settings[$key] === null || $settings[$key] === '') {
                $missing[] = strtoupper($key);
            }
        }
        if ($public && ($missing !== [] || empty($settings['public_pricing_enabled']))) {
            throw new DomainException('O cálculo público ainda não foi liberado. Configuração pendente: ' . implode(', ', $missing ?: ['PUBLIC_PRICING_ENABLED']) . '.');
        }

        $baseCents = Money::toCents((string) ($settings['base_daily_rate'] ?? '0'));
        $items = [];
        $appliedRules = [];
        $daily = [];
        $period = new DatePeriod($checkin, new DateInterval('P1D'), $checkout);
        foreach ($period as $night) {
            $rate = $baseCents;
            $nightRules = [];
            $special = $this->bestDateMatch($night, $specialDates);
            if ($special !== null) {
                $rate = Money::toCents((string) $special['daily_rate']);
                $nightRules[] = $this->ruleSnapshot('SPECIAL_DATE', $special, $rate - $baseCents);
            } else {
                foreach ($this->dateMatches($night, $seasons) as $season) {
                    $before = $rate;
                    $rate = $this->adjust($rate, (string) $season['adjustment_type'], (string) $season['adjustment_value'], true);
                    $nightRules[] = $this->ruleSnapshot('SEASON', $season, $rate - $before);
                    if (empty($season['stackable'])) {
                        break;
                    }
                }
            }
            foreach ($rules as $rule) {
                if (!$this->ruleMatches($rule, $night, $nights, $checkin)) {
                    continue;
                }
                $before = $rate;
                $rate = $this->adjust($rate, (string) $rule['adjustment_type'], (string) $rule['adjustment_value']);
                $nightRules[] = $this->ruleSnapshot('RULE', $rule, $rate - $before);
                if (empty($rule['stackable'])) {
                    break;
                }
            }
            $daily[] = ['date' => $night->format('Y-m-d'), 'amount' => Money::fromCents(max(0, $rate))];
            array_push($appliedRules, ...$nightRules);
        }
        $dailyCents = array_sum(array_map(static fn(array $day): int => Money::toCents($day['amount']), $daily));
        $items[] = $this->item('DAILY_RATE', "{$nights} diária(s)", $nights, $dailyCents);

        $cleaning = Money::toCents((string) ($settings['cleaning_fee'] ?? '0'));
        if ($cleaning > 0) {
            $items[] = $this->item('CLEANING', 'Taxa de limpeza', 1, $cleaning);
        }
        $included = max(0, (int) ($settings['guests_included_in_base_rate'] ?? $guests));
        $extraGuests = max(0, $guests - $included);
        $extraUnit = Money::toCents((string) ($settings['extra_guest_fee'] ?? '0'));
        $extraMultiplier = ($settings['extra_guest_fee_mode'] ?? null) === 'PER_NIGHT' ? $nights : 1;
        $extraTotal = $extraGuests * $extraUnit * $extraMultiplier;
        if ($extraTotal > 0) {
            $items[] = $this->item('EXTRA_GUEST', "Adicional para {$extraGuests} hóspede(s)", $extraGuests * $extraMultiplier, $extraTotal);
        }
        $pets = max(0, (int) ($request['pets'] ?? 0));
        $petUnit = Money::toCents((string) ($settings['pet_fee'] ?? '0'));
        if ($pets > 0 && empty($settings['pets_allowed'])) {
            throw new DomainException('Pets não estão habilitados para esta propriedade.');
        }
        if (isset($settings['max_pets']) && $settings['max_pets'] !== null && $pets > (int) $settings['max_pets']) {
            throw new DomainException('A quantidade de pets excede o limite configurado.');
        }
        if ($pets > 0 && $petUnit > 0) {
            $items[] = $this->item('PET', "Taxa para {$pets} pet(s)", $pets, $pets * $petUnit);
        }

        $subtotal = array_sum(array_map(static fn(array $item): int => Money::toCents($item['total_amount']), $items));
        $discount = 0;
        if ($coupon !== null && $this->couponValid($coupon, $subtotal)) {
            $discount = max(0, $subtotal - $this->adjust($subtotal, (string) $coupon['discount_type'], '-' . ltrim((string) $coupon['discount_value'], '-')));
            $discount = min($discount, $subtotal);
            if ($discount > 0) {
                $items[] = ['item_type' => 'DISCOUNT', 'description' => 'Cupom ' . (string) $coupon['code'], 'quantity' => '1.00', 'unit_amount' => Money::fromCents(-$discount), 'total_amount' => Money::fromCents(-$discount)];
                $appliedRules[] = $this->ruleSnapshot('COUPON', $coupon, -$discount);
            }
        }
        $total = max(0, $subtotal - $discount);
        return [
            'currency' => (string) ($settings['currency'] ?? 'BRL'),
            'checkin' => $checkin->format('Y-m-d'), 'checkout' => $checkout->format('Y-m-d'),
            'nights' => $nights, 'guests' => $guests, 'pets' => $pets,
            'daily_rates' => $daily, 'items' => $items, 'applied_rules' => $appliedRules,
            'subtotal' => Money::fromCents($subtotal), 'discount_total' => Money::fromCents($discount), 'total' => Money::fromCents($total),
            'configuration_complete' => $missing === [], 'missing_settings' => $missing,
        ];
    }

    private function date(string $value, string $label): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new DomainException("Informe uma data válida para {$label}.");
        }
        return $date;
    }

    private function dateMatches(DateTimeImmutable $date, array $rows): array
    {
        $matches = array_values(array_filter($rows, static fn(array $row): bool => !empty($row['ativo']) && $date->format('Y-m-d') >= $row['starts_on'] && $date->format('Y-m-d') <= $row['ends_on']));
        usort($matches, static fn(array $a, array $b): int => ((int) $a['priority']) <=> ((int) $b['priority']));
        return $matches;
    }

    private function bestDateMatch(DateTimeImmutable $date, array $rows): ?array
    {
        return $this->dateMatches($date, $rows)[0] ?? null;
    }

    private function ruleMatches(array $rule, DateTimeImmutable $night, int $nights, DateTimeImmutable $checkin): bool
    {
        if (empty($rule['ativo'])) return false;
        $conditions = is_array($rule['conditions'] ?? null) ? $rule['conditions'] : json_decode((string) ($rule['conditions_json'] ?? '{}'), true);
        $conditions = is_array($conditions) ? $conditions : [];
        return match ($rule['rule_type'] ?? '') {
            'WEEKEND' => in_array((int) $night->format('N'), array_map('intval', $conditions['weekdays'] ?? [5,6]), true),
            'LENGTH_OF_STAY' => $nights >= (int) ($conditions['min_nights'] ?? PHP_INT_MAX) && $nights <= (int) ($conditions['max_nights'] ?? PHP_INT_MAX),
            'ADVANCE' => (int) (new DateTimeImmutable('today'))->diff($checkin)->format('%r%a') >= (int) ($conditions['min_days'] ?? PHP_INT_MAX),
            'LAST_MINUTE' => (int) (new DateTimeImmutable('today'))->diff($checkin)->format('%r%a') <= (int) ($conditions['max_days'] ?? -1),
            default => false,
        };
    }

    private function adjust(int $cents, string $type, string $value, bool $allowFixedRate = false): int
    {
        if ($allowFixedRate && $type === 'FIXED_RATE') return Money::toCents($value);
        if ($type === 'PERCENT') return (int) round($cents * (1 + ((float) $value / 100)), 0, PHP_ROUND_HALF_UP);
        if ($type === 'FIXED_AMOUNT') return $cents + Money::toCents($value);
        return $cents;
    }

    private function item(string $type, string $description, int $quantity, int $total): array
    {
        $unit = $quantity > 0 ? (int) round($total / $quantity, 0, PHP_ROUND_HALF_UP) : $total;
        return ['item_type' => $type, 'description' => $description, 'quantity' => number_format($quantity, 2, '.', ''), 'unit_amount' => Money::fromCents($unit), 'total_amount' => Money::fromCents($total)];
    }

    private function ruleSnapshot(string $type, array $row, int $effect): array
    {
        return ['source_type' => $type, 'source_id' => isset($row['id']) ? (int) $row['id'] : null, 'source_name' => (string) ($row['nome'] ?? $row['name'] ?? $row['code'] ?? $type), 'amount_effect' => Money::fromCents($effect), 'snapshot' => $row];
    }

    private function couponValid(array $coupon, int $subtotal): bool
    {
        if (empty($coupon['ativo'])) return false;
        $now = date('Y-m-d H:i:s');
        if (!empty($coupon['starts_at']) && $coupon['starts_at'] > $now) return false;
        if (!empty($coupon['ends_at']) && $coupon['ends_at'] < $now) return false;
        if ($coupon['max_uses'] !== null && (int) $coupon['uses_count'] >= (int) $coupon['max_uses']) return false;
        return empty($coupon['minimum_total']) || $subtotal >= Money::toCents((string) $coupon['minimum_total']);
    }
}

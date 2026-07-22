<?php
declare(strict_types=1);

namespace Refugio\Support;

use InvalidArgumentException;

final class Money
{
    public static function normalize(int|string $value): string
    {
        return self::fromCents(self::toCents($value));
    }

    public static function toCents(int|string $value): int
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            throw new InvalidArgumentException('Valor monetario vazio.');
        }
        $negative = str_starts_with($raw, '-');
        if ($negative) {
            $raw = substr($raw, 1);
        }
        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            $raw = str_replace('.', '', $raw);
        }
        $raw = str_replace(',', '.', $raw);
        if (!preg_match('/^\d{1,12}(?:\.\d{1,2})?$/', $raw)) {
            throw new InvalidArgumentException('Valor monetario invalido.');
        }
        [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '');
        $cents = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
        return $negative ? -$cents : $cents;
    }

    public static function fromCents(int $cents): string
    {
        $negative = $cents < 0;
        $absolute = abs($cents);
        return ($negative ? '-' : '') . intdiv($absolute, 100) . '.' . str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function add(int|string ...$values): string
    {
        return self::fromCents(array_sum(array_map(self::toCents(...), $values)));
    }

    public static function subtract(int|string $left, int|string $right): string
    {
        return self::fromCents(self::toCents($left) - self::toCents($right));
    }
}

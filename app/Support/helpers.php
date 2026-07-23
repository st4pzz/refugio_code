<?php
declare(strict_types=1);

use Refugio\Support\Csrf;
use Refugio\Services\AuthorizationService;
use Refugio\Support\Money as MoneyValue;

if (!function_exists('mb_strlen')) {
    function mb_strlen(string $value): int { return strlen($value); }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $value, int $start, ?int $length = null): string { return substr($value, $start, $length); }
}
if (!class_exists('ReservationStatus', false)) {
    class_alias(Refugio\Models\ReservationStatus::class, 'ReservationStatus');
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_field(): string
{
    return Csrf::field();
}

function redirect(string $url): never
{
    header('Location: ' . $url, true, 303);
    exit;
}

function money(null|int|float|string $value): string
{
    if (!is_float($value)) {
        try {
            $cents = MoneyValue::toCents((string) ($value ?? '0'));
            $negative = $cents < 0;
            $absolute = abs($cents);
            return ($negative ? '-R$ ' : 'R$ ') . number_format(intdiv($absolute, 100), 0, ',', '.') . ',' . str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
        } catch (\InvalidArgumentException) {
        }
    }
    return 'R$ ' . number_format((float) $value, 2, ',', '.');
}

function can(string $permission): bool
{
    return AuthorizationService::currentAllows($permission);
}

function base_url(string $path = ''): string
{
    global $config;
    $base = rtrim((string) ($config['url'] ?? ''), '/');
    $normalizedPath = ltrim($path, '/');

    // Em testes locais usamos URL same-origin para preservar host e porta e
    // impedir que um APP_URL de produção redirecione assets/formulários locais.
    $requestHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $isLocalRequest = preg_match('/^(?:localhost|127\.0\.0\.1|\[::1\])(?::\d{1,5})?$/i', $requestHost) === 1;
    if (PHP_SAPI !== 'cli' && ($base === 'http://localhost' || $isLocalRequest)) {
        return '/' . $normalizedPath;
    }

    return $base . '/' . $normalizedPath;
}

function flash(string $key, ?string $value = null): ?string
{
    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }
    $message = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $message;
}

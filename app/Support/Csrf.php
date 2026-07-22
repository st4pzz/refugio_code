<?php
declare(strict_types=1);

namespace Refugio\Support;

use RuntimeException;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function verify(?string $token): void
    {
        if (!$token || !hash_equals($_SESSION['_csrf'] ?? '', $token)) {
            throw new RuntimeException('Sessao expirada. Atualize a pagina e tente novamente.');
        }
    }
}

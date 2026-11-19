<?php
declare(strict_types=1);

namespace Refugio\Support;

final class Security
{
    public static function startSession(array $config): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('refugio_session');
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => (bool) $config['session_secure'], 'httponly' => true, 'samesite' => 'Lax']);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();
    }

    public static function sendHeaders(bool $checkoutMeasurement = false): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        $directives = [
            'default-src' => "'self'",
            'img-src' => "'self' data:",
            'style-src' => "'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com",
            'font-src' => "'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com",
            'script-src' => "'self' 'unsafe-inline'",
            'connect-src' => "'self'",
        ];
        if ($checkoutMeasurement) {
            $tagManager = 'https://www.googletagmanager.com';
            $tagManagerPreview = 'https://tagmanager.google.com';
            $ads = 'https://www.googleadservices.com https://www.google.com';
            $adsRequests = "$tagManager $ads https://www.google.com.br https://googleads.g.doubleclick.net https://pagead2.googlesyndication.com";
            $directives['script-src'] .= " $tagManager $tagManagerPreview $ads";
            $directives['style-src'] .= " $tagManager $tagManagerPreview";
            $directives['font-src'] .= ' data:';
            $directives['img-src'] .= " $adsRequests https://ssl.gstatic.com https://www.gstatic.com";
            $directives['connect-src'] .= " $adsRequests https://ad.doubleclick.net";
            $directives['frame-src'] = "'self' $tagManager";
        }
        $policy = [];
        foreach ($directives as $directive => $sources) {
            $policy[] = "$directive $sources";
        }
        header('Content-Security-Policy: ' . implode('; ', $policy));
    }

    public static function clientIp(): string
    {
        return substr($_SERVER['REMOTE_ADDR'] ?? 'cli', 0, 45);
    }
}

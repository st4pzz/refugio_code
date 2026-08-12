<?php
declare(strict_types=1);

use Refugio\Support\Env;

return [
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => Env::bool('APP_DEBUG', false),
    'url' => rtrim(Env::get('APP_URL', 'http://localhost'), '/'),
    'timezone' => Env::get('APP_TIMEZONE', 'America/Sao_Paulo'),
    'currency' => Env::get('APP_CURRENCY', 'BRL'),
    'app_key' => Env::get('APP_KEY', ''),
    'encryption_key' => Env::get('MARKETING_ENCRYPTION_KEY', ''),
    'session_secure' => Env::bool('SESSION_SECURE', true),
    'max_guests' => Env::int('MAX_GUESTS', 10),
    'cpf_required' => Env::bool('CPF_REQUIRED', false),
    'upload_max_bytes' => Env::int('UPLOAD_MAX_MB', 8) * 1024 * 1024,
    'whatsapp_media_max_bytes' => max(1, Env::int('WHATSAPP_MEDIA_MAX_MB', 20)) * 1024 * 1024,
    'conversations_realtime_interval_seconds' => max(5, min(60, Env::int('CONVERSATIONS_REALTIME_INTERVAL_SECONDS', 10))),
    'keep_receipt_after_expiry' => Env::bool('KEEP_RECEIPT_AFTER_EXPIRY', true),
    'contact_whatsapp' => Env::get('CONTACT_WHATSAPP', '5516997376487'),
    'admin_email' => Env::get('ADMIN_EMAIL', ''),
    'review_expiration_days' => max(1, Env::int('REVIEW_INVITATION_EXPIRATION_DAYS', 90)),
    'review_delay_hours' => max(0, Env::int('REVIEW_INVITATION_DELAY_HOURS', 24)),
    'review_reminder_days' => max(1, Env::int('REVIEW_REMINDER_DELAY_DAYS', 3)),
];

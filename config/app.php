<?php
declare(strict_types=1);

use Refugio\Support\Env;

return [
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => Env::bool('APP_DEBUG', false),
    'url' => rtrim(Env::get('APP_URL', 'http://localhost'), '/'),
    'timezone' => Env::get('APP_TIMEZONE', 'America/Sao_Paulo'),
    'app_key' => Env::get('APP_KEY', ''),
    'session_secure' => Env::bool('SESSION_SECURE', true),
    'max_guests' => Env::int('MAX_GUESTS', 10),
    'cpf_required' => Env::bool('CPF_REQUIRED', false),
    'upload_max_bytes' => Env::int('UPLOAD_MAX_MB', 8) * 1024 * 1024,
    'keep_receipt_after_expiry' => Env::bool('KEEP_RECEIPT_AFTER_EXPIRY', true),
    'contact_whatsapp' => Env::get('CONTACT_WHATSAPP', '5516996212350'),
    'admin_email' => Env::get('ADMIN_EMAIL', ''),
];

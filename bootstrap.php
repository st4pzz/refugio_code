<?php
declare(strict_types=1);

define('BASE_PATH', __DIR__);

$composerAutoload = BASE_PATH . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'Refugio\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require_once BASE_PATH . '/app/Support/helpers.php';
Refugio\Support\Env::load(BASE_PATH . '/.env');
$config = require BASE_PATH . '/config/app.php';
date_default_timezone_set($config['timezone']);

if (PHP_SAPI !== 'cli') {
    Refugio\Support\Security::startSession($config);
    Refugio\Support\Security::sendHeaders();
    $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    if (!str_starts_with($requestPath, '/admin') && !str_starts_with($requestPath, '/api')) {
        Refugio\Services\AttributionService::captureRequest();
    }
}

ini_set('display_errors', $config['debug'] ? '1' : '0');
error_reporting(E_ALL);

set_exception_handler(static function (Throwable $error) use ($config): void {
    error_log(sprintf('[reservas] %s em %s:%d', $error->getMessage(), $error->getFile(), $error->getLine()));
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, ($config['debug'] ? (string) $error : $error->getMessage()) . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    if ($config['debug']) {
        echo '<pre>' . htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        require BASE_PATH . '/app/Views/public/error.php';
    }
});

return $config;

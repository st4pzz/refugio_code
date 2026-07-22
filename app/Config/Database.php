<?php
declare(strict_types=1);

namespace Refugio\Config;

use PDO;
use Refugio\Support\Env;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection) {
            return self::$connection;
        }
        if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
            throw new RuntimeException('O driver PDO MySQL nao esta instalado neste ambiente.');
        }
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', Env::get('DB_HOST', '127.0.0.1'), Env::int('DB_PORT', 3306), Env::get('DB_DATABASE'), Env::get('DB_CHARSET', 'utf8mb4'));
        self::$connection = new PDO($dsn, Env::get('DB_USERNAME'), Env::get('DB_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        self::$connection->exec("SET time_zone = '" . date('P') . "'");
        return self::$connection;
    }

    public static function setConnection(?PDO $pdo): void
    {
        self::$connection = $pdo;
    }
}

<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$config = require dirname(__DIR__) . '/bootstrap.php';
$db = Refugio\Config\Database::connection();
$db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (migration VARCHAR(190) PRIMARY KEY, checksum CHAR(64) NOT NULL, aplicada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
$executed = 0;
$files = glob(BASE_PATH . '/database/migrations/*_create_*.sql') ?: [];
sort($files, SORT_NATURAL);
if (!$files) throw new RuntimeException('Nenhuma migration encontrada.');
foreach ($files as $file) {
    $sql = file_get_contents($file);
    if ($sql === false) throw new RuntimeException('Migration nao encontrada: ' . basename($file));
    $name = basename($file);
    $checksum = hash('sha256', $sql);
    $check = $db->prepare('SELECT checksum FROM schema_migrations WHERE migration=?');
    $check->execute([$name]);
    $appliedChecksum = $check->fetchColumn();
    if ($appliedChecksum !== false) {
        if (!hash_equals((string) $appliedChecksum, $checksum)) {
            throw new RuntimeException('Migration ja aplicada foi alterada: ' . $name);
        }
        fwrite(STDOUT, 'Ignorada (ja aplicada): ' . $name . PHP_EOL);
        continue;
    }
    $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement === '' || preg_match('/^--[^\n]*$/', $statement)) continue;
        $db->exec($statement);
        $executed++;
    }
    $record = $db->prepare('INSERT INTO schema_migrations (migration,checksum) VALUES (?,?)');
    $record->execute([$name, $checksum]);
    fwrite(STDOUT, 'Aplicada: ' . $name . PHP_EOL);
}
fwrite(STDOUT, "Migrations concluidas ({$executed} instrucoes).\n");

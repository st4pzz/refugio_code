<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$config = require dirname(__DIR__) . '/bootstrap.php';
$db = Refugio\Config\Database::connection();
$file = BASE_PATH . '/database/migrations/001_create_reservas.sql';
$sql = file_get_contents($file);
if ($sql === false) throw new RuntimeException('Migration nao encontrada.');
$statements = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];
$executed = 0;
foreach ($statements as $statement) {
    $statement = trim($statement);
    if ($statement === '' || preg_match('/^--[^\n]*$/', $statement)) continue;
    $db->exec($statement);
    $executed++;
}
fwrite(STDOUT, "Migration concluida ({$executed} instrucoes).\n");

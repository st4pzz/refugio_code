<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$config = require dirname(__DIR__) . '/bootstrap.php';
$email = strtolower(trim((string) ($argv[1] ?? '')));
$name = trim((string) ($argv[2] ?? 'Administrador'));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php scripts/create_admin.php email@dominio.com \"Nome\"\n"); exit(1);
}
fwrite(STDOUT, 'Digite uma senha forte (minimo 12 caracteres): ');
$password = trim((string) fgets(STDIN));
if (strlen($password) < 12) { fwrite(STDERR, "A senha precisa ter pelo menos 12 caracteres.\n"); exit(1); }
$db = Refugio\Config\Database::connection();
$stmt = $db->prepare('SELECT id FROM usuarios_admin WHERE email=?'); $stmt->execute([$email]);
if ($stmt->fetchColumn()) { fwrite(STDERR, "Ja existe um administrador com esse e-mail.\n"); exit(1); }
$db->prepare('INSERT INTO usuarios_admin (nome,email,senha_hash) VALUES (?,?,?)')->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
fwrite(STDOUT, "Administrador criado com sucesso. A senha nao foi registrada em arquivo.\n");

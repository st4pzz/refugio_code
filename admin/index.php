<?php
declare(strict_types=1);
$config = require dirname(__DIR__) . '/bootstrap.php';
$controller = new Refugio\Controllers\AdminController($config);
$route = (string) ($_GET['route'] ?? 'dashboard');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($route === 'logout') $controller->logout();
    if ($route === 'login') $controller->login();
    if (str_starts_with($route, 'bloqueio-')) $controller->blockAction(substr($route, 9));
    $controller->action((int) ($_GET['id'] ?? 0), (string) ($_GET['action'] ?? ''));
}
match ($route) {
    'dashboard' => $controller->dashboard(), 'reservas' => $controller->reservations(),
    'detalhe' => $controller->detail((int) ($_GET['id'] ?? 0)), 'calendario' => $controller->calendar(),
    'comprovante' => $controller->receipt((int) ($_GET['pagamento'] ?? 0)), 'login' => $controller->loginForm(),
    default => $controller->dashboard(),
};

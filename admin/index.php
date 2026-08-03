<?php
declare(strict_types=1);

$config = require dirname(__DIR__) . '/bootstrap.php';
$admin = new Refugio\Controllers\AdminController($config);
$reviews = new Refugio\Controllers\AdminReviewController($config);
$conversations = new Refugio\Controllers\ConversationController($config);
$financial = new Refugio\Controllers\FinancialController($config);
$marketing = new Refugio\Controllers\MarketingController($config);
$customers = new Refugio\Controllers\CustomerController();
$settings = new Refugio\Controllers\SettingsController($config);
$operations = new Refugio\Controllers\OperationsController($config);
$whatsAppReservations = new Refugio\Controllers\WhatsAppReservationController($config);
$route = (string) ($_GET['route'] ?? 'dashboard');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($route === 'logout') $admin->logout();
    if ($route === 'login') $admin->login();
    if (str_starts_with($route, 'bloqueio-')) $admin->blockAction(substr($route, 9));
    if ($route === 'review-action') $reviews->action((int) ($_GET['id'] ?? 0), (string) ($_GET['action'] ?? ''));
    if ($route === 'review-invite') $reviews->invitation((int) ($_GET['id'] ?? 0), (string) ($_GET['action'] ?? ''));
    if ($route === 'conversa-acao') $conversations->action((int) ($_GET['id'] ?? 0), (string) ($_GET['action'] ?? ''));
    if ($route === 'conversa-templates') $conversations->syncTemplates();
    if ($route === 'financeiro-acao') $financial->action((string) ($_GET['action'] ?? ''));
    if ($route === 'marketing-conectar') $marketing->connect((string) ($_GET['provider'] ?? ''));
    if ($route === 'marketing-conta') $marketing->selectAccount((int) ($_GET['id'] ?? 0));
    if ($route === 'marketing-acao') $marketing->action((int) ($_GET['id'] ?? 0), (string) ($_GET['action'] ?? ''));
    if ($route === 'cliente-acao') $customers->action((int) ($_GET['id'] ?? 0), (string) ($_GET['action'] ?? ''));
    if ($route === 'configuracao-perfil') $settings->assignProfile();
    if ($route === 'operacao') $operations->action((string) ($_GET['action'] ?? ''));
    if ($route === 'pedido-whatsapp-acao') $whatsAppReservations->action((int) ($_GET['id'] ?? 0), (string) ($_GET['action'] ?? ''));
    $admin->action((int) ($_GET['id'] ?? 0), (string) ($_GET['action'] ?? ''));
}

match ($route) {
    'dashboard' => $admin->dashboard(),
    'reservas' => $admin->reservations(),
    'detalhe' => $admin->detail((int) ($_GET['id'] ?? 0)),
    'pedidos-whatsapp' => $whatsAppReservations->index(),
    'pedido-whatsapp-detalhe' => $whatsAppReservations->detail((int) ($_GET['id'] ?? 0)),
    'pedido-whatsapp-documento' => $whatsAppReservations->document((int) ($_GET['documento'] ?? 0)),
    'calendario' => $admin->calendar(),
    'avaliacoes' => $reviews->index(),
    'avaliacao-detalhe' => $reviews->detail((int) ($_GET['id'] ?? 0)),
    'comprovante' => $admin->receipt((int) ($_GET['pagamento'] ?? 0)),
    'clientes' => $customers->index(),
    'cliente-detalhe' => $customers->detail((int) ($_GET['id'] ?? 0)),
    'cliente-exportar' => $customers->export((int) ($_GET['id'] ?? 0)),
    'conversas' => $conversations->index(),
    'conversa-poll' => $conversations->poll((int) ($_GET['id'] ?? 0)),
    'conversa-midia' => $conversations->media((int) ($_GET['mensagem'] ?? 0)),
    'financeiro' => $financial->index(),
    'financeiro-exportar' => $financial->export(),
    'marketing' => $marketing->index(),
    'marketing-conectar' => $marketing->connect((string) ($_GET['provider'] ?? '')),
    'marketing-callback' => $marketing->callback((string) ($_GET['provider'] ?? '')),
    'marketing-contas' => $marketing->accounts((int) ($_GET['integracao'] ?? 0)),
    'configuracoes' => $settings->index(),
    'precos' => $operations->pricing(),
    'orcamentos' => $operations->quotes(),
    'contratos' => $operations->contracts(),
    'contrato-pdf' => $operations->contractDocument((int) ($_GET['id'] ?? 0)),
    'precheckins' => $operations->precheckins(),
    'automacoes' => $operations->automations(),
    'propriedade' => $operations->propertySettings(),
    'login' => $admin->loginForm(),
    default => $admin->dashboard(),
};

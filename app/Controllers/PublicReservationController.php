<?php
declare(strict_types=1);

namespace Refugio\Controllers;

use Refugio\Config\Database;
use Refugio\Repositories\ReservationRepository;
use Refugio\Repositories\CustomerRepository;
use Refugio\Services\AttributionService;
use Refugio\Services\RateLimiter;
use Refugio\Services\ReservationService;
use Refugio\Services\ValidationException;
use Refugio\Support\Csrf;
use Refugio\Support\Security;
use RuntimeException;
use Throwable;

final class PublicReservationController
{
    private ReservationRepository $repository;
    private ReservationService $service;

    public function __construct(private array $config)
    {
    }

    public function form(): void
    {
        $_SESSION['_request_idempotency'] ??= bin2hex(random_bytes(32));
        $errors = $_SESSION['_form_errors'] ?? [];
        $old = $_SESSION['_form_old'] ?? [];
        unset($_SESSION['_form_errors'], $_SESSION['_form_old']);
        require BASE_PATH . '/app/Views/public/request.php';
    }

    public function submit(): never
    {
        $this->boot();
        try {
            Csrf::verify($_POST['_csrf'] ?? null);
            (new RateLimiter(Database::connection()))->assertAllowed('request|' . Security::clientIp(), 5, 3600);
            $key = (string) ($_POST['_idempotency'] ?? '');
            if ($key === '' || !hash_equals($_SESSION['_request_idempotency'] ?? '', $key)) throw new RuntimeException('Formulario expirado. Atualize a pagina e tente novamente.');
            $reservation = $this->service->request($_POST, $key);
            $db = Database::connection();
            $clientId = (new CustomerRepository($db))->syncFromReservation($reservation);
            (new AttributionService($db))->linkReservation((int) $reservation['id'], $clientId);
            $_SESSION['_success_reservation'] = ['codigo' => $reservation['codigo'], 'token' => $reservation['token_publico']];
            redirect(base_url('reserva/sucesso'));
        } catch (ValidationException $e) {
            $_SESSION['_form_errors'] = $e->errors;
            $_SESSION['_form_old'] = $_POST;
            redirect(base_url('reserva/solicitar'));
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
            $_SESSION['_form_old'] = $_POST;
            redirect(base_url('reserva/solicitar'));
        }
    }

    public function success(): void
    {
        $reservation = $_SESSION['_success_reservation'] ?? null;
        if (!$reservation) redirect(base_url('reserva/solicitar'));
        require BASE_PATH . '/app/Views/public/success.php';
    }

    public function show(string $token): void
    {
        $this->boot();
        $reservation = $this->validToken($token);
        $payments = $this->repository->payments((int) $reservation['id']);
        require BASE_PATH . '/app/Views/public/reservation.php';
    }

    public function receipt(string $token): never
    {
        $this->boot();
        try {
            Csrf::verify($_POST['_csrf'] ?? null);
            (new RateLimiter(Database::connection()))->assertAllowed('receipt|' . Security::clientIp() . '|' . $token, 5, 3600);
            $reservation = $this->validToken($token);
            $this->service->uploadReceipt($reservation, (int) ($_POST['pagamento_id'] ?? 0), $_FILES['comprovante'] ?? []);
            flash('success', 'Comprovante recebido. A reserva sera confirmada apos a identificacao do pagamento.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect(base_url('reserva/' . rawurlencode($token) . '#comprovante'));
    }

    public function qrCode(string $token, int $paymentId): never
    {
        $this->boot();
        $reservation = $this->validToken($token);
        $payment = array_values(array_filter($this->repository->payments((int) $reservation['id']), static fn(array $p): bool => (int) $p['id'] === $paymentId))[0] ?? null;
        if (!$payment || empty($payment['qr_code_path'])) $this->notFound();
        $this->streamFile(BASE_PATH . '/' . $payment['qr_code_path'], 'image/png', 'qrcode-' . $reservation['codigo'] . '.png', false);
    }

    private function validToken(string $token): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{40,80}$/', $token)) $this->notFound();
        $reservation = $this->repository->findByToken($token);
        if (!$reservation) $this->notFound();
        return $reservation;
    }

    private function boot(): void
    {
        if (isset($this->repository)) return;
        $db = Database::connection();
        $this->repository = new ReservationRepository($db);
        $this->service = new ReservationService($db, $this->config);
    }

    private function notFound(): never
    {
        http_response_code(404);
        require BASE_PATH . '/app/Views/public/not-found.php';
        exit;
    }

    private function streamFile(string $path, string $fallbackMime, string $downloadName, bool $attachment): never
    {
        if (!is_file($path) || !str_starts_with(realpath($path) ?: '', realpath(BASE_PATH . '/storage') ?: 'x')) $this->notFound();
        $mime = function_exists('mime_content_type') ? mime_content_type($path) : $fallbackMime;
        header('Content-Type: ' . $mime); header('Content-Length: ' . filesize($path)); header('Cache-Control: private, no-store');
        header('Content-Disposition: ' . ($attachment ? 'attachment' : 'inline') . '; filename="' . preg_replace('/[^a-zA-Z0-9._-]/', '-', $downloadName) . '"');
        readfile($path); exit;
    }
}

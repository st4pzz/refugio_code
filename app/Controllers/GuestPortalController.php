<?php
declare(strict_types=1);

namespace Refugio\Controllers;

use PDO;
use Refugio\Config\Database;
use Refugio\Services\ContractSignatureWorkflowService;
use Refugio\Services\GuestPortalService;
use Refugio\Services\NotificationService;
use Refugio\Services\PreCheckinService;
use Refugio\Services\RateLimiter;
use Refugio\Services\ReservationAutomationService;
use Refugio\Services\UploadService;
use Refugio\Support\Csrf;
use Refugio\Support\Security;
use RuntimeException;
use Throwable;

final class GuestPortalController
{
    public function __construct(private array $config)
    {
    }

    public function show(string $token): void
    {
        try {
            $portal = (new GuestPortalService(Database::connection(), $this->config))->resolve($token);
            require BASE_PATH . '/app/Views/guest-portal/show.php';
        } catch (Throwable $error) {
            $this->error($error);
        }
    }

    public function precheckin(string $token): void
    {
        try {
            $db = Database::connection();
            $portalService = new GuestPortalService($db, $this->config);
            $portal = $portalService->resolve($token);
            $reservationId = $portalService->reservationId($token);
            $service = new PreCheckinService($db);
            $service->ensure($reservationId);
            $precheckin = $service->load($reservationId);
            require BASE_PATH . '/app/Views/guest-portal/precheckin.php';
        } catch (Throwable $error) {
            $this->error($error);
        }
    }

    public function savePrecheckin(string $token): never
    {
        try {
            Csrf::verify($_POST['_csrf'] ?? null);
            $db = Database::connection();
            (new RateLimiter($db))->assertAllowed('precheckin|' . Security::clientIp() . '|' . hash('sha256', $token), 20, 3600);
            $portal = new GuestPortalService($db, $this->config);
            $reservationId = $portal->reservationId($token);
            $service = new PreCheckinService($db);
            $service->save($reservationId, $_POST);
            if (($_POST['intent'] ?? 'save') === 'submit') {
                $accepted = is_array($_POST['rules'] ?? null) ? $_POST['rules'] : [];
                $service->submit($reservationId, $accepted, (string) ($_POST['responsible_name'] ?? ''), (string) ($_POST['responsible_cpf'] ?? ''), Security::clientIp(), $_SERVER['HTTP_USER_AGENT'] ?? '');
                flash('success', 'Pré-check-in enviado para análise.');
            } else {
                flash('success', 'Rascunho salvo.');
            }
        } catch (Throwable $error) {
            flash('error', $error->getMessage());
        }
        redirect(base_url('minha-reserva/' . rawurlencode($token) . '/pre-check-in'));
    }

    public function uploadSignedContract(string $token): never
    {
        try {
            Csrf::verify($_POST['_csrf'] ?? null);
            if (!isset($_POST['signed_on_gov'])) throw new RuntimeException('Confirme que o PDF foi assinado no Gov.br antes de enviar.');
            $db = Database::connection();
            (new RateLimiter($db))->assertAllowed('contract-upload|' . Security::clientIp() . '|' . hash('sha256', $token), 10, 3600);
            $portal = new GuestPortalService($db, $this->config);
            $reservationId = $portal->reservationId($token);
            $contract = $this->contract($db, $reservationId);
            $workflow = $this->workflow($db);
            $workflow->uploadGuestSigned((int) $contract['id'], $_FILES['signed_contract'] ?? [], Security::clientIp(), $_SERVER['HTTP_USER_AGENT'] ?? '');

            $stmt = $db->prepare('SELECT * FROM reservas WHERE id=?');
            $stmt->execute([$reservationId]);
            $reservation = $stmt->fetch();
            if ($reservation) {
                try {
                    (new NotificationService($db))->admin($reservation, 'CONTRATO_ASSINADO_HOSPEDE', 'O hóspede enviou o contrato assinado pelo Gov.br. Baixe o PDF no painel de contratos.', 'admin/contratos');
                } catch (Throwable $notificationError) {
                    error_log('[contract-upload-notification] ' . $notificationError->getMessage());
                }
            }
            try {
                (new ReservationAutomationService($db, $this->config))->emit('CONTRACT_SIGNED', $reservationId, [], 'contract-signed:' . $contract['id'] . ':guest-upload');
            } catch (Throwable $automationError) {
                error_log('[contract-upload-automation] ' . $automationError->getMessage());
            }
            flash('success', 'Contrato assinado enviado. Agora o proprietário fará a assinatura pelo Gov.br.');
        } catch (Throwable $error) {
            flash('error', $error->getMessage());
        }
        redirect(base_url('minha-reserva/' . rawurlencode($token) . '#contrato'));
    }

    public function contractPdf(string $token): never
    {
        try {
            $db = Database::connection();
            $portal = new GuestPortalService($db, $this->config);
            $reservationId = $portal->reservationId($token);
            $contract = $this->contract($db, $reservationId);
            $path = realpath(BASE_PATH . '/' . (string) $contract['pdf_path']);
            $storage = realpath(BASE_PATH . '/storage/contracts');
            if (!$path || !$storage || !str_starts_with(str_replace('\\', '/', $path), rtrim(str_replace('\\', '/', $storage), '/') . '/') || !is_file($path)) {
                throw new RuntimeException('PDF ainda não está disponível.');
            }
            $this->streamPdf($path, 'contrato-' . $contract['contract_number'] . '-para-assinar.pdf', false);
        } catch (Throwable $error) {
            $this->error($error);
            exit;
        }
    }

    public function signedContractPdf(string $token): never
    {
        try {
            $db = Database::connection();
            $portal = new GuestPortalService($db, $this->config);
            $reservationId = $portal->reservationId($token);
            $contract = $this->contract($db, $reservationId);
            $workflow = $this->workflow($db);
            $document = $workflow->latestDocument((int) $contract['id'], ContractSignatureWorkflowService::FULLY_SIGNED)
                ?? $workflow->latestDocument((int) $contract['id'], ContractSignatureWorkflowService::GUEST_SIGNED);
            if ($document === null) throw new RuntimeException('O contrato assinado ainda não está disponível.');
            $this->streamPdf($workflow->resolvePath($document), 'contrato-' . $contract['contract_number'] . '-assinado.pdf', false);
        } catch (Throwable $error) {
            $this->error($error);
            exit;
        }
    }

    private function contract(PDO $db, int $reservationId): array
    {
        $stmt = $db->prepare("SELECT * FROM reservation_contracts WHERE reservation_id=? AND status NOT IN ('SUPERSEDED','CANCELLED','EXPIRED') ORDER BY version_no DESC LIMIT 1");
        $stmt->execute([$reservationId]);
        return $stmt->fetch() ?: throw new RuntimeException('Contrato ainda não está disponível.');
    }

    private function workflow(PDO $db): ContractSignatureWorkflowService
    {
        return new ContractSignatureWorkflowService($db, new UploadService((int) $this->config['upload_max_bytes']));
    }

    private function streamPdf(string $path, string $filename, bool $inline = true): never
    {
        header('Content-Type: application/pdf');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . preg_replace('/[^A-Za-z0-9_.-]/', '-', $filename) . '"');
        readfile($path);
        exit;
    }

    private function error(Throwable $error): void
    {
        http_response_code(404);
        $message = $error->getMessage();
        require BASE_PATH . '/app/Views/guest-portal/error.php';
    }
}

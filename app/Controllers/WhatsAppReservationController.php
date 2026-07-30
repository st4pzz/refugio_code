<?php
declare(strict_types=1);

namespace Refugio\Controllers;

use DateTimeImmutable;
use PDO;
use Refugio\Config\Database;
use Refugio\Repositories\ReservationRepository;
use Refugio\Services\AuthorizationService;
use Refugio\Services\AvailabilityService;
use Refugio\Services\ConflictException;
use Refugio\Services\HistoryService;
use Refugio\Services\PropertySettingsService;
use Refugio\Services\ReservationDocumentService;
use Refugio\Services\ReservationService;
use Refugio\Support\Csrf;
use RuntimeException;
use Throwable;

final class WhatsAppReservationController
{
    private PDO $db;
    private ReservationDocumentService $documents;

    public function __construct(private array $config)
    {
        $this->db = Database::connection();
        $this->documents = new ReservationDocumentService($this->db, $config);
    }

    public function index(): void
    {
        AuthorizationService::requirePermission('reservas.view');
        $requests = $this->db->query("SELECT r.*,
            (SELECT COUNT(*) FROM reservation_documents d WHERE d.reservation_id=r.id) document_count,
            (SELECT MAX(d.created_at) FROM reservation_documents d WHERE d.reservation_id=r.id) last_document_at
            FROM reservas r
            WHERE r.origem='MANUAL' AND EXISTS (
                SELECT 1 FROM historico_reserva h WHERE h.reserva_id=r.id AND h.acao='PEDIDO_WHATSAPP_CRIADO'
            )
            ORDER BY r.created_at DESC LIMIT 150")->fetchAll();
        $pricing = $this->db->query('SELECT cleaning_fee FROM property_pricing_settings WHERE id=1')->fetch() ?: [];
        $settings = (new PropertySettingsService($this->db))->values();
        $requestKey = bin2hex(random_bytes(24));
        $defaultValidity = (new DateTimeImmutable('+24 hours'))->format('Y-m-d\TH:i');
        $defaultPolicy = (string) ($settings['CANCELLATION_POLICY'] ?? 'Em caso de cancelamento, entre em contato para avaliação conforme o prazo e as condições informadas no pedido.');
        $defaultCleaningFee = (string) ($pricing['cleaning_fee'] ?? '0.00');
        require BASE_PATH . '/app/Views/admin/whatsapp-reservations.php';
    }

    public function detail(int $id): void
    {
        AuthorizationService::requirePermission('reservas.view');
        $reservation = $this->reservation($id);
        $payments = (new ReservationRepository($this->db))->payments($id);
        $documents = $this->documents->documentsForReservation($id);
        $proposalDocument = $this->documents->latestDocument($id, ReservationDocumentService::PROPOSAL);
        $paymentDocument = $this->documents->latestDocument($id, ReservationDocumentService::PAYMENT_REQUEST);
        $activeConversation = $this->documents->activeConversation((string) $reservation['telefone']);
        $availability = new AvailabilityService($this->db);
        $conflicts = $availability->conflicts($reservation['checkin'], $reservation['checkout'], $id);
        $pendingConflicts = $availability->pendingConflicts($reservation['checkin'], $reservation['checkout'], $id);
        $settings = (new PropertySettingsService($this->db))->values();
        require BASE_PATH . '/app/Views/admin/whatsapp-reservation-detail.php';
    }

    public function action(int $id, string $action): never
    {
        AuthorizationService::requirePermission($action === 'criar' ? 'reservas.create' : 'reservas.manage');
        $redirect = 'admin/pedidos-whatsapp';
        try {
            Csrf::verify($_POST['_csrf'] ?? null);
            $userId = (int) $_SESSION['admin_id'];
            if ($action === 'criar') {
                $key = preg_match('/^[a-f0-9]{48}$/', (string) ($_POST['request_key'] ?? ''))
                    ? (string) $_POST['request_key']
                    : bin2hex(random_bytes(24));
                $reservation = (new ReservationService($this->db, $this->config))->createManualProposal($_POST, $key, $userId);
                $id = (int) $reservation['id'];
                $redirect = 'admin/pedidos-whatsapp/' . $id;
                if (!$this->documents->latestDocument($id, ReservationDocumentService::PROPOSAL)) {
                    $this->documents->generate($id, ReservationDocumentService::PROPOSAL, $userId, (array) ($reservation['_proposal'] ?? []));
                }
                flash('success', 'Pedido criado e PDF inicial emitido.');
            } elseif ($action === 'gerar-proposta') {
                $validUntil = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', (string) ($_POST['validade_proposta'] ?? ''));
                if (!$validUntil || $validUntil <= new DateTimeImmutable()) throw new RuntimeException('Informe uma nova validade futura.');
                $this->documents->generate($id, ReservationDocumentService::PROPOSAL, $userId, ['valid_until' => $validUntil->format('Y-m-d H:i:s')]);
                $redirect = 'admin/pedidos-whatsapp/' . $id;
                flash('success', 'Nova versão da proposta emitida.');
            } elseif ($action === 'aprovar-cobranca') {
                (new ReservationService($this->db, $this->config))->approve($id, $_POST, $_FILES['qr_code'] ?? null, $userId);
                (new HistoryService($this->db))->log(
                    $id,
                    'ACEITE_CLIENTE_REGISTRADO',
                    'AGUARDANDO_PAGAMENTO',
                    'AGUARDANDO_PAGAMENTO',
                    ['canal' => 'WHATSAPP'],
                    $userId
                );
                $this->documents->generate($id, ReservationDocumentService::PAYMENT_REQUEST, $userId);
                $redirect = 'admin/pedidos-whatsapp/' . $id;
                flash('success', 'Pedido aprovado, cobrança criada e PDF de pagamento emitido.');
            } elseif ($action === 'gerar-cobranca') {
                $this->documents->generate($id, ReservationDocumentService::PAYMENT_REQUEST, $userId);
                $redirect = 'admin/pedidos-whatsapp/' . $id;
                flash('success', 'Nova versão das instruções de pagamento emitida.');
            } elseif (in_array($action, ['enviar-proposta', 'enviar-cobranca'], true)) {
                AuthorizationService::requirePermission('conversas.reply');
                $documentId = (int) ($_POST['document_id'] ?? 0);
                $document = $this->documents->document($documentId);
                $expected = $action === 'enviar-proposta' ? ReservationDocumentService::PROPOSAL : ReservationDocumentService::PAYMENT_REQUEST;
                if (!$document || (int) $document['reservation_id'] !== $id || $document['document_type'] !== $expected) {
                    throw new RuntimeException('Documento selecionado não pertence a este pedido.');
                }
                $this->documents->send($documentId, $userId);
                $redirect = 'admin/pedidos-whatsapp/' . $id;
                flash('success', 'PDF enviado na conversa do WhatsApp.');
            } else {
                throw new RuntimeException('Ação de pedido via WhatsApp inválida.');
            }
        } catch (ConflictException) {
            flash('error', 'As datas possuem conflito ativo. Revise o calendário antes de continuar.');
            if ($id > 0) $redirect = 'admin/pedidos-whatsapp/' . $id;
        } catch (Throwable $error) {
            flash('error', $error->getMessage());
            if ($id > 0) $redirect = 'admin/pedidos-whatsapp/' . $id;
        }
        redirect(base_url($redirect));
    }

    public function document(int $documentId): never
    {
        AuthorizationService::requirePermission('reservas.view');
        $document = $this->documents->document($documentId) ?? throw new RuntimeException('PDF de reserva não encontrado.');
        $path = $this->documents->absoluteDocumentPath((string) $document['storage_path']);
        $prefix = $document['document_type'] === ReservationDocumentService::PROPOSAL ? 'pedido-reserva-' : 'pagamento-reserva-';
        $filename = $prefix . preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $document['codigo']) . '-v' . (int) $document['version_no'] . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        readfile($path);
        exit;
    }

    private function reservation(int $id): array
    {
        $stmt = $this->db->prepare("SELECT * FROM reservas WHERE id=? AND origem='MANUAL'");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: throw new RuntimeException('Pedido de reserva via WhatsApp não encontrado.');
    }
}

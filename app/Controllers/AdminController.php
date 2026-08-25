<?php
declare(strict_types=1);

namespace Refugio\Controllers;

use DateTimeImmutable;
use PDO;
use Refugio\Config\Database;
use Refugio\Models\ReservationStatus;
use Refugio\Repositories\ReservationRepository;
use Refugio\Repositories\ReviewRepository;
use Refugio\Services\AuthService;
use Refugio\Services\AuthorizationService;
use Refugio\Services\AvailabilityService;
use Refugio\Services\ConflictException;
use Refugio\Services\NotificationService;
use Refugio\Services\ReservationService;
use Refugio\Services\ReviewEligibilityService;
use Refugio\Support\Csrf;
use RuntimeException;
use Throwable;

final class AdminController
{
    private PDO $db;
    private ReservationRepository $repository;
    private ReservationService $service;

    public function __construct(private array $config)
    {
    }

    public function loginForm(): void
    {
        if (!empty($_SESSION['admin_id'])) redirect(base_url('admin'));
        require BASE_PATH . '/app/Views/admin/login.php';
    }

    public function login(): never
    {
        $this->boot();
        try {
            Csrf::verify($_POST['_csrf'] ?? null);
            if (!(new AuthService($this->db))->attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['senha'] ?? ''))) throw new RuntimeException('E-mail ou senha invalidos.');
            redirect(base_url('admin'));
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
            redirect(base_url('admin/login.php'));
        }
    }

    public function logout(): never
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        AuthService::logout();
        redirect(base_url('admin/login.php'));
    }

    public function dashboard(): void
    {
        AuthorizationService::requirePermission('dashboard.view');
        $this->boot();
        $metrics = [
            'pendentes' => $this->scalar("SELECT COUNT(*) FROM reservas WHERE status='AGUARDANDO_APROVACAO'"),
            'pagamento' => $this->scalar("SELECT COUNT(*) FROM reservas WHERE status='AGUARDANDO_PAGAMENTO'"),
            'comprovantes' => $this->scalar("SELECT COUNT(*) FROM reservas WHERE status='COMPROVANTE_ENVIADO'"),
            'confirmadas' => $this->scalar("SELECT COUNT(*) FROM reservas WHERE status='RESERVA_CONFIRMADA'"),
            'proximas' => $this->scalar("SELECT COUNT(*) FROM reservas WHERE status='RESERVA_CONFIRMADA' AND checkin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"),
            'vencidos' => $this->scalar("SELECT COUNT(*) FROM pagamentos WHERE status='PENDENTE' AND data_vencimento<NOW()"),
            'receita' => $this->scalar("SELECT COALESCE(SUM(valor),0) FROM pagamentos WHERE status='CONFIRMADO' AND data_confirmacao>=DATE_FORMAT(CURDATE(),'%Y-%m-01')"),
        ];
        $recent = $this->db->query('SELECT * FROM reservas ORDER BY created_at DESC LIMIT 8')->fetchAll();
        require BASE_PATH . '/app/Views/admin/dashboard.php';
    }

    public function reservations(): void
    {
        AuthorizationService::requirePermission('reservas.view');
        $this->boot();
        $filters = array_intersect_key($_GET, array_flip(['q','status','origem','inicio','fim','ordem']));
        $page = max(1, (int) ($_GET['pagina'] ?? 1));
        $result = $this->repository->paginate($filters, $page);
        require BASE_PATH . '/app/Views/admin/reservations.php';
    }

    public function detail(int $id): void
    {
        AuthorizationService::requirePermission('reservas.view');
        $this->boot();
        $reservation = $this->repository->find($id);
        if (!$reservation) { http_response_code(404); throw new RuntimeException('Reserva nao encontrada.'); }
        $payments = $this->repository->payments($id);
        $history = $this->repository->history($id);
        $notifications = $this->repository->notifications($id);
        $availability = new AvailabilityService($this->db);
        $conflicts = $availability->conflicts($reservation['checkin'], $reservation['checkout'], $id);
        $pendingConflicts = $availability->pendingConflicts($reservation['checkin'], $reservation['checkout'], $id);
        $reviewRepository = new ReviewRepository($this->db);
        $reviewInvitation = $reviewRepository->invitationByReservation($id);
        $reviewExisting = $reviewRepository->reviewByReservation($id);
        $reviewEligibility = (new ReviewEligibilityService($this->db, $this->config))->check($reservation, $reviewExisting);
        $reviewWindow = (new ReviewEligibilityService($this->db, $this->config))->invitationWindow($reservation);
        require BASE_PATH . '/app/Views/admin/detail.php';
    }

    public function action(int $id, string $action): never
    {
        AuthorizationService::requirePermission(in_array($action, ['confirmar-pagamento','recusar-comprovante'], true) ? 'financeiro.manage' : 'reservas.manage');
        $this->boot();
        try {
            Csrf::verify($_POST['_csrf'] ?? null);
            $userId = (int) $_SESSION['admin_id'];
            if ($action === 'aprovar') {
                $result = $this->service->approve($id, $_POST, $_FILES['qr_code'] ?? null, $userId);
                if (($result['_delivery']['email'] ?? false) === true) {
                    flash('success', 'Reserva aprovada, cobrança criada e e-mail enviado ao cliente.');
                } else {
                    flash('error', 'A reserva foi aprovada e a cobrança foi criada, mas o e-mail não pôde ser enviado. A falha foi registrada e uma nova tentativa ficou na fila; você também pode usar “Reenviar e-mail”.');
                }
            } else {
                match ($action) {
                    'recusar' => $this->service->refuse($id, (string) ($_POST['motivo'] ?? ''), $userId),
                    'confirmar-pagamento' => $this->service->confirmPayment($id, (int) ($_POST['pagamento_id'] ?? 0), (string) ($_POST['observacoes'] ?? ''), $userId),
                    'recusar-comprovante' => $this->service->rejectReceipt($id, (int) ($_POST['pagamento_id'] ?? 0), (string) ($_POST['motivo'] ?? ''), $userId),
                    'cancelar' => $this->service->cancel($id, (string) ($_POST['motivo'] ?? ''), $userId),
                    'finalizar' => $this->service->finish($id, $userId),
                    'observacoes' => $this->service->updateInternalNotes($id, (string) ($_POST['observacoes_internas'] ?? ''), $userId),
                    'alterar' => $this->service->updateReservation($id, $_POST, $userId),
                    'pagamentos' => $this->service->addPayment($id, $_POST, $_FILES['qr_code'] ?? null, $userId),
                    'reenviar-notificacao' => $this->resend($id),
                    default => throw new RuntimeException('Acao administrativa invalida.'),
                };
                flash('success', 'Operacao concluida com sucesso.');
            }
        } catch (ConflictException $e) {
            flash('error', 'A operacao foi impedida por conflito de datas. Consulte os conflitos nesta pagina.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect(base_url('admin/reservas/' . $id));
    }

    public function calendar(): void
    {
        AuthorizationService::requirePermission('calendar.view');
        $this->boot();
        $month = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['mes'] ?? '')) ? $_GET['mes'] : date('Y-m');
        $start = new DateTimeImmutable($month . '-01'); $end = $start->modify('first day of next month');
        $stmt = $this->db->prepare("SELECT id,codigo,nome_cliente,checkin,checkout,status,origem FROM reservas WHERE checkin<? AND checkout>? AND status<>'CANCELADA' ORDER BY checkin");
        $stmt->execute([$end->format('Y-m-d'), $start->format('Y-m-d')]); $events = $stmt->fetchAll();
        $stmt = $this->db->prepare('SELECT * FROM datas_bloqueadas WHERE data_inicio<? AND data_fim>? ORDER BY data_inicio');
        $stmt->execute([$end->format('Y-m-d'), $start->format('Y-m-d')]); $blocks = $stmt->fetchAll();
        $unifiedEvents=(new \Refugio\Services\UnifiedCalendarService($this->db))->events($start->format('Y-m-d'),$end->format('Y-m-d'));
        $calendarSources=$this->db->query("SELECT s.*,
            (SELECT COUNT(*) FROM calendar_external_events e WHERE e.source_id=s.id AND e.status<>'CANCELLED' AND e.deleted_at IS NULL) active_event_count,
            (SELECT MIN(e.starts_at) FROM calendar_external_events e WHERE e.source_id=s.id AND e.status<>'CANCELLED' AND e.deleted_at IS NULL) first_event_at,
            (SELECT MAX(e.ends_at) FROM calendar_external_events e WHERE e.source_id=s.id AND e.status<>'CANCELLED' AND e.deleted_at IS NULL) last_event_at
            FROM calendar_sources s ORDER BY s.ativo DESC,s.nome")->fetchAll();
        $exportTokens=$this->db->query('SELECT id,nome,ativo,created_at,last_used_at,revoked_at FROM calendar_export_tokens ORDER BY created_at DESC,id DESC')->fetchAll();
        $syncLogs=$this->db->query('SELECT l.*,s.nome source_name FROM calendar_sync_logs l JOIN calendar_sources s ON s.id=l.source_id ORDER BY l.created_at DESC LIMIT 20')->fetchAll();
        require BASE_PATH . '/app/Views/admin/calendar.php';
    }

    public function blockAction(string $action): never
    {
        AuthorizationService::requirePermission('reservas.manage'); $this->boot(); Csrf::verify($_POST['_csrf'] ?? null);
        try {
            if ($action === 'criar') {
                $start = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($_POST['data_inicio'] ?? ''));
                $end = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($_POST['data_fim'] ?? ''));
                $reason = mb_substr(trim(strip_tags((string) ($_POST['motivo'] ?? ''))), 0, 255);
                $origin = in_array($_POST['origem'] ?? '', ['USO_PROPRIO','MANUTENCAO','RESERVA_EXTERNA','INDISPONIBILIDADE','OUTRO'], true) ? $_POST['origem'] : 'OUTRO';
                if (!$start || !$end || $end <= $start || $reason === '') throw new RuntimeException('Informe datas e motivo validos.');
                $this->db->beginTransaction();
                (new AvailabilityService($this->db))->lockApprovalMutex();
                $conflicts = (new AvailabilityService($this->db))->conflicts($start->format('Y-m-d'), $end->format('Y-m-d'), null, true);
                if (AvailabilityService::hasConflicts($conflicts)) throw new ConflictException($conflicts);
                $this->db->prepare('INSERT INTO datas_bloqueadas (data_inicio,data_fim,motivo,origem) VALUES (?,?,?,?)')->execute([$start->format('Y-m-d'), $end->format('Y-m-d'), $reason, $origin]);
                $this->db->commit();
            } elseif ($action === 'excluir') {
                $this->db->prepare("DELETE FROM datas_bloqueadas WHERE id=? AND reserva_id IS NULL")->execute([(int) ($_POST['id'] ?? 0)]);
            } else throw new RuntimeException('Acao invalida.');
            flash('success', 'Calendario atualizado.');
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            flash('error', $e instanceof ConflictException ? 'As datas ja possuem reserva ou bloqueio.' : $e->getMessage());
        }
        redirect(base_url('admin/calendario'));
    }

    public function receipt(int $paymentId): never
    {
        AuthorizationService::requirePermission('reservas.view');
        $this->boot();
        $stmt = $this->db->prepare('SELECT p.*,r.codigo FROM pagamentos p JOIN reservas r ON r.id=p.reserva_id WHERE p.id=?');
        $stmt->execute([$paymentId]); $payment = $stmt->fetch();
        if (!$payment || !$payment['comprovante_path']) throw new RuntimeException('Comprovante nao encontrado.');
        $path = realpath(BASE_PATH . '/' . $payment['comprovante_path']); $storage = realpath(BASE_PATH . '/storage');
        if (!$path || !$storage || !str_starts_with($path, $storage) || !is_file($path)) throw new RuntimeException('Arquivo indisponivel.');
        header('Content-Type: ' . ($payment['comprovante_mime'] ?: 'application/octet-stream'));
        header('Content-Length: ' . filesize($path)); header('Cache-Control: private, no-store');
        header('Content-Disposition: attachment; filename="comprovante-' . preg_replace('/[^A-Za-z0-9_-]/', '', $payment['codigo']) . '.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
        readfile($path); exit;
    }

    private function resend(int $id): void
    {
        $r = $this->repository->find($id) ?? throw new RuntimeException('Reserva nao encontrada.');
        $event = match ($r['status']) {
            'AGUARDANDO_APROVACAO' => 'SOLICITACAO_RECEBIDA', 'AGUARDANDO_PAGAMENTO','COMPROVANTE_ENVIADO' => 'RESERVA_APROVADA',
            'RESERVA_CONFIRMADA','FINALIZADA' => 'RESERVA_CONFIRMADA', 'RECUSADA' => 'RESERVA_RECUSADA',
            'EXPIRADA' => 'PAGAMENTO_EXPIRADO', 'CANCELADA' => 'RESERVA_CANCELADA', default => throw new RuntimeException('Nao ha notificacao para este status.'),
        };
        $payment = $this->repository->payments($id)[0] ?? [];
        $delivery=(new NotificationService($this->db))->customer($r, $event, ['valor' => $payment['valor'] ?? 0, 'link' => base_url('reserva/' . $r['token_publico'])]);
        if (!$delivery['email']) throw new RuntimeException('O e-mail não pôde ser enviado. Consulte o erro registrado em Notificações e verifique a configuração SMTP.');
    }

    private function scalar(string $sql): float|int
    {
        $value = $this->db->query($sql)->fetchColumn();
        return is_numeric($value) && str_contains((string) $value, '.') ? (float) $value : (int) $value;
    }

    private function boot(): void
    {
        if (isset($this->db)) return;
        $this->db = Database::connection();
        $this->repository = new ReservationRepository($this->db);
        $this->service = new ReservationService($this->db, $this->config);
    }
}

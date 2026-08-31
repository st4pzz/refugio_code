<?php
declare(strict_types=1);

namespace Refugio\Controllers;

use PDO;
use Refugio\Config\Database;
use Refugio\Repositories\ReservationRepository;
use Refugio\Repositories\ReviewRepository;
use Refugio\Services\AuthService;
use Refugio\Services\AuthorizationService;
use Refugio\Services\ReviewInviteService;
use Refugio\Services\ReviewService;
use Refugio\Support\Csrf;
use RuntimeException;
use Throwable;

final class AdminReviewController
{
    private PDO $db;
    private ReviewRepository $repository;

    public function __construct(private array $config) {}

    public function index(): void
    {
        AuthorizationService::requirePermission('avaliacoes.view');
        $this->boot();
        $filters = array_intersect_key($_GET, array_flip(['q','status','nota','origem','inicio','fim']));
        $result = $this->repository->paginate($filters, max(1, (int) ($_GET['pagina'] ?? 1)));
        require BASE_PATH . '/app/Views/admin/reviews.php';
    }

    public function detail(int $id): void
    {
        AuthorizationService::requirePermission('avaliacoes.view');
        $this->boot();
        $review = $this->repository->find($id);
        if (!$review) {
            http_response_code(404);
            throw new RuntimeException('Avaliação não encontrada.');
        }
        $history = (new ReservationRepository($this->db))->history((int) $review['reserva_id']);
        require BASE_PATH . '/app/Views/admin/review-detail.php';
    }

    public function action(int $id, string $action): never
    {
        AuthorizationService::requirePermission('avaliacoes.manage');
        $this->boot();
        try {
            Csrf::verify($_POST['_csrf'] ?? null);
            (new ReviewService($this->db, $this->config))->moderate($id, $action, $_POST, (int) $_SESSION['admin_id']);
            flash('success', 'Moderação atualizada com sucesso.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect(base_url('admin/avaliacoes/' . $id));
    }

    public function invitation(int $reservationId, string $action): never
    {
        AuthorizationService::requirePermission('avaliacoes.manage');
        $this->boot();
        try {
            Csrf::verify($_POST['_csrf'] ?? null);
            $service = new ReviewInviteService($this->db, $this->config);
            $userId = (int) $_SESSION['admin_id'];
            $result = match ($action) {
                'enviar-convite-avaliacao' => $service->send($reservationId, false, false, $userId),
                'reenviar-convite-avaliacao' => $service->send($reservationId, true, false, $userId),
                'revogar-convite-avaliacao' => $service->revoke($reservationId, $userId),
                default => throw new RuntimeException('Ação de convite inválida.'),
            };
            if ($action === 'revogar-convite-avaliacao') {
                flash('success', 'Convite revogado.');
            } elseif (!empty($result['skipped'])) {
                flash('error', 'Já existe um convite válido em processamento. Use “Reenviar com novo link” se precisar rotacioná-lo.');
            } else {
                flash('review_invite_url', (string) $result['link']);
                $channels=[];if(!empty($result['email']))$channels[]='e-mail';if(!empty($result['whatsapp']))$channels[]='WhatsApp';
                if($channels!==[])flash('success','Convite enviado por '.implode(' e ',$channels).'. O link também pode ser copiado nesta página.');
                else flash('error','O convite foi criado, mas nenhum canal confirmou a entrega. Copie o link exibido nesta página e revise SMTP/WhatsApp.');
            }
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect(base_url('admin/reservas/' . $reservationId));
    }

    private function boot(): void
    {
        if (isset($this->db)) return;
        $this->db = Database::connection();
        $this->repository = new ReviewRepository($this->db);
    }
}

<?php
declare(strict_types=1);

namespace Refugio\Controllers;

use DateTimeImmutable;
use Refugio\Config\Database;
use Refugio\Services\AvailabilityService;
use Throwable;

final class PublicAvailabilityController
{
    public function __construct(private array $config) {}

    public function page(): void
    {
        require BASE_PATH . '/app/Views/public/availability.php';
    }

    public function month(): never
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store, max-age=0');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            header('Allow: GET');
            $this->respond(['ok' => false, 'message' => 'Método não permitido.']);
        }

        $month = trim((string) ($_GET['month'] ?? date('Y-m')));
        $firstDay = DateTimeImmutable::createFromFormat('!Y-m', $month);
        if ($firstDay === false || $firstDay->format('Y-m') !== $month) {
            http_response_code(422);
            $this->respond(['ok' => false, 'message' => 'Mês inválido.']);
        }

        $currentMonth = new DateTimeImmutable(date('Y-m-01'));
        $lastAllowedMonth = $currentMonth->modify('+18 months');
        if ($firstDay < $currentMonth || $firstDay > $lastAllowedMonth) {
            http_response_code(422);
            $this->respond(['ok' => false, 'message' => 'Mês fora do período de consulta.']);
        }

        $start = $firstDay->format('Y-m-d');
        $end = $firstDay->modify('+1 month')->format('Y-m-d');

        try {
            $occupied = (new AvailabilityService(Database::connection()))->blockedRanges($start, $end);
            $this->respond([
                'ok' => true,
                'month' => $month,
                'occupied' => $occupied,
                'generated_at' => (new DateTimeImmutable())->format(DATE_ATOM),
            ]);
        } catch (Throwable $error) {
            error_log('[disponibilidade-publica] ' . $error->getMessage());
            http_response_code(503);
            $this->respond(['ok' => false, 'message' => 'Não foi possível consultar o calendário agora.']);
        }
    }

    private function respond(array $payload): never
    {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    }
}

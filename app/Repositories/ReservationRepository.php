<?php
declare(strict_types=1);

namespace Refugio\Repositories;

use PDO;

final class ReservationRepository
{
    public function __construct(private PDO $db) {}

    public function create(array $data): int
    {
        $sql = 'INSERT INTO reservas (codigo, token_publico, idempotency_key, nome_cliente, cpf_cliente, email, telefone, checkin, checkout, adultos, criancas, quantidade_hospedes, status, observacoes_cliente, origem, termos_aceitos_em, regras_aceitas, cancelamento_aceito, whatsapp_autorizado, finalidade_coleta) VALUES (:codigo,:token_publico,:idempotency_key,:nome_cliente,:cpf_cliente,:email,:telefone,:checkin,:checkout,:adultos,:criancas,:quantidade_hospedes,:status,:observacoes_cliente,\'SITE_DIRETO\',NOW(),:regras_aceitas,:cancelamento_aceito,:whatsapp_autorizado,:finalidade_coleta)';
        $this->db->prepare($sql)->execute($data);
        return (int) $this->db->lastInsertId();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM reservas WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findForUpdate(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM reservas WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByIdempotency(string $key): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM reservas WHERE idempotency_key = ? LIMIT 1');
        $stmt->execute([$key]);
        return $stmt->fetch() ?: null;
    }

    public function findByToken(string $rawToken): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM reservas WHERE token_publico = ? LIMIT 1');
        $stmt->execute([$rawToken]);
        return $stmt->fetch() ?: null;
    }

    public function payments(int $id): array
    {
        $stmt = $this->db->prepare('SELECT * FROM pagamentos WHERE reserva_id = ? ORDER BY created_at DESC');
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    public function history(int $id): array
    {
        $stmt = $this->db->prepare('SELECT h.*, u.nome usuario_nome FROM historico_reserva h LEFT JOIN usuarios_admin u ON u.id=h.usuario_id WHERE h.reserva_id=? ORDER BY h.created_at DESC');
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    public function notifications(int $id): array
    {
        $stmt = $this->db->prepare('SELECT * FROM notificacoes WHERE reserva_id=? ORDER BY created_at DESC');
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    public function paginate(array $filters, int $page = 1, int $perPage = 20): array
    {
        $where = ['1=1']; $params = [];
        if ($filters['q'] ?? '') { $where[] = '(nome_cliente LIKE :q OR codigo LIKE :q OR email LIKE :q OR telefone LIKE :q)'; $params['q'] = '%' . $filters['q'] . '%'; }
        if ($filters['status'] ?? '') { $where[] = 'status=:status'; $params['status'] = $filters['status']; }
        if ($filters['origem'] ?? '') { $where[] = 'origem=:origem'; $params['origem'] = $filters['origem']; }
        if ($filters['inicio'] ?? '') { $where[] = 'checkout>=:inicio'; $params['inicio'] = $filters['inicio']; }
        if ($filters['fim'] ?? '') { $where[] = 'checkin<=:fim'; $params['fim'] = $filters['fim']; }
        $whereSql = implode(' AND ', $where);
        $count = $this->db->prepare("SELECT COUNT(*) FROM reservas WHERE {$whereSql}"); $count->execute($params);
        $allowedOrder = ['created_at','checkin','checkout','nome_cliente','status'];
        $order = in_array($filters['ordem'] ?? '', $allowedOrder, true) ? $filters['ordem'] : 'created_at';
        $offset = max(0, ($page - 1) * $perPage);
        $stmt = $this->db->prepare("SELECT * FROM reservas WHERE {$whereSql} ORDER BY {$order} DESC LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute($params);
        return ['items' => $stmt->fetchAll(), 'total' => (int) $count->fetchColumn(), 'page' => $page, 'per_page' => $perPage];
    }
}

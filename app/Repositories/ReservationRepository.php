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

    public function createManualProposal(array $data): int
    {
        $sql = "INSERT INTO reservas (
                    codigo,token_publico,idempotency_key,nome_cliente,cpf_cliente,email,telefone,
                    checkin,checkout,adultos,criancas,quantidade_hospedes,valor_total,valor_restante,
                    status,observacoes_cliente,observacoes_cobranca,politica_cancelamento,origem,
                    termos_aceitos_em,regras_aceitas,cancelamento_aceito,whatsapp_autorizado,finalidade_coleta
                ) VALUES (
                    :codigo,:token_publico,:idempotency_key,:nome_cliente,:cpf_cliente,:email,:telefone,
                    :checkin,:checkout,:adultos,:criancas,:quantidade_hospedes,:valor_total,:valor_restante,
                    :status,:observacoes_cliente,:observacoes_cobranca,:politica_cancelamento,'MANUAL',
                    NOW(),:regras_aceitas,:cancelamento_aceito,:whatsapp_autorizado,:finalidade_coleta
                )";
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
        $source = "(SELECT id,codigo,nome_cliente,email,telefone,checkin,checkout,status,origem,created_at,
                    'internal' AS record_type,NULL AS feed_status,NULL AS deleted_at,NULL AS source_name,1 AS source_active
                    FROM reservas
                    UNION ALL
                    SELECT e.id,e.external_uid,COALESCE(NULLIF(e.summary,''),'Evento externo'),'', '',
                    DATE(e.starts_at),DATE(e.ends_at),
                    CASE WHEN e.status='CANCELLED' THEN 'CANCELADA'
                         WHEN e.deleted_at IS NOT NULL THEN 'ICAL_REMOVIDO'
                         WHEN e.status='TENTATIVE' THEN 'ICAL_PROVISORIO'
                         ELSE 'RESERVA_CONFIRMADA' END,
                    s.provider,e.created_at,'external',e.status,e.deleted_at,s.nome,s.ativo
                    FROM calendar_external_events e JOIN calendar_sources s ON s.id=e.source_id
                    WHERE s.provider IN ('BOOKING','AIRBNB')) AS reservation_list";
        $perPage = max(1, min(100, $perPage));
        $where = ['1=1']; $params = [];
        if ($filters['q'] ?? '') {
            $where[] = '(nome_cliente LIKE :q_nome OR codigo LIKE :q_codigo OR email LIKE :q_email OR telefone LIKE :q_telefone)';
            foreach (['q_nome','q_codigo','q_email','q_telefone'] as $key) $params[$key] = '%' . $filters['q'] . '%';
        }
        if ($filters['status'] ?? '') { $where[] = 'status=:status'; $params['status'] = $filters['status']; }
        if ($filters['origem'] ?? '') { $where[] = 'origem=:origem'; $params['origem'] = $filters['origem']; }
        if ($filters['inicio'] ?? '') { $where[] = 'checkout>=:inicio'; $params['inicio'] = $filters['inicio']; }
        if ($filters['fim'] ?? '') { $where[] = 'checkin<=:fim'; $params['fim'] = $filters['fim']; }
        $whereSql = implode(' AND ', $where);
        $count = $this->db->prepare("SELECT COUNT(*) FROM {$source} WHERE {$whereSql}"); $count->execute($params);
        $allowedOrder = ['created_at','checkin','checkout','nome_cliente','status'];
        $order = in_array($filters['ordem'] ?? '', $allowedOrder, true) ? $filters['ordem'] : 'created_at';
        $offset = max(0, ($page - 1) * $perPage);
        $stmt = $this->db->prepare("SELECT * FROM {$source} WHERE {$whereSql} ORDER BY {$order} DESC,record_type,id DESC LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute($params);
        return ['items' => $stmt->fetchAll(), 'total' => (int) $count->fetchColumn(), 'page' => $page, 'per_page' => $perPage];
    }
}

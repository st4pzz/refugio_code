<?php
declare(strict_types=1);

namespace Refugio\Repositories;

use PDO;

final class ConversationRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function paginate(array $filters, int $page = 1, int $perPage = 30): array
    {
        $where = ['1=1']; $params = [];
        if (($filters['q'] ?? '') !== '') {
            $needle = '%' . trim((string) $filters['q']) . '%';
            $where[] = '(c.nome_contato LIKE ? OR c.telefone_normalizado LIKE ? OR c.ultima_mensagem_preview LIKE ?)';
            array_push($params, $needle, $needle, $needle);
        }
        foreach (['status','prioridade','atendente_id'] as $field) {
            if (($filters[$field] ?? '') !== '') { $where[] = 'c.' . $field . '=?'; $params[] = $filters[$field]; }
        }
        if (!empty($filters['tag_id'])) { $where[] = 'EXISTS (SELECT 1 FROM conversa_tag_vinculos tvf WHERE tvf.conversa_id=c.id AND tvf.tag_id=?)'; $params[] = (int) $filters['tag_id']; }
        $whereSql = implode(' AND ', $where);
        $count = $this->db->prepare("SELECT COUNT(*) FROM conversas c WHERE {$whereSql}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $page = max(1, $page); $perPage = max(10, min(100, $perPage)); $offset = ($page - 1) * $perPage;
        $sql = "SELECT c.*,u.nome atendente_nome,cl.nome cliente_nome,l.nome lead_nome,GROUP_CONCAT(DISTINCT t.nome ORDER BY t.nome SEPARATOR ' · ') tags FROM conversas c LEFT JOIN usuarios_admin u ON u.id=c.atendente_id LEFT JOIN clientes cl ON cl.id=c.cliente_id LEFT JOIN leads l ON l.id=c.lead_id LEFT JOIN conversa_tag_vinculos tv ON tv.conversa_id=c.id LEFT JOIN conversa_tags t ON t.id=tv.tag_id WHERE {$whereSql} GROUP BY c.id ORDER BY COALESCE(c.ultima_mensagem_em,c.created_at) DESC LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql); $stmt->execute($params);
        return ['items'=>$stmt->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>max(1,(int) ceil($total/$perPage))];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT c.*,u.nome atendente_nome,cl.nome cliente_nome,cl.email cliente_email,l.nome lead_nome,l.status lead_status,r.codigo reserva_codigo,r.status reserva_status,a.utm_source,a.utm_campaign FROM conversas c LEFT JOIN usuarios_admin u ON u.id=c.atendente_id LEFT JOIN clientes cl ON cl.id=c.cliente_id LEFT JOIN leads l ON l.id=c.lead_id LEFT JOIN reservas r ON r.id=c.reserva_id LEFT JOIN marketing_atribuicoes a ON a.conversa_id=c.id WHERE c.id=? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function messages(int $conversationId, int $afterId = 0, int $limit = 100): array
    {
        $limit = max(1, min(200, $limit));
        if ($afterId > 0) {
            $stmt = $this->db->prepare("SELECT m.*,u.nome usuario_nome FROM mensagens m LEFT JOIN usuarios_admin u ON u.id=m.enviada_por_usuario_id WHERE m.conversa_id=? AND m.id>? ORDER BY m.id ASC LIMIT {$limit}");
            $stmt->execute([$conversationId,$afterId]);
            return $stmt->fetchAll();
        }
        $stmt = $this->db->prepare("SELECT x.* FROM (SELECT m.*,u.nome usuario_nome FROM mensagens m LEFT JOIN usuarios_admin u ON u.id=m.enviada_por_usuario_id WHERE m.conversa_id=? ORDER BY m.id DESC LIMIT {$limit}) x ORDER BY x.id ASC");
        $stmt->execute([$conversationId]);
        return $stmt->fetchAll();
    }

    public function tags(): array { return $this->db->query('SELECT * FROM conversa_tags WHERE ativo=1 ORDER BY nome')->fetchAll(); }
    public function assignedTagIds(int $id): array { $s=$this->db->prepare('SELECT tag_id FROM conversa_tag_vinculos WHERE conversa_id=?'); $s->execute([$id]); return array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN)); }
    public function agents(): array { return $this->db->query('SELECT id,nome FROM usuarios_admin WHERE ativo=1 ORDER BY nome')->fetchAll(); }
    public function templates(): array { return $this->db->query("SELECT * FROM whatsapp_templates WHERE status='APPROVED' ORDER BY nome,idioma")->fetchAll(); }
    public function notes(int $id): array { $s=$this->db->prepare('SELECT n.*,u.nome usuario_nome FROM conversa_notas n LEFT JOIN usuarios_admin u ON u.id=n.usuario_id WHERE n.conversa_id=? ORDER BY n.created_at DESC LIMIT 30'); $s->execute([$id]); return $s->fetchAll(); }

    public function candidates(string $query): array
    {
        $needle = '%' . trim($query) . '%';
        $clients = $this->db->prepare("SELECT id,nome,email,telefone_normalizado FROM clientes WHERE status='ATIVO' AND (nome LIKE ? OR email LIKE ? OR telefone_normalizado LIKE ?) ORDER BY updated_at DESC LIMIT 15");
        $clients->execute([$needle,$needle,$needle]);
        $reservations = $this->db->prepare('SELECT id,codigo,nome_cliente,status,checkin FROM reservas WHERE codigo LIKE ? OR nome_cliente LIKE ? OR telefone LIKE ? ORDER BY created_at DESC LIMIT 15');
        $reservations->execute([$needle,$needle,$needle]);
        return ['clients'=>$clients->fetchAll(),'reservations'=>$reservations->fetchAll()];
    }
}

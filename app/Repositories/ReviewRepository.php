<?php
declare(strict_types=1);

namespace Refugio\Repositories;

use PDO;

final class ReviewRepository
{
    public function __construct(private PDO $db) {}

    public function reservation(int $id, bool $lock = false): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM reservas WHERE id=?' . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute([$id]); return $stmt->fetch() ?: null;
    }

    public function invitationByReservation(int $id, bool $lock = false): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM convites_avaliacao WHERE reserva_id=?' . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute([$id]); return $stmt->fetch() ?: null;
    }

    public function invitationByToken(string $rawToken, bool $lock = false): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM convites_avaliacao WHERE token_hash=?' . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute([hash('sha256', $rawToken)]); return $stmt->fetch() ?: null;
    }

    public function reviewByReservation(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM avaliacoes WHERE reserva_id=?');
        $stmt->execute([$id]); return $stmt->fetch() ?: null;
    }

    public function saveInvitation(int $reservationId, string $hash, string $expiresAt): int
    {
        $sql = "INSERT INTO convites_avaliacao (reserva_id,token_hash,status,expira_em) VALUES (?,?,'PENDENTE',?)
            ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),status='PENDENTE',expira_em=VALUES(expira_em),
            utilizado_em=NULL,revogado_em=NULL,enviado_email_em=NULL,enviado_whatsapp_em=NULL,
            ultimo_envio_em=NULL,lembrete_enviado_em=NULL,updated_at=NOW()";
        $this->db->prepare($sql)->execute([$reservationId, $hash, $expiresAt]);
        $invite = $this->invitationByReservation($reservationId);
        return (int) $invite['id'];
    }

    public function recordDelivery(int $id, bool $email, bool $whatsApp, bool $reminder): void
    {
        $sql = "UPDATE convites_avaliacao SET status=IF(? OR ?,'ENVIADO','PENDENTE'),
            enviado_email_em=IF(?,NOW(),enviado_email_em), enviado_whatsapp_em=IF(?,NOW(),enviado_whatsapp_em),
            ultimo_envio_em=IF(? OR ?,NOW(),ultimo_envio_em), lembrete_enviado_em=IF((? OR ?) AND ?,NOW(),lembrete_enviado_em),
            quantidade_envios=quantidade_envios+1 WHERE id=?";
        $flags = [$email,$whatsApp,$email,$whatsApp,$email,$whatsApp,$email,$whatsApp,$reminder,$id];
        $this->db->prepare($sql)->execute(array_map('intval', $flags));
    }

    public function createReview(int $reservationId, int $inviteId, array $data): int
    {
        $sql = "INSERT INTO avaliacoes (reserva_id,convite_avaliacao_id,nome_exibicao,nota_geral,nota_limpeza,nota_localizacao,nota_conforto,nota_comunicacao,nota_custo_beneficio,comentario,status,autoriza_publicacao,anonima,enviada_em)
            VALUES (:reserva_id,:convite_id,:nome_exibicao,:nota_geral,:nota_limpeza,:nota_localizacao,:nota_conforto,:nota_comunicacao,:nota_custo_beneficio,:comentario,'PENDENTE',:autoriza_publicacao,:anonima,NOW())";
        $this->db->prepare($sql)->execute(array_merge($data, ['reserva_id'=>$reservationId,'convite_id'=>$inviteId]));
        return (int) $this->db->lastInsertId();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT a.*,r.codigo,r.nome_cliente,r.checkin,r.checkout,r.origem,r.status reserva_status,c.status convite_status,c.expira_em,c.revogado_em FROM avaliacoes a JOIN reservas r ON r.id=a.reserva_id JOIN convites_avaliacao c ON c.id=a.convite_avaliacao_id WHERE a.id=?');
        $stmt->execute([$id]); return $stmt->fetch() ?: null;
    }

    public function paginate(array $filters, int $page, int $perPage = 20): array
    {
        $where=['1=1']; $params=[];
        if ($filters['q']??'') { $where[]='(r.nome_cliente LIKE :q OR a.nome_exibicao LIKE :q OR r.codigo LIKE :q)'; $params['q']='%'.$filters['q'].'%'; }
        if ($filters['status']??'') { $where[]='a.status=:status'; $params['status']=$filters['status']; }
        if ($filters['nota']??'') { $where[]='a.nota_geral=:nota'; $params['nota']=(int)$filters['nota']; }
        if ($filters['origem']??'') { $where[]='r.origem=:origem'; $params['origem']=$filters['origem']; }
        if ($filters['inicio']??'') { $where[]='a.enviada_em>=:inicio'; $params['inicio']=$filters['inicio'].' 00:00:00'; }
        if ($filters['fim']??'') { $where[]='a.enviada_em<=:fim'; $params['fim']=$filters['fim'].' 23:59:59'; }
        $whereSql=implode(' AND ',$where);
        $count=$this->db->prepare("SELECT COUNT(*) FROM avaliacoes a JOIN reservas r ON r.id=a.reserva_id WHERE {$whereSql}"); $count->execute($params);
        $offset=max(0,($page-1)*$perPage);
        $stmt=$this->db->prepare("SELECT a.*,r.codigo,r.nome_cliente,r.checkin,r.checkout,r.origem FROM avaliacoes a JOIN reservas r ON r.id=a.reserva_id WHERE {$whereSql} ORDER BY a.enviada_em DESC LIMIT {$perPage} OFFSET {$offset}"); $stmt->execute($params);
        return ['items'=>$stmt->fetchAll(),'total'=>(int)$count->fetchColumn(),'page'=>$page,'per_page'=>$perPage];
    }

    public function publicData(int $limit = 20): array
    {
        $stmt=$this->db->prepare("SELECT a.nome_exibicao,a.nota_geral,a.comentario,a.resposta_administrador,r.checkout FROM avaliacoes a JOIN reservas r ON r.id=a.reserva_id WHERE a.status='APROVADA' AND a.autoriza_publicacao=1 ORDER BY a.aprovada_em DESC LIMIT ?");
        $stmt->bindValue(1,$limit,PDO::PARAM_INT); $stmt->execute();
        $stats=$this->db->query("SELECT COUNT(*) quantidade,AVG(nota_geral) media FROM avaliacoes WHERE status='APROVADA' AND autoriza_publicacao=1")->fetch();
        return ['items'=>$stmt->fetchAll(),'count'=>(int)$stats['quantidade'],'average'=>$stats['media']!==null?round((float)$stats['media'],1):null];
    }

    public function invitationCandidates(string $checkoutThreshold, int $limit = 100): array
    {
        $stmt=$this->db->prepare("SELECT r.id FROM reservas r LEFT JOIN avaliacoes a ON a.reserva_id=r.id LEFT JOIN convites_avaliacao c ON c.reserva_id=r.id WHERE r.status IN ('FINALIZADA','RESERVA_CONFIRMADA') AND r.checkout<=? AND a.id IS NULL AND (c.id IS NULL OR c.status='PENDENTE') ORDER BY r.checkout LIMIT {$limit}");
        $stmt->execute([$checkoutThreshold]); return array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function reminderCandidates(string $sentThreshold, int $limit = 100): array
    {
        $stmt=$this->db->prepare("SELECT c.reserva_id FROM convites_avaliacao c LEFT JOIN avaliacoes a ON a.reserva_id=c.reserva_id WHERE c.status='ENVIADO' AND c.expira_em>NOW() AND c.lembrete_enviado_em IS NULL AND c.ultimo_envio_em<=? AND a.id IS NULL ORDER BY c.ultimo_envio_em LIMIT {$limit}");
        $stmt->execute([$sentThreshold]); return array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}

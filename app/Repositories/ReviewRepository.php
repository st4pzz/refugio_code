<?php
declare(strict_types=1);

namespace Refugio\Repositories;

use PDO;
use Refugio\Services\EncryptionService;

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
        $sql = "INSERT INTO avaliacoes (reserva_id,convite_avaliacao_id,origem_plataforma,nome_exibicao,nota_geral,nota_limpeza,nota_localizacao,nota_conforto,nota_comunicacao,nota_custo_beneficio,comentario,status,autoriza_publicacao,anonima,enviada_em)
            VALUES (:reserva_id,:convite_id,:origem_plataforma,:nome_exibicao,:nota_geral,:nota_limpeza,:nota_localizacao,:nota_conforto,:nota_comunicacao,:nota_custo_beneficio,:comentario,'PENDENTE',:autoriza_publicacao,:anonima,NOW())";
        $this->db->prepare($sql)->execute(array_merge($data, ['reserva_id'=>$reservationId,'convite_id'=>$inviteId,'origem_plataforma'=>$data['origem_plataforma']??'SITE_DIRETO']));
        return (int) $this->db->lastInsertId();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT a.*,COALESCE(r.codigo,CONCAT(a.origem_plataforma,'-',a.id)) codigo,COALESCE(r.nome_cliente,a.nome_exibicao) nome_cliente,r.checkin,r.checkout,a.origem_plataforma origem,r.status reserva_status,c.status convite_status,c.expira_em convite_expira_em,c.revogado_em FROM avaliacoes a LEFT JOIN reservas r ON r.id=a.reserva_id LEFT JOIN convites_avaliacao c ON c.id=a.convite_avaliacao_id WHERE a.id=?");
        $stmt->execute([$id]); return $stmt->fetch() ?: null;
    }

    public function paginate(array $filters, int $page, int $perPage = 20): array
    {
        $where=['1=1']; $params=[];
        if ($filters['q']??'') { $where[]='(r.nome_cliente LIKE :q OR a.nome_exibicao LIKE :q OR r.codigo LIKE :q OR a.external_review_id LIKE :q)'; $params['q']='%'.$filters['q'].'%'; }
        if ($filters['status']??'') { $where[]='a.status=:status'; $params['status']=$filters['status']; }
        if ($filters['nota']??'') { $where[]='a.nota_geral=:nota'; $params['nota']=(int)$filters['nota']; }
        if ($filters['origem']??'') { $where[]='a.origem_plataforma=:origem'; $params['origem']=$filters['origem']; }
        if ($filters['inicio']??'') { $where[]='a.enviada_em>=:inicio'; $params['inicio']=$filters['inicio'].' 00:00:00'; }
        if ($filters['fim']??'') { $where[]='a.enviada_em<=:fim'; $params['fim']=$filters['fim'].' 23:59:59'; }
        $whereSql=implode(' AND ',$where);
        $count=$this->db->prepare("SELECT COUNT(*) FROM avaliacoes a LEFT JOIN reservas r ON r.id=a.reserva_id WHERE {$whereSql}"); $count->execute($params);
        $offset=max(0,($page-1)*$perPage);
        $stmt=$this->db->prepare("SELECT a.*,COALESCE(r.codigo,CONCAT(a.origem_plataforma,'-',a.id)) codigo,COALESCE(r.nome_cliente,a.nome_exibicao) nome_cliente,r.checkin,r.checkout,a.origem_plataforma origem FROM avaliacoes a LEFT JOIN reservas r ON r.id=a.reserva_id WHERE {$whereSql} ORDER BY a.enviada_em DESC LIMIT {$perPage} OFFSET {$offset}"); $stmt->execute($params);
        return ['items'=>$stmt->fetchAll(),'total'=>(int)$count->fetchColumn(),'page'=>$page,'per_page'=>$perPage];
    }

    public function publicData(int $limit = 20): array
    {
        $this->purgeExpiredGoogleReviews();
        $stmt=$this->db->prepare("SELECT a.nome_exibicao,a.nota_geral,a.comentario,a.resposta_administrador,r.checkout,a.origem_plataforma origem,a.external_url FROM avaliacoes a LEFT JOIN reservas r ON r.id=a.reserva_id WHERE a.status='APROVADA' AND a.autoriza_publicacao=1 AND TRIM(a.comentario)<>'' AND (a.expira_em IS NULL OR a.expira_em>NOW()) ORDER BY a.aprovada_em DESC LIMIT ?");
        $stmt->bindValue(1,$limit,PDO::PARAM_INT); $stmt->execute();
        $stats=$this->db->query("SELECT COUNT(*) quantidade,AVG(nota_geral) media FROM avaliacoes WHERE status='APROVADA' AND autoriza_publicacao=1 AND origem_plataforma<>'GOOGLE' AND TRIM(comentario)<>'' AND (expira_em IS NULL OR expira_em>NOW())")->fetch();
        return ['items'=>$stmt->fetchAll(),'count'=>(int)$stats['quantidade'],'average'=>$stats['media']!==null?round((float)$stats['media'],1):null];
    }

    public function upsertExternalReview(array $data, int $userId): int
    {
        $sql = "INSERT INTO avaliacoes (reserva_id,convite_avaliacao_id,origem_plataforma,external_review_id,external_url,avaliacao_em,importada_em,expira_em,nome_exibicao,nota_geral,nota_limpeza,nota_localizacao,nota_conforto,nota_comunicacao,nota_custo_beneficio,comentario,status,autoriza_publicacao,anonima,enviada_em,created_by)
            VALUES (NULL,NULL,:provider,:external_id,:external_url,:reviewed_at,NOW(),:expires_at,:name,:rating,NULL,NULL,NULL,NULL,NULL,:comment,'PENDENTE',1,0,:reviewed_at,:created_by)
            ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),status=IF(nota_geral<>VALUES(nota_geral) OR comentario<>VALUES(comentario),'PENDENTE',status),aprovada_em=IF(nota_geral<>VALUES(nota_geral) OR comentario<>VALUES(comentario),NULL,aprovada_em),external_url=VALUES(external_url),avaliacao_em=VALUES(avaliacao_em),importada_em=NOW(),expira_em=VALUES(expira_em),nome_exibicao=VALUES(nome_exibicao),nota_geral=VALUES(nota_geral),comentario=VALUES(comentario),updated_at=NOW()";
        $this->db->prepare($sql)->execute([
            'provider'=>$data['provider'],'external_id'=>$data['external_id'],'external_url'=>$data['external_url'],
            'reviewed_at'=>$data['reviewed_at'],'expires_at'=>$data['expires_at'],'name'=>$data['name'],
            'rating'=>$data['rating'],'comment'=>$data['comment'],'created_by'=>$userId > 0 ? $userId : null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function googleIntegration(bool $includeSecrets = false): ?array
    {
        $fields = $includeSecrets ? '*' : 'id,provider,status,token_expires_at,ultima_sincronizacao_em,erro_ultima_sincronizacao,created_at,updated_at';
        $stmt = $this->db->query("SELECT {$fields} FROM review_integrations WHERE provider='GOOGLE' LIMIT 1");
        return $stmt->fetch() ?: null;
    }

    public function saveGoogleIntegration(array $tokens, int $userId): void
    {
        $encryption = new EncryptionService();
        $access = $encryption->encrypt((string) $tokens['access_token']);
        $refresh = ($tokens['refresh_token'] ?? '') !== '' ? $encryption->encrypt((string) $tokens['refresh_token']) : '';
        $sql = "INSERT INTO review_integrations (provider,status,access_token_encrypted,refresh_token_encrypted,token_expires_at,created_by) VALUES ('GOOGLE','CONECTADA',?,?,?,?)
            ON DUPLICATE KEY UPDATE status='CONECTADA',access_token_encrypted=VALUES(access_token_encrypted),refresh_token_encrypted=IF(VALUES(refresh_token_encrypted)='',refresh_token_encrypted,VALUES(refresh_token_encrypted)),token_expires_at=VALUES(token_expires_at),erro_ultima_sincronizacao=NULL,created_by=COALESCE(VALUES(created_by),created_by)";
        $this->db->prepare($sql)->execute([$access,$refresh,$tokens['expires_at'] ?? null,$userId > 0 ? $userId : null]);
    }

    public function finishGoogleSync(?string $error): void
    {
        if ($error === null) {
            $this->db->exec("UPDATE review_integrations SET status='CONECTADA',ultima_sincronizacao_em=NOW(),erro_ultima_sincronizacao=NULL WHERE provider='GOOGLE'");
            return;
        }
        $stmt = $this->db->prepare("UPDATE review_integrations SET status='ERRO',erro_ultima_sincronizacao=? WHERE provider='GOOGLE'");
        $stmt->execute([mb_substr($error, 0, 1000)]);
    }

    public function disconnectGoogleIntegration(): void
    {
        $this->db->exec("UPDATE review_integrations SET status='DESCONECTADA',access_token_encrypted=NULL,refresh_token_encrypted=NULL,token_expires_at=NULL WHERE provider='GOOGLE'");
    }

    public function purgeExpiredGoogleReviews(): int
    {
        return $this->db->exec("DELETE FROM avaliacoes WHERE origem_plataforma='GOOGLE' AND expira_em IS NOT NULL AND expira_em<=NOW()");
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

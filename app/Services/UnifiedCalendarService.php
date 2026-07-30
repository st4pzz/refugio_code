<?php
declare(strict_types=1);

namespace Refugio\Services;

use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

final class UnifiedCalendarService
{
    public function __construct(private PDO $db)
    {
    }

    public function events(string $start, string $end): array
    {
        $items = [];
        $queries = [
            ['reservation', 'SELECT id,codigo AS title,checkin AS starts_at,checkout AS ends_at,status,origem AS source FROM reservas WHERE checkin<? AND checkout>?', [$end,$start]],
            ['block', "SELECT id,motivo AS title,data_inicio AS starts_at,data_fim AS ends_at,'BLOCKED' AS status,origem AS source FROM datas_bloqueadas WHERE data_inicio<? AND data_fim>?", [$end,$start]],
            ['external', "SELECT e.id,COALESCE(e.summary,'Reserva externa') AS title,e.starts_at,e.ends_at,e.status,s.provider AS source FROM calendar_external_events e JOIN calendar_sources s ON s.id=e.source_id WHERE e.starts_at<? AND e.ends_at>? AND e.status<>'CANCELLED' AND e.deleted_at IS NULL AND s.ativo=1", [$end,$start]],
            ['hold', "SELECT id,CONCAT('Retenção ',purpose) AS title,checkin AS starts_at,checkout AS ends_at,status,purpose AS source FROM calendar_holds WHERE checkin<? AND checkout>? AND status='ACTIVE' AND expires_at>NOW()", [$end,$start]],
        ];
        foreach ($queries as [$type,$sql,$params]) {
            $stmt = $this->db->prepare($sql); $stmt->execute($params);
            foreach ($stmt->fetchAll() as $row) $items[] = ['type' => $type] + $row;
        }
        usort($items, static fn(array $a,array $b): int => [$a['starts_at'],$a['type'],$a['id']] <=> [$b['starts_at'],$b['type'],$b['id']]);
        return $items;
    }

    public function createHold(string $checkin, string $checkout, string $purpose, int $ttlMinutes, ?int $quoteId = null, ?int $userId = null): array
    {
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $checkin);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $checkout);
        if (!$start || !$end || $end <= $start) throw new RuntimeException('Período de retenção inválido.');
        if (!in_array($purpose, ['QUOTE','PAYMENT','MANUAL'], true)) throw new RuntimeException('Finalidade da retenção inválida.');
        $token = bin2hex(random_bytes(32));
        $expires = (new DateTimeImmutable())->modify('+' . max(5,min(1440,$ttlMinutes)) . ' minutes');
        $this->db->beginTransaction();
        try {
            $availability = new AvailabilityService($this->db);
            $availability->lockApprovalMutex();
            $conflicts = $availability->conflicts($checkin,$checkout,null,true);
            if (AvailabilityService::hasConflicts($conflicts)) throw new RuntimeException('As datas já possuem conflito ativo.');
            $stmt = $this->db->prepare("INSERT INTO calendar_holds (token_hash,checkin,checkout,purpose,status,quote_id,expires_at,criado_por) VALUES (?,?,?,?,'ACTIVE',?,?,?)");
            $stmt->execute([hash('sha256',$token),$checkin,$checkout,$purpose,$quoteId,$expires->format('Y-m-d H:i:s'),$userId]);
            $id = (int) $this->db->lastInsertId();
            $this->db->commit();
            return ['id'=>$id,'token'=>$token,'expires_at'=>$expires->format(DATE_ATOM)];
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    public function releaseExpiredHolds(): int
    {
        return $this->db->exec("UPDATE calendar_holds SET status='EXPIRED',released_at=NOW(),release_reason='TTL_EXPIRED' WHERE status='ACTIVE' AND expires_at<=NOW()");
    }

    public function export(string $token): string
    {
        if (!preg_match('/^[a-f0-9]{64}$/',$token)) throw new RuntimeException('Token de calendário inválido.');
        $stmt=$this->db->prepare("SELECT id FROM calendar_export_tokens WHERE token_hash=? AND ativo=1 AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at>NOW())");
        $stmt->execute([hash('sha256',$token)]); $tokenId=$stmt->fetchColumn();
        if (!$tokenId) throw new RuntimeException('Calendário indisponível.');
        $this->db->prepare('UPDATE calendar_export_tokens SET last_used_at=NOW() WHERE id=?')->execute([$tokenId]);
        $rows=$this->db->query("SELECT codigo,checkin,checkout,updated_at FROM reservas WHERE status IN ('AGUARDANDO_PAGAMENTO','COMPROVANTE_ENVIADO','PAGAMENTO_CONFIRMADO','RESERVA_CONFIRMADA') ORDER BY checkin")->fetchAll();
        $lines=['BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//Refugio do Cuscuzeiro//Reservas//PT-BR','CALSCALE:GREGORIAN','METHOD:PUBLISH'];
        foreach($rows as $row){
            $lines[]='BEGIN:VEVENT'; $lines[]='UID:'.strtolower($row['codigo']).'@refugiodocuscuzeiro.local';
            $lines[]='DTSTAMP:'.gmdate('Ymd\THis\Z',strtotime($row['updated_at'].' UTC'));
            $lines[]='DTSTART;VALUE=DATE:'.str_replace('-','',$row['checkin']); $lines[]='DTEND;VALUE=DATE:'.str_replace('-','',$row['checkout']);
            $lines[]='SUMMARY:Ocupado - Refúgio do Cuscuzeiro'; $lines[]='STATUS:CONFIRMED'; $lines[]='TRANSP:OPAQUE'; $lines[]='END:VEVENT';
        }
        $lines[]='END:VCALENDAR';
        return implode("\r\n",$lines)."\r\n";
    }
}

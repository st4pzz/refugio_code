<?php
declare(strict_types=1);

namespace Refugio\Services;

use PDO;

final class AttributionService
{
    private const KEYS = ['utm_source','utm_medium','utm_campaign','utm_content','utm_term','gclid','gbraid','wbraid','fbclid','ttclid'];

    public function __construct(private ?PDO $db = null)
    {
    }

    public static function captureRequest(): void
    {
        if (PHP_SAPI === 'cli' || session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $touch = [];
        foreach (self::KEYS as $key) {
            $value = trim((string) ($_GET[$key] ?? ''));
            if ($value !== '') {
                $touch[$key] = mb_substr($value, 0, 255);
            }
        }
        $touch['landing_page'] = mb_substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 1000);
        $touch['referrer'] = mb_substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 1000);
        $touch['captured_at'] = date(DATE_ATOM);
        $_SESSION['_marketing_first_touch'] ??= $touch;
        $_SESSION['_marketing_last_touch'] = array_replace($_SESSION['_marketing_last_touch'] ?? [], $touch);
    }

    public function linkReservation(int $reservationId, ?int $clientId = null, ?int $leadId = null): void
    {
        $first = $_SESSION['_marketing_first_touch'] ?? null;
        $last = $_SESSION['_marketing_last_touch'] ?? null;
        if (!$this->db || !is_array($first) || !is_array($last)) {
            return;
        }
        $provider = self::provider($last);
        $stmt = $this->db->prepare('SELECT id,first_touch_json FROM marketing_atribuicoes WHERE reserva_id=? ORDER BY id LIMIT 1');
        $stmt->execute([$reservationId]);
        $existing = $stmt->fetch();
        $columns = self::columns($last);
        if ($existing) {
            $update = $this->db->prepare('UPDATE marketing_atribuicoes SET lead_id=COALESCE(?,lead_id),cliente_id=COALESCE(?,cliente_id),provider=?,utm_source=?,utm_medium=?,utm_campaign=?,utm_content=?,utm_term=?,gclid=?,gbraid=?,wbraid=?,fbclid=?,ttclid=?,landing_page=?,referrer=?,last_touch_json=?,ultimo_contato_em=NOW() WHERE id=?');
            $update->execute([$leadId,$clientId,$provider,...$columns,self::json($last),$existing['id']]);
            return;
        }
        $insert = $this->db->prepare('INSERT INTO marketing_atribuicoes (lead_id,cliente_id,reserva_id,provider,utm_source,utm_medium,utm_campaign,utm_content,utm_term,gclid,gbraid,wbraid,fbclid,ttclid,landing_page,referrer,first_touch_json,last_touch_json,primeiro_contato_em,ultimo_contato_em) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
        $insert->execute([$leadId,$clientId,$reservationId,$provider,...$columns,self::json($first),self::json($last)]);
    }

    public function linkLead(int $leadId): void
    {
        $first=$_SESSION['_marketing_first_touch']??null;$last=$_SESSION['_marketing_last_touch']??null;if(!$this->db||!is_array($first)||!is_array($last))return;$provider=self::provider($last);$columns=self::columns($last);$stmt=$this->db->prepare('SELECT id FROM marketing_atribuicoes WHERE lead_id=? ORDER BY id LIMIT 1');$stmt->execute([$leadId]);$id=$stmt->fetchColumn();if($id){$update=$this->db->prepare('UPDATE marketing_atribuicoes SET provider=?,utm_source=?,utm_medium=?,utm_campaign=?,utm_content=?,utm_term=?,gclid=?,gbraid=?,wbraid=?,fbclid=?,ttclid=?,landing_page=?,referrer=?,last_touch_json=?,ultimo_contato_em=NOW() WHERE id=?');$update->execute([$provider,...$columns,self::json($last),$id]);return;}$insert=$this->db->prepare('INSERT INTO marketing_atribuicoes (lead_id,provider,utm_source,utm_medium,utm_campaign,utm_content,utm_term,gclid,gbraid,wbraid,fbclid,ttclid,landing_page,referrer,first_touch_json,last_touch_json,primeiro_contato_em,ultimo_contato_em) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');$insert->execute([$leadId,$provider,...$columns,self::json($first),self::json($last)]);
    }

    public static function provider(array $touch): string
    {
        if (!empty($touch['gclid']) || !empty($touch['gbraid']) || !empty($touch['wbraid'])) return 'GOOGLE';
        if (!empty($touch['fbclid'])) return 'META';
        if (!empty($touch['ttclid'])) return 'TIKTOK';
        $source = strtolower((string) ($touch['utm_source'] ?? ''));
        if (str_contains($source, 'google')) return 'GOOGLE';
        if (str_contains($source, 'facebook') || str_contains($source, 'instagram') || str_contains($source, 'meta')) return 'META';
        if (str_contains($source, 'tiktok')) return 'TIKTOK';
        return $source === '' ? 'DIRETO' : 'OUTRO';
    }

    private static function columns(array $touch): array
    {
        $values = [];
        foreach ([...self::KEYS,'landing_page','referrer'] as $key) {
            $values[] = isset($touch[$key]) && $touch[$key] !== '' ? (string) $touch[$key] : null;
        }
        return $values;
    }

    private static function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}

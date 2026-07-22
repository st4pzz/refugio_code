<?php
declare(strict_types=1);

namespace Refugio\Services;

use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

final class QuoteService
{
    public function __construct(private PDO $db, private PricingEngine $engine = new PricingEngine())
    {
    }

    public function calculate(array $request, bool $public = true): array
    {
        $settings = $this->db->query('SELECT * FROM property_pricing_settings WHERE id=1')->fetch() ?: throw new RuntimeException('Configuração de preços não encontrada.');
        $property = (new PropertySettingsService($this->db))->values();
        $settings['max_guests'] = (int) ($property['MAX_GUESTS'] ?? 10);
        $settings['pets_allowed'] = $property['PETS_ALLOWED'] ?? false;
        $settings['max_pets'] = $property['MAX_PETS'] ?? null;
        $settings['pet_fee'] = $property['PET_FEE'] ?? '0';
        $seasons = $this->db->query('SELECT * FROM pricing_seasons WHERE ativo=1 ORDER BY priority,id')->fetchAll();
        $special = $this->db->query('SELECT * FROM pricing_special_dates WHERE ativo=1 ORDER BY priority,id')->fetchAll();
        $rules = $this->db->query('SELECT * FROM pricing_rules WHERE ativo=1 AND (starts_at IS NULL OR starts_at<=NOW()) AND (ends_at IS NULL OR ends_at>=NOW()) ORDER BY priority,id')->fetchAll();
        $coupon = null;
        if (!empty($request['coupon'])) {
            $stmt = $this->db->prepare('SELECT * FROM pricing_coupons WHERE code=?');
            $stmt->execute([mb_strtoupper(trim((string) $request['coupon']))]);
            $coupon = $stmt->fetch() ?: null;
        }
        return $this->engine->calculate($request, $settings, $seasons, $special, $rules, $coupon, $public);
    }

    public function create(array $customer, array $calculation, int $expirationHours, ?int $userId = null): array
    {
        $token = bin2hex(random_bytes(32));
        $code = 'ORC-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $expires = (new DateTimeImmutable())->modify('+' . max(1, min(720, $expirationHours)) . ' hours');
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("INSERT INTO quotes (code,public_token_hash,customer_name,customer_email,customer_phone,checkin,checkout,guests,pets,currency,subtotal,discount_total,total,status,pricing_snapshot_json,expires_at,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'READY',?,?,?)");
            $stmt->execute([
                $code, hash('sha256', $token), $customer['name'] ?? null, $customer['email'] ?? null, $customer['phone'] ?? null,
                $calculation['checkin'], $calculation['checkout'], $calculation['guests'], $calculation['pets'], $calculation['currency'],
                $calculation['subtotal'], $calculation['discount_total'], $calculation['total'],
                json_encode($calculation, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $expires->format('Y-m-d H:i:s'), $userId,
            ]);
            $quoteId = (int) $this->db->lastInsertId();
            $itemStmt = $this->db->prepare('INSERT INTO quote_items (quote_id,item_type,description,quantity,unit_amount,total_amount,metadata_json,sort_order) VALUES (?,?,?,?,?,?,?,?)');
            foreach ($calculation['items'] as $index => $item) {
                $itemStmt->execute([$quoteId, $item['item_type'], $item['description'], $item['quantity'], $item['unit_amount'], $item['total_amount'], json_encode($item, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), ($index + 1) * 10]);
            }
            $ruleStmt = $this->db->prepare('INSERT INTO quote_applied_rules (quote_id,source_type,source_id,source_name,rule_snapshot_json,amount_effect) VALUES (?,?,?,?,?,?)');
            foreach ($calculation['applied_rules'] as $rule) {
                $ruleStmt->execute([$quoteId, $rule['source_type'], $rule['source_id'], $rule['source_name'], json_encode($rule['snapshot'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $rule['amount_effect']]);
            }
            $this->db->commit();
            return ['id' => $quoteId, 'code' => $code, 'token' => $token, 'expires_at' => $expires->format(DATE_ATOM)] + $calculation;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    public function findByToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
        $stmt = $this->db->prepare('SELECT * FROM quotes WHERE public_token_hash=?');
        $stmt->execute([hash('sha256', $token)]);
        $quote = $stmt->fetch();
        if (!$quote) return null;
        $stmt = $this->db->prepare('SELECT * FROM quote_items WHERE quote_id=? ORDER BY sort_order,id');
        $stmt->execute([$quote['id']]);
        $quote['items'] = $stmt->fetchAll();
        return $quote;
    }
}

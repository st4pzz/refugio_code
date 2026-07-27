<?php
declare(strict_types=1);

namespace Refugio\Repositories;

use PDO;
use RuntimeException;
use Throwable;

final class CustomerRepository
{
    public function __construct(private PDO $db)
    {
    }

    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if ($digits === '') {
            return null;
        }
        if ((strlen($digits) === 10 || strlen($digits) === 11)) {
            $digits = '55' . $digits;
        }
        return strlen($digits) >= 10 && strlen($digits) <= 15 ? $digits : null;
    }

    public function findByPhone(?string $phone): ?array
    {
        $normalized = self::normalizePhone($phone);
        if ($normalized === null) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT * FROM clientes WHERE telefone_normalizado=? AND status<>\'ANONIMIZADO\' LIMIT 1');
        $stmt->execute([$normalized]);
        return $stmt->fetch() ?: null;
    }

    public function syncFromReservation(array $reservation): int
    {
        $phone = self::normalizePhone((string) ($reservation['telefone'] ?? ''));
        $existing = $this->findByPhone($phone);
        if ($existing) {
            $stmt = $this->db->prepare('UPDATE clientes SET nome=?,email=COALESCE(NULLIF(?,\'\'),email),cpf=COALESCE(NULLIF(?,\'\'),cpf),telefone=?,whatsapp_autorizado=GREATEST(whatsapp_autorizado,?) WHERE id=?');
            $stmt->execute([
                (string) $reservation['nome_cliente'],
                (string) ($reservation['email'] ?? ''),
                (string) ($reservation['cpf_cliente'] ?? ''),
                (string) ($reservation['telefone'] ?? ''),
                (int) ($reservation['whatsapp_autorizado'] ?? 0),
                $existing['id'],
            ]);
            $clientId = (int) $existing['id'];
        } else {
            $stmt = $this->db->prepare('INSERT INTO clientes (nome,cpf,email,telefone,telefone_normalizado,whatsapp_autorizado) VALUES (?,?,?,?,?,?)');
            $stmt->execute([
                (string) $reservation['nome_cliente'],
                $reservation['cpf_cliente'] ?: null,
                $reservation['email'] ?: null,
                $reservation['telefone'] ?: null,
                $phone,
                (int) ($reservation['whatsapp_autorizado'] ?? 0),
            ]);
            $clientId = (int) $this->db->lastInsertId();
        }
        $stmt = $this->db->prepare('INSERT INTO reserva_contatos (reserva_id,cliente_id) VALUES (?,?) ON DUPLICATE KEY UPDATE cliente_id=VALUES(cliente_id)');
        $stmt->execute([(int) $reservation['id'], $clientId]);
        return $clientId;
    }

    public function ensureLead(string $channel, ?string $phone, ?string $name = null, ?string $origin = null, array $data = []): int
    {
        $normalized = self::normalizePhone($phone);
        if ($normalized !== null) {
            $stmt = $this->db->prepare('SELECT id FROM leads WHERE canal=? AND telefone_normalizado=? LIMIT 1');
            $stmt->execute([$channel, $normalized]);
            $id = $stmt->fetchColumn();
            if ($id !== false) {
                $this->db->prepare('UPDATE leads SET nome=COALESCE(NULLIF(?,\'\'),nome),ultimo_contato_em=NOW(),dados_json=COALESCE(?,dados_json) WHERE id=?')->execute([$name, $data ? self::json($data) : null, $id]);
                return (int) $id;
            }
        }
        $stmt = $this->db->prepare('INSERT INTO leads (nome,telefone,telefone_normalizado,canal,origem,dados_json) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$name ?: null, $phone ?: null, $normalized, $channel, $origin ?: null, $data ? self::json($data) : null]);
        return (int) $this->db->lastInsertId();
    }

    public function paginate(string $query = '', int $page = 1, int $perPage = 25): array
    {
        $page = max(1, $page);
        $perPage = max(10, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        $params = [];
        $where = "c.status<>'ANONIMIZADO'";
        if (trim($query) !== '') {
            $where .= ' AND (c.nome LIKE ? OR c.email LIKE ? OR c.telefone_normalizado LIKE ?)';
            $needle = '%' . trim($query) . '%';
            $params = [$needle, $needle, $needle];
        }
        $count = $this->db->prepare("SELECT COUNT(*) FROM clientes c WHERE {$where}");
        $count->execute($params);
        $stmt = $this->db->prepare("SELECT c.*,COUNT(DISTINCT rc.reserva_id) total_reservas,MAX(r.created_at) ultima_reserva_em FROM clientes c LEFT JOIN reserva_contatos rc ON rc.cliente_id=c.id LEFT JOIN reservas r ON r.id=rc.reserva_id WHERE {$where} GROUP BY c.id ORDER BY c.updated_at DESC LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute($params);
        $total = (int) $count->fetchColumn();
        return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM clientes WHERE id=?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function reservations(int $id): array
    {
        $stmt = $this->db->prepare('SELECT r.* FROM reserva_contatos rc JOIN reservas r ON r.id=rc.reserva_id WHERE rc.cliente_id=? ORDER BY r.created_at DESC');
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    public function merge(int $sourceId, int $targetId): void
    {
        if ($sourceId === $targetId) {
            throw new RuntimeException('Selecione dois clientes diferentes.');
        }
        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare('SELECT id FROM clientes WHERE id IN (?,?) FOR UPDATE');
            $lock->execute([$sourceId, $targetId]);
            if (count($lock->fetchAll()) !== 2) {
                throw new RuntimeException('Cliente de origem ou destino nao encontrado.');
            }
            $this->db->prepare('UPDATE reserva_contatos SET cliente_id=? WHERE cliente_id=?')->execute([$targetId, $sourceId]);
            $this->db->prepare('UPDATE leads SET cliente_id=? WHERE cliente_id=?')->execute([$targetId, $sourceId]);
            $this->db->prepare('UPDATE conversas SET cliente_id=? WHERE cliente_id=?')->execute([$targetId, $sourceId]);
            $this->db->prepare('UPDATE marketing_atribuicoes SET cliente_id=? WHERE cliente_id=?')->execute([$targetId, $sourceId]);
            $this->db->prepare("UPDATE clientes SET status='INATIVO',telefone_normalizado=NULL,observacoes=CONCAT(COALESCE(observacoes,''),'\nMesclado no cliente #',?) WHERE id=?")->execute([$targetId, $sourceId]);
            $this->db->commit();
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    public function anonymize(int $id): void
    {
        $this->db->beginTransaction();
        try {
            $conversations = $this->db->prepare('SELECT id FROM conversas WHERE cliente_id=? FOR UPDATE');
            $conversations->execute([$id]);
            $conversationIds = array_map('intval', $conversations->fetchAll(PDO::FETCH_COLUMN));
            $media = [];
            if ($conversationIds) {
                $placeholders = implode(',', array_fill(0, count($conversationIds), '?'));
                $messages = $this->db->prepare("SELECT media_path FROM mensagens WHERE conversa_id IN ({$placeholders}) AND media_path IS NOT NULL");
                $messages->execute($conversationIds);
                $media = $messages->fetchAll(PDO::FETCH_COLUMN);
                $this->db->prepare("UPDATE mensagens SET texto=NULL,media_id=NULL,media_path=NULL,media_nome=NULL,payload_json=JSON_OBJECT() WHERE conversa_id IN ({$placeholders})")->execute($conversationIds);
                $this->db->prepare("UPDATE conversas SET telefone=CONCAT('anon-',id),telefone_normalizado=CONCAT('anon-',id),wa_id=NULL,nome_contato='Contato anonimizado',ultima_mensagem_preview='Conteudo removido por solicitacao de privacidade' WHERE id IN ({$placeholders})")->execute($conversationIds);
            }

            $reservations = $this->db->prepare('SELECT reserva_id FROM reserva_contatos WHERE cliente_id=?');
            $reservations->execute([$id]);
            $reservationIds = array_map('intval', $reservations->fetchAll(PDO::FETCH_COLUMN));
            $reservationDocuments = [];
            if ($reservationIds) {
                $placeholders = implode(',', array_fill(0, count($reservationIds), '?'));
                $documents = $this->db->prepare("SELECT storage_path FROM reservation_documents WHERE reservation_id IN ({$placeholders})");
                $documents->execute($reservationIds);
                $reservationDocuments = $documents->fetchAll(PDO::FETCH_COLUMN);
                $this->db->prepare("DELETE FROM reservation_documents WHERE reservation_id IN ({$placeholders})")->execute($reservationIds);

                $updateReservation = $this->db->prepare("UPDATE reservas SET nome_cliente=?,cpf_cliente=NULL,email=?,telefone=?,observacoes_cliente=NULL WHERE id=?");
                foreach ($reservationIds as $reservationId) {
                    $updateReservation->execute(['Cliente anonimizado #' . $id, 'anonimizado-' . $id . '-' . $reservationId . '@invalid.local', 'anon-' . $reservationId, $reservationId]);
                }
            }
            $this->db->prepare("UPDATE leads SET nome='Lead anonimizado',email=NULL,telefone=NULL,telefone_normalizado=NULL,dados_json=JSON_OBJECT() WHERE cliente_id=?")->execute([$id]);
            $this->db->prepare("UPDATE marketing_atribuicoes SET cliente_id=NULL,gclid=NULL,gbraid=NULL,wbraid=NULL,fbclid=NULL,ttclid=NULL,landing_page=NULL,referrer=NULL,first_touch_json=JSON_OBJECT(),last_touch_json=JSON_OBJECT() WHERE cliente_id=?")->execute([$id]);
            $stmt = $this->db->prepare("UPDATE clientes SET nome=CONCAT('Cliente anonimizado #',id),cpf=NULL,email=NULL,telefone=NULL,telefone_normalizado=NULL,observacoes=NULL,status='ANONIMIZADO',anonimizado_em=NOW() WHERE id=?");
            $stmt->execute([$id]);
            $this->db->commit();

            $conversationStorage = realpath(BASE_PATH . '/storage/conversas');
            if ($conversationStorage) {
                foreach ($media as $relative) {
                    $path = realpath(BASE_PATH . '/' . ltrim((string) $relative, '/'));
                    if ($path && str_starts_with($path, $conversationStorage . DIRECTORY_SEPARATOR) && is_file($path)) @unlink($path);
                }
            }
            $documentStorage = realpath(BASE_PATH . '/storage/reservation-documents');
            if ($documentStorage) {
                foreach ($reservationDocuments as $relative) {
                    $path = realpath(BASE_PATH . '/' . ltrim((string) $relative, '/'));
                    if ($path && str_starts_with($path, $documentStorage . DIRECTORY_SEPARATOR) && is_file($path)) @unlink($path);
                }
            }
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    private static function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}

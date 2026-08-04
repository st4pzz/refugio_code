<?php
declare(strict_types=1);

namespace Refugio\Services;

use PDO;
use RuntimeException;
use Throwable;

final class ContractSignatureWorkflowService
{
    public const GUEST_SIGNED = 'GUEST_SIGNED';
    public const FULLY_SIGNED = 'FULLY_SIGNED';

    public function __construct(private PDO $db, private UploadService $uploads)
    {
    }

    public function uploadGuestSigned(int $contractId, array $file, string $ip, string $userAgent): array
    {
        $stored = $this->uploads->contractPdf($file, $contractId);
        try {
            $document = $this->register(
                $contractId,
                self::GUEST_SIGNED,
                'GUEST',
                null,
                $stored,
                $ip,
                $userAgent
            );
        } catch (Throwable $error) {
            $this->discard($stored);
            throw $error;
        }
        return $document;
    }

    public function uploadFullySigned(int $contractId, array $file, int $adminId, string $ip, string $userAgent): array
    {
        if ($adminId <= 0) throw new RuntimeException('Administrador invalido.');
        $stored = $this->uploads->contractPdf($file, $contractId);
        try {
            $document = $this->register(
                $contractId,
                self::FULLY_SIGNED,
                'OWNER',
                $adminId,
                $stored,
                $ip,
                $userAgent
            );
        } catch (Throwable $error) {
            $this->discard($stored);
            throw $error;
        }
        return $document;
    }

    public function latestDocument(int $contractId, string $stage): ?array
    {
        $this->assertStage($stage);
        $stmt = $this->db->prepare('SELECT * FROM contract_signature_documents WHERE contract_id=? AND stage=? ORDER BY revision_no DESC LIMIT 1');
        $stmt->execute([$contractId, $stage]);
        $document = $stmt->fetch();
        return $document ?: null;
    }

    public function resolvePath(array $document): string
    {
        $path = realpath(BASE_PATH . '/' . ltrim((string) ($document['storage_path'] ?? ''), '/\\'));
        $storage = realpath(BASE_PATH . '/storage/contracts');
        $normalizedPath = $path === false ? '' : str_replace('\\', '/', $path);
        $normalizedStorage = $storage === false ? '' : rtrim(str_replace('\\', '/', $storage), '/');
        if ($normalizedPath === '' || $normalizedStorage === '' || !str_starts_with($normalizedPath, $normalizedStorage . '/') || !is_file($path)) {
            throw new RuntimeException('Arquivo PDF nao encontrado no armazenamento protegido.');
        }
        return $path;
    }

    private function register(int $contractId, string $stage, string $role, ?int $adminId, array $stored, string $ip, string $userAgent): array
    {
        $this->assertStage($stage);
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT id,reservation_id,status,pdf_path,pdf_hash FROM reservation_contracts WHERE id=? FOR UPDATE');
            $stmt->execute([$contractId]);
            $contract = $stmt->fetch() ?: throw new RuntimeException('Contrato nao encontrado.');

            if ($stage === self::GUEST_SIGNED) {
                if (!in_array($contract['status'], ['READY', 'SENT', 'VIEWED', 'SIGNED_BY_GUEST'], true)) {
                    throw new RuntimeException('Este contrato nao aceita mais o envio do hospede.');
                }
                if (empty($contract['pdf_path']) || empty($contract['pdf_hash'])) {
                    throw new RuntimeException('O PDF original ainda esta sendo preparado. Aguarde antes de enviar a versao assinada.');
                }
                if (!empty($contract['pdf_hash']) && hash_equals((string) $contract['pdf_hash'], (string) $stored['sha256'])) {
                    throw new RuntimeException('O arquivo enviado e igual ao contrato sem assinatura. Assine-o no Gov.br antes de enviar.');
                }
            } else {
                if (!in_array($contract['status'], ['SIGNED_BY_GUEST', 'FULLY_SIGNED'], true)) {
                    throw new RuntimeException('Aguarde o envio do contrato assinado pelo hospede.');
                }
                $guest = $this->latestDocumentForUpdate($contractId, self::GUEST_SIGNED);
                if ($guest === null) throw new RuntimeException('O PDF assinado pelo hospede nao foi encontrado.');
                if (hash_equals((string) $guest['sha256'], (string) $stored['sha256'])) {
                    throw new RuntimeException('O PDF final e igual ao arquivo do hospede. Assine-o no Gov.br antes de enviar.');
                }
            }

            $existing = $this->db->prepare('SELECT id FROM contract_signature_documents WHERE contract_id=? AND stage=? AND sha256=? LIMIT 1');
            $existing->execute([$contractId, $stage, $stored['sha256']]);
            if ($existing->fetchColumn()) throw new RuntimeException('Este mesmo PDF ja foi enviado nesta etapa.');

            $last = $this->latestDocumentForUpdate($contractId, $stage);
            $revision = (int) ($last['revision_no'] ?? 0) + 1;
            $stmt = $this->db->prepare('INSERT INTO contract_signature_documents (contract_id,stage,revision_no,storage_path,original_name,mime_type,byte_size,sha256,uploaded_by_role,uploaded_by_admin_id,uploaded_ip,uploaded_user_agent) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([
                $contractId, $stage, $revision, $stored['path'], $stored['name'], $stored['mime'], $stored['bytes'], $stored['sha256'],
                $role, $adminId, mb_substr($ip, 0, 45), mb_substr($userAgent, 0, 500),
            ]);
            $documentId = (int) $this->db->lastInsertId();

            $signer = $this->db->prepare("UPDATE contract_signers SET status='SIGNED',accepted_at=NOW(),accepted_ip=?,accepted_user_agent=?,document_hash_at_acceptance=? WHERE contract_id=? AND signer_role=?");
            $signer->execute([mb_substr($ip, 0, 45), mb_substr($userAgent, 0, 500), $stored['sha256'], $contractId, $role]);

            $newStatus = $stage === self::GUEST_SIGNED ? 'SIGNED_BY_GUEST' : 'FULLY_SIGNED';
            $this->db->prepare("UPDATE reservation_contracts SET status=?,fully_signed_at=IF(?='FULLY_SIGNED',NOW(),fully_signed_at) WHERE id=?")
                ->execute([$newStatus, $newStatus, $contractId]);

            $eventType = $stage === self::GUEST_SIGNED ? 'GUEST_SIGNED_PDF_UPLOADED' : 'FULLY_SIGNED_PDF_UPLOADED';
            $metadata = json_encode(['document_id' => $documentId, 'revision_no' => $revision, 'source' => $role === 'GUEST' ? 'GUEST_PORTAL' : 'ADMIN_PORTAL'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $this->db->prepare('INSERT INTO contract_events (contract_id,event_type,metadata_json,ip,user_agent,document_hash) VALUES (?,?,?,?,?,?)')
                ->execute([$contractId, $eventType, $metadata, mb_substr($ip, 0, 45), mb_substr($userAgent, 0, 500), $stored['sha256']]);

            $this->db->commit();
            return [
                'id' => $documentId,
                'contract_id' => $contractId,
                'reservation_id' => (int) $contract['reservation_id'],
                'stage' => $stage,
                'revision_no' => $revision,
                'sha256' => $stored['sha256'],
                'storage_path' => $stored['path'],
            ];
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    private function latestDocumentForUpdate(int $contractId, string $stage): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM contract_signature_documents WHERE contract_id=? AND stage=? ORDER BY revision_no DESC LIMIT 1 FOR UPDATE');
        $stmt->execute([$contractId, $stage]);
        $document = $stmt->fetch();
        return $document ?: null;
    }

    private function assertStage(string $stage): void
    {
        if (!in_array($stage, [self::GUEST_SIGNED, self::FULLY_SIGNED], true)) throw new RuntimeException('Etapa de assinatura invalida.');
    }

    private function discard(array $stored): void
    {
        $path = BASE_PATH . '/' . ltrim((string) ($stored['path'] ?? ''), '/\\');
        if (is_file($path)) @unlink($path);
    }
}

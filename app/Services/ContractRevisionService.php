<?php
declare(strict_types=1);

namespace Refugio\Services;

use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

final class ContractRevisionService
{
    public function __construct(private PDO $db, private ?ContractPdfService $pdfService = null)
    {
    }

    public function reviseWithCurrentDate(int $sourceContractId, int $userId): array
    {
        if ($sourceContractId <= 0 || $userId <= 0) throw new RuntimeException('Contrato ou administrador invalido.');

        $newContractId = null;
        $newVersion = null;
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'SELECT c.*,v.body_html template_body_html,v.content_hash template_content_hash '
                . 'FROM reservation_contracts c '
                . 'JOIN contract_template_versions v ON v.id=c.template_version_id '
                . 'WHERE c.id=? FOR UPDATE'
            );
            $stmt->execute([$sourceContractId]);
            $source = $stmt->fetch() ?: throw new RuntimeException('Contrato nao encontrado.');
            if (!in_array($source['status'], ['READY', 'SENT', 'VIEWED'], true)) {
                throw new RuntimeException('Nao e permitido revisar um contrato que ja recebeu assinatura ou foi encerrado.');
            }

            $signed = $this->db->prepare('SELECT COUNT(*) FROM contract_signature_documents WHERE contract_id=?');
            $signed->execute([$sourceContractId]);
            if ((int) $signed->fetchColumn() > 0) {
                throw new RuntimeException('O contrato ja possui um PDF assinado e nao pode ser substituido.');
            }

            $variables = json_decode((string) $source['variables_snapshot_json'], true);
            if (!is_array($variables)) throw new RuntimeException('O snapshot do contrato esta invalido.');
            unset($variables['contract_number'], $variables['contract_version'], $variables['document_hash']);
            $variables['contract_date_long'] = (new DateTimeImmutable('now'))->format('d/m/Y');

            $versionStatement = $this->db->prepare('SELECT COALESCE(MAX(version_no),0)+1 FROM reservation_contracts WHERE reservation_id=? FOR UPDATE');
            $versionStatement->execute([(int) $source['reservation_id']]);
            $newVersion = (int) $versionStatement->fetchColumn();
            $contractNumber = 'CTR-' . (int) $source['reservation_id'] . '-' . $newVersion;
            $variables['contract_number'] = $contractNumber;
            $variables['contract_version'] = (string) $newVersion;
            $variables['document_hash'] = hash(
                'sha256',
                (string) $source['template_content_hash'] . '|' . json_encode($variables, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            );

            $html = (new ContractTemplateService($this->db))->render((string) $source['template_body_html'], $variables);
            $contentHash = hash('sha256', $html);
            $insert = $this->db->prepare(
                "INSERT INTO reservation_contracts (reservation_id,template_version_id,version_no,status,contract_number,variables_snapshot_json,html_snapshot,content_hash,ready_at,generated_by) "
                . "VALUES (?,?,?,'READY',?,?,?,?,NOW(),?)"
            );
            $insert->execute([
                $source['reservation_id'],
                $source['template_version_id'],
                $newVersion,
                $contractNumber,
                json_encode($variables, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                $html,
                $contentHash,
                $userId,
            ]);
            $newContractId = (int) $this->db->lastInsertId();

            $signer = $this->db->prepare('INSERT INTO contract_signers (contract_id,signer_role,name,cpf,email,phone) VALUES (?,?,?,?,?,?)');
            $signer->execute([$newContractId, 'GUEST', $variables['guest_full_name'], $variables['guest_cpf'], $variables['guest_email'], $variables['guest_phone']]);
            $signer->execute([$newContractId, 'OWNER', $variables['owner_full_name'], $variables['owner_cpf'], $variables['owner_email'], $variables['owner_phone']]);
            $metadata = json_encode(['template_version_id' => (int) $source['template_version_id'], 'revises_contract_id' => $sourceContractId], JSON_THROW_ON_ERROR);
            $this->db->prepare("INSERT INTO contract_events (contract_id,event_type,metadata_json,document_hash) VALUES (?,'CONTRACT_READY',?,?)")
                ->execute([$newContractId, $metadata, $contentHash]);

            $pdf = ($this->pdfService ??= new ContractPdfService($this->db))->generate($newContractId);

            $supersede = $this->db->prepare("UPDATE reservation_contracts SET status='SUPERSEDED',superseded_by=? WHERE id=? AND status IN ('READY','SENT','VIEWED')");
            $supersede->execute([$newContractId, $sourceContractId]);
            if ($supersede->rowCount() !== 1) throw new RuntimeException('O contrato original mudou durante a revisao.');
            $this->db->prepare("INSERT INTO contract_events (contract_id,event_type,metadata_json,document_hash) VALUES (?,'CONTRACT_SUPERSEDED',?,?)")
                ->execute([$sourceContractId, json_encode(['superseded_by' => $newContractId], JSON_THROW_ON_ERROR), $source['content_hash']]);

            $this->db->commit();
            return [
                'id' => $newContractId,
                'reservation_id' => (int) $source['reservation_id'],
                'contract_number' => $contractNumber,
                'version_no' => $newVersion,
                'contract_date_long' => $variables['contract_date_long'],
                'pdf' => $pdf,
            ];
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            if ($newContractId !== null && $newVersion !== null) {
                $path = BASE_PATH . '/storage/contracts/' . $newContractId . '/contract-' . $newVersion . '.pdf';
                if (is_file($path)) @unlink($path);
                $directory = dirname($path);
                if (is_dir($directory)) @rmdir($directory);
            }
            throw $error;
        }
    }
}

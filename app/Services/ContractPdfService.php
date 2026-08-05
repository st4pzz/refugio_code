<?php
declare(strict_types=1);

namespace Refugio\Services;

use PDO;
use RuntimeException;

final class ContractPdfService
{
    public function __construct(private PDO $db, private ?PdfRenderer $pdfRenderer = null)
    {
    }

    public function generate(int $contractId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM reservation_contracts WHERE id=?');
        $stmt->execute([$contractId]);
        $contract = $stmt->fetch() ?: throw new RuntimeException('Contrato não encontrado.');
        if (!in_array($contract['status'], ['READY', 'SENT', 'VIEWED', 'SIGNED_BY_GUEST', 'SIGNED_BY_OWNER', 'FULLY_SIGNED'], true)) {
            throw new RuntimeException('O estado atual não permite gerar o PDF.');
        }
        $directory = BASE_PATH . '/storage/contracts/' . $contractId;
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Não foi possível preparar o armazenamento do contrato.');
        }
        $output = $directory . '/contract-' . $contract['version_no'] . '.pdf';
        $title = 'Contrato ' . $contract['contract_number'];
        $variables = json_decode((string) $contract['variables_snapshot_json'], true);
        if (!is_array($variables)) {
            throw new RuntimeException('Os dados de identificação do contrato estão inválidos.');
        }
        $html = ContractPdfTemplate::render(
            $title,
            (string) $contract['html_snapshot'],
            (string) $contract['content_hash'],
            $variables
        );
        ($this->pdfRenderer ??= new PdfRenderer())->render($html, $output);

        $hash = hash_file('sha256', $output);
        $bytes = filesize($output);
        if ($hash === false || $bytes === false) {
            @unlink($output);
            throw new RuntimeException('Não foi possível validar o PDF gerado.');
        }
        $relative = substr(str_replace('\\', '/', $output), strlen(str_replace('\\', '/', BASE_PATH)) + 1);
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE reservation_contracts SET pdf_path=?,pdf_hash=? WHERE id=?')->execute([$relative, $hash, $contractId]);
            $this->db->prepare("INSERT INTO contract_documents (contract_id,document_type,storage_path,mime_type,byte_size,sha256) VALUES (?,'UNSIGNED_PDF',?,'application/pdf',?,?) ON DUPLICATE KEY UPDATE storage_path=VALUES(storage_path),byte_size=VALUES(byte_size),sha256=VALUES(sha256)")
                ->execute([$contractId, $relative, $bytes, $hash]);
            $this->db->prepare("INSERT INTO contract_events (contract_id,event_type,metadata_json,document_hash) VALUES (?,'PDF_GENERATED',?,?)")
                ->execute([$contractId, json_encode(['path' => $relative, 'bytes' => $bytes], JSON_THROW_ON_ERROR), $hash]);
            if ($ownsTransaction) $this->db->commit();
        } catch (\Throwable $error) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
        return ['path' => $output, 'relative_path' => $relative, 'sha256' => $hash, 'bytes' => $bytes];
    }
}

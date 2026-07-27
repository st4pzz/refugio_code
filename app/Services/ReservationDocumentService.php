<?php
declare(strict_types=1);

namespace Refugio\Services;

use DateTimeImmutable;
use PDO;
use Refugio\Repositories\CustomerRepository;
use Refugio\Support\Env;
use RuntimeException;
use Throwable;

final class ReservationDocumentService
{
    public const PROPOSAL = 'PROPOSAL';
    public const PAYMENT_REQUEST = 'PAYMENT_REQUEST';

    public function __construct(private PDO $db, private array $config, private string $pythonBinary = 'python')
    {
    }

    public function generate(int $reservationId, string $type, int $userId, array $context = []): array
    {
        if (!in_array($type, [self::PROPOSAL, self::PAYMENT_REQUEST], true)) {
            throw new RuntimeException('Tipo de documento de reserva inválido.');
        }
        $reservation = $this->reservation($reservationId);
        $payments = $this->payments($reservationId);
        $pendingPayments = array_values(array_filter($payments, static fn(array $payment): bool => in_array($payment['status'], ['PENDENTE', 'COMPROVANTE_ENVIADO'], true)));

        if ($type === self::PROPOSAL && $reservation['status'] !== 'AGUARDANDO_APROVACAO') {
            throw new RuntimeException('A proposta inicial só pode ser reemitida enquanto aguarda aprovação do cliente.');
        }
        if ($type === self::PAYMENT_REQUEST && $pendingPayments === []) {
            throw new RuntimeException('Crie uma cobrança pendente antes de emitir as instruções de pagamento.');
        }

        $previous = $this->latestDocument($reservationId, self::PROPOSAL);
        $previousSnapshot = $previous ? json_decode((string) $previous['snapshot_json'], true) : null;
        $items = is_array($context['items'] ?? null)
            ? $context['items']
            : (is_array($previousSnapshot['pricing_items'] ?? null)
                ? $previousSnapshot['pricing_items']
                : [['description' => 'Hospedagem', 'amount' => (string) $reservation['valor_total']]]);

        $validUntil = $type === self::PROPOSAL
            ? $this->proposalValidity($context['valid_until'] ?? ($previous['valid_until'] ?? null))
            : $this->paymentValidity($pendingPayments);
        $settings = (new PropertySettingsService($this->db))->values();
        $snapshot = $this->snapshot($reservation, $payments, $items, $settings, $type, $validUntil);
        $version = $this->nextVersion($reservationId, $type);
        $snapshot['document']['version'] = $version;

        $directory = BASE_PATH . '/storage/reservation-documents/' . $reservationId;
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Não foi possível preparar o armazenamento dos PDFs de reserva.');
        }
        $slug = $type === self::PROPOSAL ? 'pedido-reserva' : 'instrucoes-pagamento';
        $output = $directory . '/' . $slug . '-v' . $version . '.pdf';
        $temporaryDirectory = BASE_PATH . '/tmp/pdfs';
        if (!is_dir($temporaryDirectory) && !mkdir($temporaryDirectory, 0750, true) && !is_dir($temporaryDirectory)) {
            throw new RuntimeException('Não foi possível preparar os arquivos temporários do PDF.');
        }
        $payload = tempnam($temporaryDirectory, 'reservation-pdf-');
        if ($payload === false) throw new RuntimeException('Não foi possível preparar os dados do PDF.');

        $renderPayload = $snapshot;
        $renderPayload['assets'] = [
            'logo_path' => $this->logoPath(),
            'qr_code_path' => $this->qrCodePath($pendingPayments),
        ];
        file_put_contents($payload, json_encode($renderPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        try {
            $this->runGenerator($payload, $output);
        } finally {
            @unlink($payload);
        }

        $hash = hash_file('sha256', $output);
        $bytes = filesize($output);
        if ($hash === false || $bytes === false) {
            @unlink($output);
            throw new RuntimeException('Não foi possível validar o PDF gerado.');
        }
        $relative = $this->relativePath($output);
        $paymentId = $type === self::PAYMENT_REQUEST ? (int) ($pendingPayments[0]['id'] ?? 0) : null;
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("INSERT INTO reservation_documents
                (reservation_id,payment_id,document_type,version_no,valid_until,storage_path,mime_type,byte_size,sha256,snapshot_json,created_by)
                VALUES (?,?,?,?,?,?,'application/pdf',?,?,?,?)");
            $stmt->execute([
                $reservationId,
                $paymentId ?: null,
                $type,
                $version,
                $validUntil,
                $relative,
                $bytes,
                $hash,
                json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                $userId,
            ]);
            $documentId = (int) $this->db->lastInsertId();
            (new HistoryService($this->db))->log(
                $reservationId,
                $type === self::PROPOSAL ? 'PDF_PROPOSTA_GERADO' : 'PDF_PAGAMENTO_GERADO',
                $reservation['status'],
                $reservation['status'],
                ['documento_id' => $documentId, 'versao' => $version, 'sha256' => $hash],
                $userId
            );
            $this->db->commit();
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            @unlink($output);
            throw $error;
        }
        return $this->document($documentId) ?? throw new RuntimeException('Documento não encontrado após a geração.');
    }

    public function send(int $documentId, int $userId): array
    {
        $document = $this->document($documentId) ?? throw new RuntimeException('PDF de reserva não encontrado.');
        $conversation = $this->activeConversation((string) $document['telefone']);
        if (!$conversation) {
            throw new RuntimeException('Não há conversa ativa na janela de 24 horas para este telefone. Baixe o PDF e anexe-o manualmente no WhatsApp.');
        }
        $path = $this->absoluteDocumentPath((string) $document['storage_path']);
        $filename = ($document['document_type'] === self::PROPOSAL ? 'pedido-reserva-' : 'pagamento-reserva-')
            . preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $document['codigo']) . '.pdf';
        $caption = $document['document_type'] === self::PROPOSAL
            ? 'Olá, ' . $this->firstName((string) $document['nome_cliente']) . '! Segue o pedido de reserva ' . $document['codigo'] . ' para sua análise. A reserva ainda não está confirmada.'
            : 'Olá, ' . $this->firstName((string) $document['nome_cliente']) . '! Seguem as instruções de pagamento do pedido ' . $document['codigo'] . '. A confirmação ocorrerá após a identificação do pagamento.';

        $delivery = $this->db->prepare("INSERT INTO reservation_document_deliveries
            (document_id,conversation_id,destination,status,created_by) VALUES (?,?,?,'PENDING',?)");
        $delivery->execute([$documentId, $conversation['id'], $conversation['telefone_normalizado'], $userId]);
        $deliveryId = (int) $this->db->lastInsertId();
        try {
            $messageId = (new ConversationService($this->db, $this->config))->sendStoredDocument(
                (int) $conversation['id'],
                $path,
                $filename,
                $caption,
                $userId
            );
            $external = $this->db->prepare('SELECT external_message_id FROM mensagens WHERE id=?');
            $external->execute([$messageId]);
            $externalId = (string) ($external->fetchColumn() ?: '');
            $this->db->prepare("UPDATE reservation_document_deliveries SET status='SENT',external_message_id=?,sent_at=NOW() WHERE id=?")
                ->execute([$externalId ?: null, $deliveryId]);
            (new HistoryService($this->db))->log(
                (int) $document['reservation_id'],
                $document['document_type'] === self::PROPOSAL ? 'PDF_PROPOSTA_ENVIADO' : 'PDF_PAGAMENTO_ENVIADO',
                $document['reservation_status'],
                $document['reservation_status'],
                ['documento_id' => $documentId, 'conversa_id' => (int) $conversation['id']],
                $userId
            );
            return ['delivery_id' => $deliveryId, 'message_id' => $messageId, 'external_message_id' => $externalId];
        } catch (Throwable $error) {
            $safe = preg_replace('/Bearer\s+\S+/i', 'Bearer [redacted]', mb_substr($error->getMessage(), 0, 2000));
            $this->db->prepare("UPDATE reservation_document_deliveries SET status='FAILED',error_message=? WHERE id=?")
                ->execute([$safe, $deliveryId]);
            throw $error;
        }
    }

    public function document(int $documentId): ?array
    {
        $stmt = $this->db->prepare('SELECT d.*,r.codigo,r.nome_cliente,r.telefone,r.status reservation_status
            FROM reservation_documents d JOIN reservas r ON r.id=d.reservation_id WHERE d.id=?');
        $stmt->execute([$documentId]);
        return $stmt->fetch() ?: null;
    }

    public function documentsForReservation(int $reservationId): array
    {
        $stmt = $this->db->prepare('SELECT d.*,
            (SELECT dd.status FROM reservation_document_deliveries dd WHERE dd.document_id=d.id ORDER BY dd.id DESC LIMIT 1) delivery_status,
            (SELECT dd.sent_at FROM reservation_document_deliveries dd WHERE dd.document_id=d.id ORDER BY dd.id DESC LIMIT 1) last_sent_at,
            (SELECT dd.error_message FROM reservation_document_deliveries dd WHERE dd.document_id=d.id ORDER BY dd.id DESC LIMIT 1) delivery_error
            FROM reservation_documents d WHERE d.reservation_id=? ORDER BY d.created_at DESC,d.id DESC');
        $stmt->execute([$reservationId]);
        return $stmt->fetchAll();
    }

    public function latestDocument(int $reservationId, string $type): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM reservation_documents WHERE reservation_id=? AND document_type=? ORDER BY version_no DESC LIMIT 1');
        $stmt->execute([$reservationId, $type]);
        return $stmt->fetch() ?: null;
    }

    public function activeConversation(string $phone): ?array
    {
        $normalized = CustomerRepository::normalizePhone($phone);
        if ($normalized === null) return null;
        $stmt = $this->db->prepare("SELECT * FROM conversas
            WHERE telefone_normalizado=? AND janela_atendimento_ate>=NOW()
            ORDER BY ultima_mensagem_em DESC,id DESC LIMIT 1");
        $stmt->execute([$normalized]);
        return $stmt->fetch() ?: null;
    }

    public function absoluteDocumentPath(string $relative): string
    {
        $path = realpath(BASE_PATH . '/' . ltrim($relative, '/'));
        $root = realpath(BASE_PATH . '/storage/reservation-documents');
        if (!$path || !$root || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
            throw new RuntimeException('Arquivo PDF não encontrado no armazenamento privado.');
        }
        if (file_get_contents($path, false, null, 0, 5) !== '%PDF-') {
            throw new RuntimeException('O arquivo armazenado não é um PDF válido.');
        }
        return $path;
    }

    private function snapshot(array $reservation, array $payments, array $items, array $settings, string $type, string $validUntil): array
    {
        $nights = (new DateTimeImmutable($reservation['checkin']))->diff(new DateTimeImmutable($reservation['checkout']))->days;
        return [
            'document' => [
                'type' => $type,
                'version' => 0,
                'issued_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                'valid_until' => $validUntil,
            ],
            'property' => [
                'name' => $settings['PROPERTY_NAME'] ?? 'Refúgio do Cuscuzeiro',
                'city' => $settings['PROPERTY_CITY'] ?? 'Analândia',
                'state' => $settings['PROPERTY_STATE'] ?? 'SP',
                'address' => $settings['PROPERTY_FULL_ADDRESS'] ?? null,
                'phone' => $settings['OWNER_PHONE'] ?? ($this->config['contact_whatsapp'] ?? null),
                'email' => $settings['OWNER_EMAIL'] ?? null,
                'checkin_time' => $settings['DEFAULT_CHECKIN_TIME'] ?? null,
                'checkout_time' => $settings['DEFAULT_CHECKOUT_TIME'] ?? null,
            ],
            'reservation' => [
                'id' => (int) $reservation['id'],
                'code' => $reservation['codigo'],
                'status' => $reservation['status'],
                'customer_name' => $reservation['nome_cliente'],
                'customer_email' => $reservation['email'],
                'customer_phone' => $reservation['telefone'],
                'checkin' => $reservation['checkin'],
                'checkout' => $reservation['checkout'],
                'nights' => (int) $nights,
                'adults' => (int) $reservation['adultos'],
                'children' => (int) $reservation['criancas'],
                'guests' => (int) $reservation['quantidade_hospedes'],
                'total' => (string) $reservation['valor_total'],
                'signal' => $reservation['valor_sinal'] !== null ? (string) $reservation['valor_sinal'] : null,
                'remaining' => $reservation['valor_restante'] !== null ? (string) $reservation['valor_restante'] : null,
                'customer_notes' => $reservation['observacoes_cliente'],
                'commercial_notes' => $reservation['observacoes_cobranca'],
                'cancellation_policy' => $reservation['politica_cancelamento'],
                'customer_portal_url' => base_url('reserva/' . $reservation['token_publico']),
            ],
            'pricing_items' => array_values(array_map(static fn(array $item): array => [
                'description' => mb_substr(trim(strip_tags((string) ($item['description'] ?? 'Item'))), 0, 160),
                'amount' => (string) ($item['amount'] ?? '0.00'),
            ], $items)),
            'payments' => array_values(array_map(static fn(array $payment): array => [
                'id' => (int) $payment['id'],
                'type' => $payment['tipo'],
                'amount' => (string) $payment['valor'],
                'status' => $payment['status'],
                'due_at' => $payment['data_vencimento'],
                'pix_copy_paste' => $payment['pix_copia_cola'],
                'qr_code_path' => $payment['qr_code_path'],
                'notes' => $payment['observacoes'],
            ], $payments)),
        ];
    }

    private function reservation(int $reservationId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM reservas WHERE id=?');
        $stmt->execute([$reservationId]);
        return $stmt->fetch() ?: throw new RuntimeException('Pedido de reserva não encontrado.');
    }

    private function payments(int $reservationId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM pagamentos WHERE reserva_id=? ORDER BY data_vencimento,id');
        $stmt->execute([$reservationId]);
        return $stmt->fetchAll();
    }

    private function nextVersion(int $reservationId, string $type): int
    {
        $stmt = $this->db->prepare('SELECT COALESCE(MAX(version_no),0)+1 FROM reservation_documents WHERE reservation_id=? AND document_type=?');
        $stmt->execute([$reservationId, $type]);
        return max(1, (int) $stmt->fetchColumn());
    }

    private function proposalValidity(mixed $value): string
    {
        $validity = $value ? new DateTimeImmutable((string) $value) : (new DateTimeImmutable())->modify('+24 hours');
        if ($validity <= new DateTimeImmutable()) throw new RuntimeException('A validade da proposta já expirou. Informe um novo prazo.');
        return $validity->format('Y-m-d H:i:s');
    }

    private function paymentValidity(array $pendingPayments): string
    {
        $dates = array_map(static fn(array $payment): string => (string) $payment['data_vencimento'], $pendingPayments);
        sort($dates);
        if (new DateTimeImmutable($dates[0]) <= new DateTimeImmutable()) {
            throw new RuntimeException('A cobrança está vencida. Atualize o prazo antes de reemitir as instruções de pagamento.');
        }
        return $dates[0];
    }

    private function logoPath(): ?string
    {
        foreach ([
            BASE_PATH . '/assets/images/logo_refugio_certo.png',
            BASE_PATH . '/assets/images/logo_refugio.png',
            BASE_PATH . '/assets/images/logo_transparente.png',
        ] as $candidate) {
            if (is_file($candidate)) return $candidate;
        }
        return null;
    }

    private function qrCodePath(array $pendingPayments): ?string
    {
        foreach ($pendingPayments as $payment) {
            if (empty($payment['qr_code_path'])) continue;
            $path = realpath(BASE_PATH . '/' . ltrim((string) $payment['qr_code_path'], '/'));
            $root = realpath(BASE_PATH . '/storage/qrcodes');
            if ($path && $root && str_starts_with($path, $root . DIRECTORY_SEPARATOR) && is_file($path)) return $path;
        }
        return null;
    }

    private function runGenerator(string $payload, string $output): void
    {
        $command = [$this->pythonBinary, BASE_PATH . '/scripts/generate_reservation_pdf.py', '--input', $payload, '--output', $output];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, BASE_PATH);
        if (!is_resource($process)) throw new RuntimeException('O gerador do PDF de reserva não pôde ser iniciado.');
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        if ($exit !== 0 || !is_file($output) || filesize($output) < 1000) {
            @unlink($output);
            throw new RuntimeException('Falha ao gerar PDF de reserva: ' . mb_substr(trim((string) $stderr . (string) $stdout), 0, 1000));
        }
        if (file_get_contents($output, false, null, 0, 5) !== '%PDF-') {
            @unlink($output);
            throw new RuntimeException('O gerador produziu um arquivo PDF inválido.');
        }
    }

    private function relativePath(string $absolute): string
    {
        return ltrim(str_replace('\\', '/', substr($absolute, strlen(BASE_PATH))), '/');
    }

    private function firstName(string $name): string
    {
        return explode(' ', trim($name))[0] ?: 'cliente';
    }
}

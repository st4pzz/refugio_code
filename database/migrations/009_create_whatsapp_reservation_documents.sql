-- Pedidos de reserva emitidos pelo admin, PDFs versionados e entregas por WhatsApp.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS reservation_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reservation_id BIGINT UNSIGNED NOT NULL,
    payment_id BIGINT UNSIGNED NULL,
    document_type VARCHAR(24) NOT NULL,
    version_no SMALLINT UNSIGNED NOT NULL,
    valid_until DATETIME NULL,
    storage_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100) NOT NULL DEFAULT 'application/pdf',
    byte_size BIGINT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    snapshot_json JSON NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_reservation_document_version (reservation_id,document_type,version_no),
    KEY idx_reservation_document_reservation (reservation_id,created_at),
    KEY idx_reservation_document_payment (payment_id),
    CONSTRAINT fk_reservation_document_reservation FOREIGN KEY (reservation_id) REFERENCES reservas(id) ON DELETE CASCADE,
    CONSTRAINT fk_reservation_document_payment FOREIGN KEY (payment_id) REFERENCES pagamentos(id) ON DELETE SET NULL,
    CONSTRAINT fk_reservation_document_user FOREIGN KEY (created_by) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    CONSTRAINT chk_reservation_document_type CHECK (document_type IN ('PROPOSAL','PAYMENT_REQUEST')),
    CONSTRAINT chk_reservation_document_size CHECK (byte_size > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservation_document_deliveries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id BIGINT UNSIGNED NOT NULL,
    conversation_id BIGINT UNSIGNED NULL,
    channel VARCHAR(16) NOT NULL DEFAULT 'WHATSAPP',
    destination VARCHAR(30) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    external_message_id VARCHAR(190) NULL,
    error_message TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_reservation_document_delivery (document_id,created_at),
    KEY idx_reservation_document_delivery_status (status,created_at),
    CONSTRAINT fk_reservation_document_delivery_document FOREIGN KEY (document_id) REFERENCES reservation_documents(id) ON DELETE CASCADE,
    CONSTRAINT fk_reservation_document_delivery_conversation FOREIGN KEY (conversation_id) REFERENCES conversas(id) ON DELETE SET NULL,
    CONSTRAINT fk_reservation_document_delivery_user FOREIGN KEY (created_by) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    CONSTRAINT chk_reservation_document_delivery_channel CHECK (channel IN ('WHATSAPP')),
    CONSTRAINT chk_reservation_document_delivery_status CHECK (status IN ('PENDING','SENT','FAILED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

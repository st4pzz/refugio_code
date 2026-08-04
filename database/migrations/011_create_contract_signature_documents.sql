-- PDFs assinados externamente no Gov.br, com versoes e trilha de auditoria.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS contract_signature_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id BIGINT UNSIGNED NOT NULL,
    stage VARCHAR(24) NOT NULL,
    revision_no INT UNSIGNED NOT NULL,
    storage_path VARCHAR(500) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    byte_size BIGINT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    uploaded_by_role VARCHAR(16) NOT NULL,
    uploaded_by_admin_id BIGINT UNSIGNED NULL,
    uploaded_ip VARCHAR(45) NULL,
    uploaded_user_agent VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_contract_signature_revision (contract_id,stage,revision_no),
    UNIQUE KEY uk_contract_signature_hash (contract_id,stage,sha256),
    KEY idx_contract_signature_latest (contract_id,stage,created_at),
    CONSTRAINT fk_contract_signature_contract FOREIGN KEY (contract_id) REFERENCES reservation_contracts(id) ON DELETE CASCADE,
    CONSTRAINT fk_contract_signature_admin FOREIGN KEY (uploaded_by_admin_id) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    CONSTRAINT chk_contract_signature_stage CHECK (stage IN ('GUEST_SIGNED','FULLY_SIGNED')),
    CONSTRAINT chk_contract_signature_role CHECK (uploaded_by_role IN ('GUEST','OWNER'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

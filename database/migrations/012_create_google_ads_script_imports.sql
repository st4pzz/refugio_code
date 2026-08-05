-- Recebimentos idempotentes enviados por Google Ads Scripts.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS marketing_google_ads_script_imports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    integracao_id BIGINT UNSIGNED NOT NULL,
    request_id VARCHAR(100) NOT NULL,
    payload_sha256 CHAR(64) NOT NULL,
    data_inicio DATE NULL,
    data_fim DATE NULL,
    campanhas_processadas SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    metricas_processadas INT UNSIGNED NOT NULL DEFAULT 0,
    source_ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_google_ads_script_request (request_id),
    KEY idx_google_ads_script_integracao (integracao_id,created_at),
    CONSTRAINT fk_google_ads_script_integracao FOREIGN KEY (integracao_id) REFERENCES marketing_integracoes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

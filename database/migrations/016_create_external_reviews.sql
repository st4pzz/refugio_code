-- Avaliacoes externas e integracao Google Business Profile.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS review_integrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(16) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'DESCONECTADA',
    access_token_encrypted TEXT NULL,
    refresh_token_encrypted TEXT NULL,
    token_expires_at DATETIME NULL,
    ultima_sincronizacao_em DATETIME NULL,
    erro_ultima_sincronizacao VARCHAR(1000) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_review_integration_provider (provider),
    CONSTRAINT fk_review_integration_admin FOREIGN KEY (created_by) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    CONSTRAINT chk_review_integration_provider CHECK (provider IN ('GOOGLE')),
    CONSTRAINT chk_review_integration_status CHECK (status IN ('CONECTADA','ERRO','EXPIRADA','DESCONECTADA'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE avaliacoes
    MODIFY reserva_id BIGINT UNSIGNED NULL,
    MODIFY convite_avaliacao_id BIGINT UNSIGNED NULL,
    MODIFY nota_limpeza TINYINT UNSIGNED NULL,
    MODIFY nota_localizacao TINYINT UNSIGNED NULL,
    MODIFY nota_conforto TINYINT UNSIGNED NULL,
    MODIFY nota_comunicacao TINYINT UNSIGNED NULL,
    MODIFY nota_custo_beneficio TINYINT UNSIGNED NULL,
    ADD COLUMN origem_plataforma VARCHAR(16) NOT NULL DEFAULT 'SITE_DIRETO' AFTER convite_avaliacao_id,
    ADD COLUMN external_review_id VARCHAR(190) NULL AFTER origem_plataforma,
    ADD COLUMN external_url VARCHAR(1000) NULL AFTER external_review_id,
    ADD COLUMN avaliacao_em DATETIME NULL AFTER external_url,
    ADD COLUMN importada_em DATETIME NULL AFTER avaliacao_em,
    ADD COLUMN expira_em DATETIME NULL AFTER importada_em,
    ADD COLUMN created_by BIGINT UNSIGNED NULL AFTER aprovada_por_usuario_id,
    ADD UNIQUE KEY uk_avaliacao_origem_externa (origem_plataforma,external_review_id),
    ADD KEY idx_avaliacao_expiracao_externa (origem_plataforma,expira_em),
    ADD CONSTRAINT fk_avaliacao_created_by FOREIGN KEY (created_by) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    ADD CONSTRAINT chk_avaliacao_origem_plataforma CHECK (origem_plataforma IN ('SITE_DIRETO','GOOGLE','BOOKING','AIRBNB'));

UPDATE avaliacoes a
JOIN reservas r ON r.id=a.reserva_id
SET a.origem_plataforma=CASE WHEN r.origem IN ('AIRBNB','BOOKING') THEN r.origem ELSE 'SITE_DIRETO' END;

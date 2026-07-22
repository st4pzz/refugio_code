-- Sistema de avaliacoes verificadas. Execute depois de 001_create_reservas.sql.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS convites_avaliacao (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reserva_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL COMMENT 'SHA-256 do token; o token original nunca e persistido',
    status VARCHAR(16) NOT NULL DEFAULT 'PENDENTE',
    expira_em DATETIME NOT NULL,
    utilizado_em DATETIME NULL,
    revogado_em DATETIME NULL,
    enviado_email_em DATETIME NULL,
    enviado_whatsapp_em DATETIME NULL,
    ultimo_envio_em DATETIME NULL,
    lembrete_enviado_em DATETIME NULL,
    quantidade_envios SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_convite_reserva (reserva_id),
    UNIQUE KEY uk_convite_token (token_hash),
    KEY idx_convite_status_expiracao (status, expira_em),
    KEY idx_convite_lembrete (status, lembrete_enviado_em, ultimo_envio_em),
    CONSTRAINT fk_convite_reserva FOREIGN KEY (reserva_id) REFERENCES reservas(id) ON DELETE CASCADE,
    CONSTRAINT chk_convite_status CHECK (status IN ('PENDENTE','ENVIADO','UTILIZADO','EXPIRADO','REVOGADO'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS avaliacoes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reserva_id BIGINT UNSIGNED NOT NULL,
    convite_avaliacao_id BIGINT UNSIGNED NOT NULL,
    nome_exibicao VARCHAR(160) NOT NULL,
    nota_geral TINYINT UNSIGNED NOT NULL,
    nota_limpeza TINYINT UNSIGNED NOT NULL,
    nota_localizacao TINYINT UNSIGNED NOT NULL,
    nota_conforto TINYINT UNSIGNED NOT NULL,
    nota_comunicacao TINYINT UNSIGNED NOT NULL,
    nota_custo_beneficio TINYINT UNSIGNED NOT NULL,
    comentario TEXT NOT NULL COMMENT 'Conteudo original sanitizado; nunca sobrescrever na moderacao',
    resposta_administrador VARCHAR(1000) NULL,
    motivo_moderacao VARCHAR(1000) NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'PENDENTE',
    autoriza_publicacao TINYINT(1) NOT NULL DEFAULT 0,
    anonima TINYINT(1) NOT NULL DEFAULT 0,
    enviada_em DATETIME NOT NULL,
    aprovada_em DATETIME NULL,
    rejeitada_em DATETIME NULL,
    ocultada_em DATETIME NULL,
    aprovada_por_usuario_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_avaliacao_reserva (reserva_id),
    UNIQUE KEY uk_avaliacao_convite (convite_avaliacao_id),
    KEY idx_avaliacao_status (status),
    KEY idx_avaliacao_nota (nota_geral),
    KEY idx_avaliacao_aprovada (aprovada_em),
    KEY idx_avaliacao_created (created_at),
    CONSTRAINT fk_avaliacao_reserva FOREIGN KEY (reserva_id) REFERENCES reservas(id),
    CONSTRAINT fk_avaliacao_convite FOREIGN KEY (convite_avaliacao_id) REFERENCES convites_avaliacao(id),
    CONSTRAINT fk_avaliacao_admin FOREIGN KEY (aprovada_por_usuario_id) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    CONSTRAINT chk_avaliacao_status CHECK (status IN ('PENDENTE','APROVADA','REJEITADA','OCULTA')),
    CONSTRAINT chk_avaliacao_notas CHECK (
        nota_geral BETWEEN 1 AND 5 AND nota_limpeza BETWEEN 1 AND 5 AND
        nota_localizacao BETWEEN 1 AND 5 AND nota_conforto BETWEEN 1 AND 5 AND
        nota_comunicacao BETWEEN 1 AND 5 AND nota_custo_beneficio BETWEEN 1 AND 5
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

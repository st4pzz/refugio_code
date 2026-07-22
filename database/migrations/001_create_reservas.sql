-- Execute em MySQL 8+ ou MariaDB 10.5+ depois de realizar backup.
SET NAMES utf8mb4;
SET time_zone = '-03:00';

CREATE TABLE IF NOT EXISTS usuarios_admin (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ultimo_login_em DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_admin_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(32) NOT NULL,
    token_publico VARCHAR(80) NOT NULL COMMENT 'Token aleatorio entregue ao cliente',
    idempotency_key CHAR(64) NULL,
    nome_cliente VARCHAR(160) NOT NULL,
    cpf_cliente VARCHAR(14) NULL,
    email VARCHAR(190) NOT NULL,
    telefone VARCHAR(20) NOT NULL,
    checkin DATE NOT NULL,
    checkout DATE NOT NULL,
    adultos SMALLINT UNSIGNED NOT NULL,
    criancas SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    quantidade_hospedes SMALLINT UNSIGNED NOT NULL,
    valor_total DECIMAL(12,2) NULL,
    valor_sinal DECIMAL(12,2) NULL,
    valor_restante DECIMAL(12,2) NULL,
    status VARCHAR(32) NOT NULL,
    prazo_pagamento DATETIME NULL,
    observacoes_cliente TEXT NULL,
    observacoes_internas TEXT NULL,
    observacoes_cobranca TEXT NULL,
    politica_cancelamento TEXT NULL,
    origem VARCHAR(20) NOT NULL DEFAULT 'SITE_DIRETO',
    termos_aceitos_em DATETIME NOT NULL,
    regras_aceitas TINYINT(1) NOT NULL DEFAULT 0,
    cancelamento_aceito TINYINT(1) NOT NULL DEFAULT 0,
    whatsapp_autorizado TINYINT(1) NOT NULL DEFAULT 0,
    finalidade_coleta VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_reserva_codigo (codigo),
    UNIQUE KEY uk_reserva_token (token_publico),
    UNIQUE KEY uk_reserva_idempotency (idempotency_key),
    KEY idx_reserva_status (status),
    KEY idx_reserva_datas (checkin, checkout),
    KEY idx_reserva_email (email),
    KEY idx_reserva_telefone (telefone),
    CONSTRAINT chk_reserva_datas CHECK (checkout > checkin),
    CONSTRAINT chk_reserva_hospedes CHECK (adultos > 0 AND quantidade_hospedes = adultos + criancas),
    CONSTRAINT chk_reserva_origem CHECK (origem IN ('SITE_DIRETO','AIRBNB','BOOKING','MANUAL')),
    CONSTRAINT chk_reserva_status CHECK (status IN ('AGUARDANDO_APROVACAO','AGUARDANDO_PAGAMENTO','COMPROVANTE_ENVIADO','PAGAMENTO_CONFIRMADO','RESERVA_CONFIRMADA','RECUSADA','EXPIRADA','CANCELADA','FINALIZADA'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pagamentos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reserva_id BIGINT UNSIGNED NOT NULL,
    tipo VARCHAR(16) NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    pix_copia_cola TEXT NULL,
    qr_code_path VARCHAR(255) NULL,
    comprovante_path VARCHAR(255) NULL,
    comprovante_nome_original VARCHAR(255) NULL,
    comprovante_mime VARCHAR(100) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'PENDENTE',
    data_vencimento DATETIME NOT NULL,
    data_confirmacao DATETIME NULL,
    observacoes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_pagamento_reserva (reserva_id),
    KEY idx_pagamento_status_vencimento (status, data_vencimento),
    CONSTRAINT fk_pagamento_reserva FOREIGN KEY (reserva_id) REFERENCES reservas(id),
    CONSTRAINT chk_pagamento_tipo CHECK (tipo IN ('SINAL','SALDO','INTEGRAL','CAUCAO','OUTRO')),
    CONSTRAINT chk_pagamento_status CHECK (status IN ('PENDENTE','COMPROVANTE_ENVIADO','CONFIRMADO','RECUSADO','EXPIRADO','REEMBOLSADO')),
    CONSTRAINT chk_pagamento_valor CHECK (valor > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notificacoes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reserva_id BIGINT UNSIGNED NOT NULL,
    canal VARCHAR(16) NOT NULL,
    tipo VARCHAR(64) NOT NULL,
    destinatario VARCHAR(190) NOT NULL,
    conteudo MEDIUMTEXT NOT NULL,
    status VARCHAR(20) NOT NULL,
    id_mensagem_externa VARCHAR(190) NULL,
    erro TEXT NULL,
    tentativas SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    enviado_em DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notificacao_reserva (reserva_id),
    KEY idx_notificacao_status (status),
    CONSTRAINT fk_notificacao_reserva FOREIGN KEY (reserva_id) REFERENCES reservas(id),
    CONSTRAINT chk_notificacao_canal CHECK (canal IN ('EMAIL','WHATSAPP'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS historico_reserva (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reserva_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NULL,
    acao VARCHAR(80) NOT NULL,
    status_anterior VARCHAR(32) NULL,
    status_novo VARCHAR(32) NULL,
    detalhes JSON NULL,
    ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_historico_reserva (reserva_id, created_at),
    CONSTRAINT fk_historico_reserva FOREIGN KEY (reserva_id) REFERENCES reservas(id),
    CONSTRAINT fk_historico_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_admin(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS datas_bloqueadas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    motivo VARCHAR(255) NOT NULL,
    reserva_id BIGINT UNSIGNED NULL,
    origem VARCHAR(30) NOT NULL DEFAULT 'MANUAL',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_bloqueio_datas (data_inicio, data_fim),
    KEY idx_bloqueio_reserva (reserva_id),
    CONSTRAINT fk_bloqueio_reserva FOREIGN KEY (reserva_id) REFERENCES reservas(id) ON DELETE CASCADE,
    CONSTRAINT chk_bloqueio_datas CHECK (data_fim > data_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
    chave CHAR(64) PRIMARY KEY,
    tentativas INT UNSIGNED NOT NULL DEFAULT 1,
    janela_inicio DATETIME NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reserva_mutex (
    id TINYINT UNSIGNED PRIMARY KEY,
    nome VARCHAR(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO reserva_mutex (id, nome) VALUES (1, 'aprovacao');

-- Inbox compartilhada do WhatsApp Cloud API.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS whatsapp_webhook_eventos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_hash CHAR(64) NOT NULL,
    object_type VARCHAR(80) NULL,
    payload_json JSON NOT NULL,
    assinatura_valida TINYINT(1) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'RECEBIDO',
    erro TEXT NULL,
    recebido_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processado_em DATETIME NULL,
    UNIQUE KEY uk_whatsapp_event_hash (event_hash),
    KEY idx_whatsapp_event_status (status,recebido_em),
    CONSTRAINT chk_whatsapp_event_status CHECK (status IN ('RECEBIDO','PROCESSANDO','PROCESSADO','FALHOU','IGNORADO'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conversas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    canal VARCHAR(20) NOT NULL DEFAULT 'WHATSAPP',
    telefone VARCHAR(30) NOT NULL,
    telefone_normalizado VARCHAR(20) NOT NULL,
    wa_id VARCHAR(80) NULL,
    nome_contato VARCHAR(160) NULL,
    cliente_id BIGINT UNSIGNED NULL,
    lead_id BIGINT UNSIGNED NULL,
    reserva_id BIGINT UNSIGNED NULL,
    atendente_id BIGINT UNSIGNED NULL,
    status VARCHAR(28) NOT NULL DEFAULT 'NOVA',
    prioridade VARCHAR(12) NOT NULL DEFAULT 'NORMAL',
    origem VARCHAR(80) NULL,
    primeira_mensagem_em DATETIME NULL,
    ultima_mensagem_em DATETIME NULL,
    ultima_mensagem_preview VARCHAR(255) NULL,
    nao_lidas INT UNSIGNED NOT NULL DEFAULT 0,
    janela_atendimento_ate DATETIME NULL,
    tags_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_conversa_canal_telefone (canal,telefone_normalizado),
    UNIQUE KEY uk_conversa_wa_id (wa_id),
    KEY idx_conversa_inbox (status,ultima_mensagem_em),
    KEY idx_conversa_atendente (atendente_id,status),
    KEY idx_conversa_cliente (cliente_id),
    KEY idx_conversa_lead (lead_id),
    KEY idx_conversa_reserva (reserva_id),
    CONSTRAINT fk_conversa_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
    CONSTRAINT fk_conversa_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL,
    CONSTRAINT fk_conversa_reserva FOREIGN KEY (reserva_id) REFERENCES reservas(id) ON DELETE SET NULL,
    CONSTRAINT fk_conversa_atendente FOREIGN KEY (atendente_id) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    CONSTRAINT chk_conversa_canal CHECK (canal IN ('WHATSAPP')),
    CONSTRAINT chk_conversa_status CHECK (status IN ('NOVA','EM_ATENDIMENTO','AGUARDANDO_CLIENTE','AGUARDANDO_EQUIPE','CONVERTIDA','FINALIZADA','ARQUIVADA','SPAM')),
    CONSTRAINT chk_conversa_prioridade CHECK (prioridade IN ('BAIXA','NORMAL','ALTA','URGENTE'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mensagens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversa_id BIGINT UNSIGNED NOT NULL,
    external_message_id VARCHAR(190) NOT NULL,
    direcao VARCHAR(8) NOT NULL,
    tipo VARCHAR(24) NOT NULL,
    texto TEXT NULL,
    media_id VARCHAR(190) NULL,
    media_path VARCHAR(255) NULL,
    media_mime VARCHAR(120) NULL,
    media_nome VARCHAR(255) NULL,
    template_name VARCHAR(190) NULL,
    template_language VARCHAR(20) NULL,
    payload_json JSON NOT NULL,
    status VARCHAR(16) NOT NULL,
    erro TEXT NULL,
    respondendo_a_id BIGINT UNSIGNED NULL,
    enviada_por_usuario_id BIGINT UNSIGNED NULL,
    enviada_em DATETIME NULL,
    recebida_em DATETIME NULL,
    entregue_em DATETIME NULL,
    lida_em DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_mensagem_external (external_message_id),
    KEY idx_mensagem_conversa (conversa_id,created_at),
    KEY idx_mensagem_status (status,updated_at),
    KEY idx_mensagem_media (media_id),
    CONSTRAINT fk_mensagem_conversa FOREIGN KEY (conversa_id) REFERENCES conversas(id) ON DELETE CASCADE,
    CONSTRAINT fk_mensagem_resposta FOREIGN KEY (respondendo_a_id) REFERENCES mensagens(id) ON DELETE SET NULL,
    CONSTRAINT fk_mensagem_usuario FOREIGN KEY (enviada_por_usuario_id) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    CONSTRAINT chk_mensagem_direcao CHECK (direcao IN ('ENTRADA','SAIDA')),
    CONSTRAINT chk_mensagem_status CHECK (status IN ('RECEBIDA','PENDENTE','ENVIADA','ENTREGUE','LIDA','FALHA')),
    CONSTRAINT chk_mensagem_tipo CHECK (tipo IN ('TEXTO','IMAGEM','DOCUMENTO','AUDIO','VIDEO','LOCALIZACAO','CONTATO','BOTAO','INTERATIVA','TEMPLATE','STICKER','DESCONHECIDA'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conversa_tags (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(80) NOT NULL,
    cor CHAR(7) NOT NULL DEFAULT '#737469',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_conversa_tag_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO conversa_tags (nome,cor) VALUES
('Novo lead','#2d6cdf'),('Interessado em reserva','#7a4db3'),('Solicitou orçamento','#a86c0b'),
('Aguardando pagamento','#c77b00'),('Reserva confirmada','#257148'),('Pós-venda','#338a8a'),
('Reclamação','#a13232'),('Spam','#666666')
ON DUPLICATE KEY UPDATE cor=VALUES(cor),ativo=1;

CREATE TABLE IF NOT EXISTS conversa_tag_vinculos (
    conversa_id BIGINT UNSIGNED NOT NULL,
    tag_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (conversa_id,tag_id),
    KEY idx_conversa_tag_tag (tag_id),
    CONSTRAINT fk_conversa_tag_conversa FOREIGN KEY (conversa_id) REFERENCES conversas(id) ON DELETE CASCADE,
    CONSTRAINT fk_conversa_tag_tag FOREIGN KEY (tag_id) REFERENCES conversa_tags(id) ON DELETE CASCADE,
    CONSTRAINT fk_conversa_tag_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_admin(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    external_id VARCHAR(190) NULL,
    nome VARCHAR(190) NOT NULL,
    idioma VARCHAR(20) NOT NULL,
    categoria VARCHAR(40) NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'DESCONHECIDO',
    componentes_json JSON NULL,
    ultima_sincronizacao_em DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_whatsapp_template (nome,idioma)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conversa_notas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversa_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NULL,
    texto VARCHAR(2000) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_conversa_nota (conversa_id,created_at),
    CONSTRAINT fk_conversa_nota_conversa FOREIGN KEY (conversa_id) REFERENCES conversas(id) ON DELETE CASCADE,
    CONSTRAINT fk_conversa_nota_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_admin(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

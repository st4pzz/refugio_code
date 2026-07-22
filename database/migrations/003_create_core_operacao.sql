-- Nucleo compartilhado da central de operacao. Execute apos backup e migrations 001/002.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS perfis_admin (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(32) NOT NULL,
    nome VARCHAR(80) NOT NULL,
    descricao VARCHAR(255) NULL,
    permissoes_json JSON NOT NULL,
    sistema TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_perfil_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO perfis_admin (codigo,nome,descricao,permissoes_json) VALUES
('SUPER_ADMIN','Super administrador','Acesso completo, inclusive integracoes e perfis',JSON_ARRAY('*')),
('ADMIN','Administrador','Operacao completa sem gestao de perfis sensiveis',JSON_ARRAY('dashboard.view','reservas.*','clientes.*','conversas.*','marketing.view','marketing.sync','financeiro.*','avaliacoes.*','configuracoes.view')),
('ATENDIMENTO','Atendimento','Clientes, reservas e conversas',JSON_ARRAY('dashboard.view','reservas.view','reservas.create','clientes.view','clientes.update','conversas.*','avaliacoes.view')),
('MARKETING','Marketing','Dashboards, atribuicoes e sincronizacao de marketing',JSON_ARRAY('dashboard.view','clientes.view','reservas.view','marketing.view','marketing.sync','marketing.attribution')),
('FINANCEIRO','Financeiro','Recebimentos, despesas, caixa e exportacoes',JSON_ARRAY('dashboard.view','reservas.view','clientes.view','financeiro.*')),
('LEITURA','Leitura','Visualizacao sem alteracoes',JSON_ARRAY('dashboard.view','reservas.view','clientes.view','conversas.view','marketing.view','financeiro.view','avaliacoes.view'))
ON DUPLICATE KEY UPDATE nome=VALUES(nome),descricao=VALUES(descricao),permissoes_json=VALUES(permissoes_json);

CREATE TABLE IF NOT EXISTS usuarios_admin_perfis (
    usuario_id BIGINT UNSIGNED NOT NULL,
    perfil_id BIGINT UNSIGNED NOT NULL,
    atribuido_por BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id,perfil_id),
    KEY idx_usuario_perfil_perfil (perfil_id),
    CONSTRAINT fk_usuario_perfil_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_admin(id) ON DELETE CASCADE,
    CONSTRAINT fk_usuario_perfil_perfil FOREIGN KEY (perfil_id) REFERENCES perfis_admin(id) ON DELETE CASCADE,
    CONSTRAINT fk_usuario_perfil_autor FOREIGN KEY (atribuido_por) REFERENCES usuarios_admin(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Preserva acesso dos administradores existentes na primeira implantacao.
INSERT IGNORE INTO usuarios_admin_perfis (usuario_id,perfil_id)
SELECT u.id,p.id FROM usuarios_admin u JOIN perfis_admin p ON p.codigo='SUPER_ADMIN';

CREATE TABLE IF NOT EXISTS clientes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(160) NOT NULL,
    cpf VARCHAR(14) NULL,
    email VARCHAR(190) NULL,
    telefone VARCHAR(30) NULL,
    telefone_normalizado VARCHAR(20) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ATIVO',
    observacoes TEXT NULL,
    whatsapp_autorizado TINYINT(1) NOT NULL DEFAULT 0,
    consentimento_marketing TINYINT(1) NOT NULL DEFAULT 0,
    anonimizado_em DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_cliente_telefone (telefone_normalizado),
    KEY idx_cliente_email (email),
    KEY idx_cliente_cpf (cpf),
    KEY idx_cliente_nome (nome),
    CONSTRAINT chk_cliente_status CHECK (status IN ('ATIVO','INATIVO','ANONIMIZADO'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id BIGINT UNSIGNED NULL,
    nome VARCHAR(160) NULL,
    email VARCHAR(190) NULL,
    telefone VARCHAR(30) NULL,
    telefone_normalizado VARCHAR(20) NULL,
    canal VARCHAR(32) NOT NULL,
    origem VARCHAR(80) NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'NOVO',
    atendente_id BIGINT UNSIGNED NULL,
    primeiro_contato_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_contato_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    dados_json JSON NULL,
    convertido_em DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_lead_canal_telefone (canal,telefone_normalizado),
    KEY idx_lead_status_contato (status,ultimo_contato_em),
    KEY idx_lead_cliente (cliente_id),
    KEY idx_lead_atendente (atendente_id),
    CONSTRAINT fk_lead_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
    CONSTRAINT fk_lead_atendente FOREIGN KEY (atendente_id) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    CONSTRAINT chk_lead_status CHECK (status IN ('NOVO','QUALIFICANDO','QUALIFICADO','CONVERTIDO','PERDIDO','SPAM'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reserva_contatos (
    reserva_id BIGINT UNSIGNED PRIMARY KEY,
    cliente_id BIGINT UNSIGNED NULL,
    lead_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_reserva_contato_cliente (cliente_id),
    KEY idx_reserva_contato_lead (lead_id),
    CONSTRAINT fk_reserva_contato_reserva FOREIGN KEY (reserva_id) REFERENCES reservas(id) ON DELETE CASCADE,
    CONSTRAINT fk_reserva_contato_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
    CONSTRAINT fk_reserva_contato_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auditoria (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id BIGINT UNSIGNED NULL,
    modulo VARCHAR(40) NOT NULL,
    acao VARCHAR(80) NOT NULL,
    entidade VARCHAR(80) NULL,
    entidade_id VARCHAR(80) NULL,
    antes_json JSON NULL,
    depois_json JSON NULL,
    metadados_json JSON NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    correlation_id CHAR(32) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_auditoria_entidade (entidade,entidade_id,created_at),
    KEY idx_auditoria_usuario (usuario_id,created_at),
    KEY idx_auditoria_modulo (modulo,created_at),
    CONSTRAINT fk_auditoria_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_admin(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(80) NOT NULL,
    payload_json JSON NOT NULL,
    chave_unica VARCHAR(190) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDENTE',
    prioridade SMALLINT NOT NULL DEFAULT 100,
    tentativas SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    max_tentativas SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    disponivel_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    bloqueado_em DATETIME NULL,
    bloqueado_por VARCHAR(100) NULL,
    erro_ultimo TEXT NULL,
    finalizado_em DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_job_chave (chave_unica),
    KEY idx_job_processamento (status,disponivel_em,prioridade,id),
    CONSTRAINT chk_job_status CHECK (status IN ('PENDENTE','PROCESSANDO','CONCLUIDO','FALHOU','CANCELADO'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS configuracoes_sistema (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    namespace VARCHAR(40) NOT NULL,
    chave VARCHAR(80) NOT NULL,
    valor_json JSON NOT NULL,
    atualizado_por BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_config_namespace_chave (namespace,chave),
    CONSTRAINT fk_config_usuario FOREIGN KEY (atualizado_por) REFERENCES usuarios_admin(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

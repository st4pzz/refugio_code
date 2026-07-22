-- Modulo financeiro compartilhado. Valores monetarios usam DECIMAL, nunca FLOAT.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS contas_financeiras (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    tipo VARCHAR(24) NOT NULL,
    banco VARCHAR(100) NULL,
    agencia VARCHAR(30) NULL,
    conta VARCHAR(50) NULL,
    moeda CHAR(3) NOT NULL DEFAULT 'BRL',
    saldo_inicial DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    data_saldo_inicial DATE NOT NULL,
    ativa TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_conta_financeira_ativa (ativa,nome),
    CONSTRAINT chk_conta_financeira_tipo CHECK (tipo IN ('CAIXA','CONTA_CORRENTE','POUPANCA','CARTEIRA_DIGITAL','OUTRA'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO contas_financeiras (nome,tipo,moeda,saldo_inicial,data_saldo_inicial)
SELECT 'Conta principal','CONTA_CORRENTE','BRL',0.00,CURDATE()
WHERE NOT EXISTS (SELECT 1 FROM contas_financeiras);

CREATE TABLE IF NOT EXISTS categorias_financeiras (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NULL,
    nome VARCHAR(120) NOT NULL,
    tipo VARCHAR(12) NOT NULL,
    ativa TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_categoria_tipo_nome (tipo,nome),
    KEY idx_categoria_parent (parent_id),
    CONSTRAINT fk_categoria_parent FOREIGN KEY (parent_id) REFERENCES categorias_financeiras(id) ON DELETE SET NULL,
    CONSTRAINT chk_categoria_tipo CHECK (tipo IN ('RECEITA','DESPESA','AMBOS'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categorias_financeiras (nome,tipo) VALUES
('Hospedagem','RECEITA'),('Caução','RECEITA'),('Marketing','DESPESA'),('Manutenção','DESPESA'),
('Limpeza','DESPESA'),('Energia e água','DESPESA'),('Impostos e taxas','DESPESA'),('Outros','AMBOS')
ON DUPLICATE KEY UPDATE ativa=1;

CREATE TABLE IF NOT EXISTS fornecedores (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(160) NOT NULL,
    documento VARCHAR(20) NULL,
    email VARCHAR(190) NULL,
    telefone VARCHAR(30) NULL,
    observacoes TEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_fornecedor_nome (nome),
    KEY idx_fornecedor_documento (documento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contas_receber (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reserva_id BIGINT UNSIGNED NULL,
    cliente_id BIGINT UNSIGNED NULL,
    categoria_id BIGINT UNSIGNED NULL,
    conta_id BIGINT UNSIGNED NULL,
    pagamento_reserva_id BIGINT UNSIGNED NULL,
    descricao VARCHAR(255) NOT NULL,
    tipo VARCHAR(20) NOT NULL DEFAULT 'OUTRO',
    numero_parcela SMALLINT UNSIGNED NULL,
    total_parcelas SMALLINT UNSIGNED NULL,
    valor_original DECIMAL(14,2) NOT NULL,
    valor_recebido DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    valor_estornado DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    moeda CHAR(3) NOT NULL DEFAULT 'BRL',
    vencimento DATE NOT NULL,
    competencia DATE NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDENTE',
    observacoes TEXT NULL,
    criado_por BIGINT UNSIGNED NULL,
    cancelado_em DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_receber_pagamento_reserva (pagamento_reserva_id),
    KEY idx_receber_status_vencimento (status,vencimento),
    KEY idx_receber_reserva (reserva_id),
    KEY idx_receber_cliente (cliente_id),
    CONSTRAINT fk_receber_reserva FOREIGN KEY (reserva_id) REFERENCES reservas(id) ON DELETE SET NULL,
    CONSTRAINT fk_receber_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
    CONSTRAINT fk_receber_categoria FOREIGN KEY (categoria_id) REFERENCES categorias_financeiras(id) ON DELETE SET NULL,
    CONSTRAINT fk_receber_conta FOREIGN KEY (conta_id) REFERENCES contas_financeiras(id) ON DELETE SET NULL,
    CONSTRAINT fk_receber_pagamento_reserva FOREIGN KEY (pagamento_reserva_id) REFERENCES pagamentos(id) ON DELETE SET NULL,
    CONSTRAINT fk_receber_usuario FOREIGN KEY (criado_por) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    CONSTRAINT chk_receber_tipo CHECK (tipo IN ('SINAL','SALDO','INTEGRAL','CAUCAO','TAXA','OUTRO')),
    CONSTRAINT chk_receber_status CHECK (status IN ('PENDENTE','PARCIAL','RECEBIDO','VENCIDO','CANCELADO','ESTORNADO')),
    CONSTRAINT chk_receber_valores CHECK (valor_original > 0 AND valor_recebido >= 0 AND valor_estornado >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recebimentos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conta_receber_id BIGINT UNSIGNED NOT NULL,
    conta_id BIGINT UNSIGNED NOT NULL,
    valor DECIMAL(14,2) NOT NULL,
    valor_estornado DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    forma_pagamento VARCHAR(24) NOT NULL,
    recebido_em DATETIME NOT NULL,
    referencia_externa VARCHAR(190) NULL,
    idempotency_key VARCHAR(190) NULL,
    observacoes TEXT NULL,
    registrado_por BIGINT UNSIGNED NULL,
    estornado_em DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_recebimento_idempotencia (idempotency_key),
    KEY idx_recebimento_data (recebido_em),
    KEY idx_recebimento_receber (conta_receber_id),
    KEY idx_recebimento_conta (conta_id),
    CONSTRAINT fk_recebimento_receber FOREIGN KEY (conta_receber_id) REFERENCES contas_receber(id),
    CONSTRAINT fk_recebimento_conta FOREIGN KEY (conta_id) REFERENCES contas_financeiras(id),
    CONSTRAINT fk_recebimento_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    CONSTRAINT chk_recebimento_valor CHECK (valor > 0 AND valor_estornado >= 0 AND valor_estornado <= valor),
    CONSTRAINT chk_recebimento_forma CHECK (forma_pagamento IN ('PIX','DINHEIRO','TRANSFERENCIA','CARTAO','BOLETO','OUTRO'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contas_pagar (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fornecedor_id BIGINT UNSIGNED NULL,
    categoria_id BIGINT UNSIGNED NULL,
    conta_id BIGINT UNSIGNED NULL,
    recorrencia_id BIGINT UNSIGNED NULL,
    descricao VARCHAR(255) NOT NULL,
    documento VARCHAR(100) NULL,
    valor_original DECIMAL(14,2) NOT NULL,
    valor_pago DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    valor_estornado DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    moeda CHAR(3) NOT NULL DEFAULT 'BRL',
    vencimento DATE NOT NULL,
    competencia DATE NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDENTE',
    observacoes TEXT NULL,
    criado_por BIGINT UNSIGNED NULL,
    cancelado_em DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_pagar_status_vencimento (status,vencimento),
    KEY idx_pagar_fornecedor (fornecedor_id),
    KEY idx_pagar_categoria (categoria_id),
    KEY idx_pagar_recorrencia (recorrencia_id,competencia),
    CONSTRAINT fk_pagar_fornecedor FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id) ON DELETE SET NULL,
    CONSTRAINT fk_pagar_categoria FOREIGN KEY (categoria_id) REFERENCES categorias_financeiras(id) ON DELETE SET NULL,
    CONSTRAINT fk_pagar_conta FOREIGN KEY (conta_id) REFERENCES contas_financeiras(id) ON DELETE SET NULL,
    CONSTRAINT fk_pagar_usuario FOREIGN KEY (criado_por) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    CONSTRAINT chk_pagar_status CHECK (status IN ('PENDENTE','PARCIAL','PAGO','VENCIDO','CANCELADO','ESTORNADO')),
    CONSTRAINT chk_pagar_valores CHECK (valor_original > 0 AND valor_pago >= 0 AND valor_estornado >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pagamentos_despesas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conta_pagar_id BIGINT UNSIGNED NOT NULL,
    conta_id BIGINT UNSIGNED NOT NULL,
    valor DECIMAL(14,2) NOT NULL,
    valor_estornado DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    forma_pagamento VARCHAR(24) NOT NULL,
    pago_em DATETIME NOT NULL,
    referencia_externa VARCHAR(190) NULL,
    idempotency_key VARCHAR(190) NULL,
    observacoes TEXT NULL,
    registrado_por BIGINT UNSIGNED NULL,
    estornado_em DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pagamento_despesa_idempotencia (idempotency_key),
    KEY idx_pagamento_despesa_data (pago_em),
    KEY idx_pagamento_despesa_conta (conta_id),
    CONSTRAINT fk_pagamento_despesa_pagar FOREIGN KEY (conta_pagar_id) REFERENCES contas_pagar(id),
    CONSTRAINT fk_pagamento_despesa_conta FOREIGN KEY (conta_id) REFERENCES contas_financeiras(id),
    CONSTRAINT fk_pagamento_despesa_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    CONSTRAINT chk_pagamento_despesa_valor CHECK (valor > 0 AND valor_estornado >= 0 AND valor_estornado <= valor),
    CONSTRAINT chk_pagamento_despesa_forma CHECK (forma_pagamento IN ('PIX','DINHEIRO','TRANSFERENCIA','CARTAO','BOLETO','DEBITO_AUTOMATICO','OUTRO'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recorrencias_financeiras (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(12) NOT NULL DEFAULT 'DESPESA',
    fornecedor_id BIGINT UNSIGNED NULL,
    categoria_id BIGINT UNSIGNED NULL,
    conta_id BIGINT UNSIGNED NULL,
    descricao VARCHAR(255) NOT NULL,
    valor DECIMAL(14,2) NOT NULL,
    periodicidade VARCHAR(16) NOT NULL,
    intervalo_periodos SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    dia_vencimento TINYINT UNSIGNED NULL,
    inicio DATE NOT NULL,
    fim DATE NULL,
    proxima_competencia DATE NOT NULL,
    ativa TINYINT(1) NOT NULL DEFAULT 1,
    criado_por BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_recorrencia_proxima (ativa,proxima_competencia),
    CONSTRAINT fk_recorrencia_fornecedor FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id) ON DELETE SET NULL,
    CONSTRAINT fk_recorrencia_categoria FOREIGN KEY (categoria_id) REFERENCES categorias_financeiras(id) ON DELETE SET NULL,
    CONSTRAINT fk_recorrencia_conta FOREIGN KEY (conta_id) REFERENCES contas_financeiras(id) ON DELETE SET NULL,
    CONSTRAINT fk_recorrencia_usuario FOREIGN KEY (criado_por) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    CONSTRAINT chk_recorrencia_tipo CHECK (tipo IN ('RECEITA','DESPESA')),
    CONSTRAINT chk_recorrencia_periodicidade CHECK (periodicidade IN ('SEMANAL','MENSAL','BIMESTRAL','TRIMESTRAL','SEMESTRAL','ANUAL')),
    CONSTRAINT chk_recorrencia_valor CHECK (valor > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE contas_pagar ADD CONSTRAINT fk_pagar_recorrencia FOREIGN KEY (recorrencia_id) REFERENCES recorrencias_financeiras(id) ON DELETE SET NULL;
ALTER TABLE contas_pagar ADD UNIQUE KEY uk_pagar_recorrencia_competencia (recorrencia_id,competencia);

CREATE TABLE IF NOT EXISTS movimentos_financeiros (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conta_id BIGINT UNSIGNED NOT NULL,
    tipo VARCHAR(12) NOT NULL,
    origem_tipo VARCHAR(32) NOT NULL,
    origem_id BIGINT UNSIGNED NULL,
    descricao VARCHAR(255) NOT NULL,
    valor DECIMAL(14,2) NOT NULL,
    data_movimento DATETIME NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'REALIZADO',
    estorno_de_id BIGINT UNSIGNED NULL,
    registrado_por BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_movimento_conta_data (conta_id,data_movimento),
    KEY idx_movimento_origem (origem_tipo,origem_id),
    CONSTRAINT fk_movimento_conta FOREIGN KEY (conta_id) REFERENCES contas_financeiras(id),
    CONSTRAINT fk_movimento_estorno FOREIGN KEY (estorno_de_id) REFERENCES movimentos_financeiros(id) ON DELETE SET NULL,
    CONSTRAINT fk_movimento_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    CONSTRAINT chk_movimento_tipo CHECK (tipo IN ('ENTRADA','SAIDA')),
    CONSTRAINT chk_movimento_status CHECK (status IN ('PREVISTO','REALIZADO','CANCELADO','ESTORNADO')),
    CONSTRAINT chk_movimento_valor CHECK (valor > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conciliacoes_financeiras (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conta_id BIGINT UNSIGNED NOT NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    saldo_extrato DECIMAL(14,2) NOT NULL,
    saldo_sistema DECIMAL(14,2) NOT NULL,
    diferenca DECIMAL(14,2) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'ABERTA',
    observacoes TEXT NULL,
    conciliado_por BIGINT UNSIGNED NULL,
    conciliado_em DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_conciliacao_conta_periodo (conta_id,data_inicio,data_fim),
    CONSTRAINT fk_conciliacao_conta FOREIGN KEY (conta_id) REFERENCES contas_financeiras(id),
    CONSTRAINT fk_conciliacao_usuario FOREIGN KEY (conciliado_por) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    CONSTRAINT chk_conciliacao_status CHECK (status IN ('ABERTA','CONCILIADA','CANCELADA')),
    CONSTRAINT chk_conciliacao_datas CHECK (data_fim >= data_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conciliacao_itens (
    conciliacao_id BIGINT UNSIGNED NOT NULL,
    movimento_id BIGINT UNSIGNED NOT NULL,
    conciliado TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (conciliacao_id,movimento_id),
    CONSTRAINT fk_conciliacao_item_conciliacao FOREIGN KEY (conciliacao_id) REFERENCES conciliacoes_financeiras(id) ON DELETE CASCADE,
    CONSTRAINT fk_conciliacao_item_movimento FOREIGN KEY (movimento_id) REFERENCES movimentos_financeiros(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS caucoes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reserva_id BIGINT UNSIGNED NOT NULL,
    conta_receber_id BIGINT UNSIGNED NULL,
    recebimento_id BIGINT UNSIGNED NULL,
    valor DECIMAL(14,2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDENTE',
    recebida_em DATETIME NULL,
    devolvida_em DATETIME NULL,
    valor_retido DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    motivo_retencao TEXT NULL,
    registrado_por BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_caucao_reserva (reserva_id),
    CONSTRAINT fk_caucao_reserva FOREIGN KEY (reserva_id) REFERENCES reservas(id),
    CONSTRAINT fk_caucao_receber FOREIGN KEY (conta_receber_id) REFERENCES contas_receber(id) ON DELETE SET NULL,
    CONSTRAINT fk_caucao_recebimento FOREIGN KEY (recebimento_id) REFERENCES recebimentos(id) ON DELETE SET NULL,
    CONSTRAINT fk_caucao_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    CONSTRAINT chk_caucao_status CHECK (status IN ('PENDENTE','RECEBIDA','DEVOLVIDA','RETIDA_PARCIAL','RETIDA_TOTAL','CANCELADA')),
    CONSTRAINT chk_caucao_valores CHECK (valor > 0 AND valor_retido >= 0 AND valor_retido <= valor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

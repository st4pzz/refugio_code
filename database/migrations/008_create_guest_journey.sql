-- Portal do hospede, contratos, assinatura auditavel, pre-check-in e automacoes.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS guest_portal_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reservation_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    token_prefix CHAR(12) NOT NULL,
    token_encrypted MEDIUMTEXT NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'ACTIVE',
    expires_at DATETIME NULL,
    last_used_at DATETIME NULL,
    use_count INT UNSIGNED NOT NULL DEFAULT 0,
    revoked_at DATETIME NULL,
    revoked_reason VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_guest_portal_token (token_hash),
    KEY idx_guest_portal_reservation (reservation_id,status),
    CONSTRAINT fk_guest_portal_reservation FOREIGN KEY (reservation_id) REFERENCES reservas(id) ON DELETE CASCADE,
    CONSTRAINT fk_guest_portal_user FOREIGN KEY (created_by) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    CONSTRAINT chk_guest_portal_status CHECK (status IN ('ACTIVE','REVOKED','EXPIRED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contract_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(160) NOT NULL,
    description VARCHAR(255) NULL,
    active_version_id BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_contract_template_code (code),
    CONSTRAINT fk_contract_template_user FOREIGN KEY (created_by) REFERENCES usuarios_admin(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contract_template_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_id BIGINT UNSIGNED NOT NULL,
    version_no INT UNSIGNED NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
    source_kind VARCHAR(24) NOT NULL DEFAULT 'EDITABLE_HTML',
    title VARCHAR(190) NOT NULL,
    body_html MEDIUMTEXT NOT NULL,
    variables_json JSON NOT NULL,
    change_summary TEXT NULL,
    legal_review_notes TEXT NULL,
    source_document_hash CHAR(64) NULL,
    content_hash CHAR(64) NOT NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_contract_template_version (template_id,version_no),
    KEY idx_contract_template_version_status (template_id,status,version_no),
    CONSTRAINT fk_contract_version_template FOREIGN KEY (template_id) REFERENCES contract_templates(id) ON DELETE CASCADE,
    CONSTRAINT fk_contract_version_approved_by FOREIGN KEY (approved_by) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    CONSTRAINT fk_contract_version_created_by FOREIGN KEY (created_by) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    CONSTRAINT chk_contract_version_status CHECK (status IN ('ARCHIVED','DRAFT','PENDING_APPROVAL','APPROVED','REJECTED','SUPERSEDED')),
    CONSTRAINT chk_contract_source_kind CHECK (source_kind IN ('SOURCE_ARCHIVE','EDITABLE_HTML'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE contract_templates
    ADD CONSTRAINT fk_contract_template_active_version FOREIGN KEY (active_version_id) REFERENCES contract_template_versions(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS reservation_contracts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reservation_id BIGINT UNSIGNED NOT NULL,
    template_version_id BIGINT UNSIGNED NOT NULL,
    version_no INT UNSIGNED NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'DRAFT',
    contract_number VARCHAR(64) NOT NULL,
    variables_snapshot_json JSON NOT NULL,
    html_snapshot MEDIUMTEXT NOT NULL,
    content_hash CHAR(64) NOT NULL,
    pdf_path VARCHAR(500) NULL,
    pdf_hash CHAR(64) NULL,
    ready_at DATETIME NULL,
    sent_at DATETIME NULL,
    expires_at DATETIME NULL,
    fully_signed_at DATETIME NULL,
    superseded_by BIGINT UNSIGNED NULL,
    generated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_reservation_contract_number (contract_number),
    UNIQUE KEY uk_reservation_contract_version (reservation_id,version_no),
    KEY idx_reservation_contract_status (reservation_id,status),
    CONSTRAINT fk_reservation_contract_reservation FOREIGN KEY (reservation_id) REFERENCES reservas(id) ON DELETE CASCADE,
    CONSTRAINT fk_reservation_contract_template_version FOREIGN KEY (template_version_id) REFERENCES contract_template_versions(id),
    CONSTRAINT fk_reservation_contract_superseded FOREIGN KEY (superseded_by) REFERENCES reservation_contracts(id) ON DELETE SET NULL,
    CONSTRAINT fk_reservation_contract_user FOREIGN KEY (generated_by) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    CONSTRAINT chk_reservation_contract_status CHECK (status IN ('DRAFT','READY','SENT','VIEWED','SIGNED_BY_GUEST','SIGNED_BY_OWNER','FULLY_SIGNED','DECLINED','EXPIRED','CANCELLED','SUPERSEDED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contract_signers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id BIGINT UNSIGNED NOT NULL,
    signer_role VARCHAR(16) NOT NULL,
    name VARCHAR(160) NOT NULL,
    cpf VARCHAR(14) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(30) NULL,
    auth_code_hash CHAR(64) NULL,
    auth_code_expires_at DATETIME NULL,
    auth_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    auth_max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    auth_used_at DATETIME NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    viewed_at DATETIME NULL,
    accepted_at DATETIME NULL,
    accepted_name VARCHAR(160) NULL,
    accepted_cpf VARCHAR(14) NULL,
    accepted_ip VARCHAR(45) NULL,
    accepted_user_agent VARCHAR(500) NULL,
    acceptance_text_hash CHAR(64) NULL,
    document_hash_at_acceptance CHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_contract_signer_role (contract_id,signer_role),
    KEY idx_contract_signer_auth (contract_id,status,auth_code_expires_at),
    CONSTRAINT fk_contract_signer_contract FOREIGN KEY (contract_id) REFERENCES reservation_contracts(id) ON DELETE CASCADE,
    CONSTRAINT chk_contract_signer_role CHECK (signer_role IN ('GUEST','OWNER')),
    CONSTRAINT chk_contract_signer_status CHECK (status IN ('PENDING','CODE_SENT','VIEWED','SIGNED','DECLINED','EXPIRED','LOCKED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contract_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id BIGINT UNSIGNED NOT NULL,
    signer_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(48) NOT NULL,
    metadata_json JSON NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    document_hash CHAR(64) NULL,
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_contract_event_contract (contract_id,occurred_at,id),
    CONSTRAINT fk_contract_event_contract FOREIGN KEY (contract_id) REFERENCES reservation_contracts(id) ON DELETE CASCADE,
    CONSTRAINT fk_contract_event_signer FOREIGN KEY (signer_id) REFERENCES contract_signers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contract_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id BIGINT UNSIGNED NOT NULL,
    document_type VARCHAR(24) NOT NULL,
    storage_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    byte_size BIGINT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_contract_document_hash (contract_id,document_type,sha256),
    CONSTRAINT fk_contract_document_contract FOREIGN KEY (contract_id) REFERENCES reservation_contracts(id) ON DELETE CASCADE,
    CONSTRAINT chk_contract_document_type CHECK (document_type IN ('UNSIGNED_PDF','SIGNED_PDF','AUDIT_TRAIL','SOURCE_SNAPSHOT'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS precheckins (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reservation_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'NOT_STARTED',
    responsible_name VARCHAR(160) NULL,
    responsible_cpf VARCHAR(14) NULL,
    responsible_birth_date DATE NULL,
    responsible_document VARCHAR(80) NULL,
    estimated_arrival_time TIME NULL,
    notes TEXT NULL,
    submitted_at DATETIME NULL,
    reviewed_at DATETIME NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    correction_message TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_precheckin_reservation (reservation_id),
    KEY idx_precheckin_status (status,submitted_at),
    CONSTRAINT fk_precheckin_reservation FOREIGN KEY (reservation_id) REFERENCES reservas(id) ON DELETE CASCADE,
    CONSTRAINT fk_precheckin_reviewer FOREIGN KEY (reviewed_by) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    CONSTRAINT chk_precheckin_status CHECK (status IN ('NOT_STARTED','IN_PROGRESS','SUBMITTED','UNDER_REVIEW','APPROVED','CORRECTION_REQUESTED','REJECTED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservation_guests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    precheckin_id BIGINT UNSIGNED NOT NULL,
    full_name VARCHAR(160) NOT NULL,
    cpf VARCHAR(14) NULL,
    document_number VARCHAR(80) NULL,
    birth_date DATE NULL,
    is_responsible TINYINT(1) NOT NULL DEFAULT 0,
    sort_order SMALLINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_precheckin_guest_order (precheckin_id,sort_order),
    KEY idx_precheckin_guest_name (precheckin_id,full_name),
    CONSTRAINT fk_reservation_guest_precheckin FOREIGN KEY (precheckin_id) REFERENCES precheckins(id) ON DELETE CASCADE,
    CONSTRAINT chk_reservation_guest_order CHECK (sort_order BETWEEN 1 AND 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservation_vehicles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    precheckin_id BIGINT UNSIGNED NOT NULL,
    plate VARCHAR(16) NOT NULL,
    make_model VARCHAR(120) NULL,
    color VARCHAR(40) NULL,
    driver_name VARCHAR(160) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_precheckin_vehicle_plate (precheckin_id,plate),
    CONSTRAINT fk_reservation_vehicle_precheckin FOREIGN KEY (precheckin_id) REFERENCES precheckins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservation_pets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    precheckin_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(80) NOT NULL,
    species VARCHAR(60) NOT NULL,
    breed VARCHAR(80) NULL,
    size VARCHAR(20) NULL,
    notes VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reservation_pet_precheckin FOREIGN KEY (precheckin_id) REFERENCES precheckins(id) ON DELETE CASCADE,
    CONSTRAINT chk_reservation_pet_size CHECK (size IS NULL OR size IN ('SMALL','MEDIUM','LARGE'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS house_rule_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    version_no INT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    title VARCHAR(160) NOT NULL,
    rules_json JSON NOT NULL,
    content_hash CHAR(64) NOT NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_house_rule_version (version_no),
    CONSTRAINT fk_house_rule_approver FOREIGN KEY (approved_by) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    CONSTRAINT chk_house_rule_status CHECK (status IN ('DRAFT','APPROVED','SUPERSEDED','ARCHIVED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS house_rule_acceptances (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    precheckin_id BIGINT UNSIGNED NOT NULL,
    rule_version_id BIGINT UNSIGNED NOT NULL,
    accepted_items_json JSON NOT NULL,
    acceptance_text_hash CHAR(64) NOT NULL,
    accepted_name VARCHAR(160) NOT NULL,
    accepted_cpf VARCHAR(14) NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    accepted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_house_rule_acceptance (precheckin_id,rule_version_id),
    CONSTRAINT fk_house_rule_acceptance_precheckin FOREIGN KEY (precheckin_id) REFERENCES precheckins(id) ON DELETE CASCADE,
    CONSTRAINT fk_house_rule_acceptance_version FOREIGN KEY (rule_version_id) REFERENCES house_rule_versions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS automation_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(80) NOT NULL,
    name VARCHAR(160) NOT NULL,
    trigger_event VARCHAR(64) NOT NULL,
    schedule_anchor VARCHAR(24) NOT NULL DEFAULT 'EVENT',
    offset_minutes INT NOT NULL DEFAULT 0,
    channels_json JSON NOT NULL,
    subject_template VARCHAR(255) NULL,
    body_template MEDIUMTEXT NOT NULL,
    conditions_json JSON NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_automation_rule_code (code),
    KEY idx_automation_rule_trigger (ativo,trigger_event),
    CONSTRAINT fk_automation_rule_user FOREIGN KEY (created_by) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    CONSTRAINT chk_automation_anchor CHECK (schedule_anchor IN ('EVENT','CHECKIN','CHECKOUT','PAYMENT_DUE','QUOTE_EXPIRY'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS automation_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rule_id BIGINT UNSIGNED NOT NULL,
    reservation_id BIGINT UNSIGNED NOT NULL,
    event_name VARCHAR(64) NOT NULL,
    event_key VARCHAR(190) NOT NULL,
    scheduled_at DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'SCHEDULED',
    rendered_payload_json JSON NULL,
    job_id BIGINT UNSIGNED NULL,
    error_message TEXT NULL,
    executed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_automation_run_event (rule_id,reservation_id,event_key),
    KEY idx_automation_run_schedule (status,scheduled_at),
    CONSTRAINT fk_automation_run_rule FOREIGN KEY (rule_id) REFERENCES automation_rules(id) ON DELETE CASCADE,
    CONSTRAINT fk_automation_run_reservation FOREIGN KEY (reservation_id) REFERENCES reservas(id) ON DELETE CASCADE,
    CONSTRAINT fk_automation_run_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE SET NULL,
    CONSTRAINT chk_automation_run_status CHECK (status IN ('SCHEDULED','QUEUED','PROCESSING','SENT','SKIPPED','FAILED','CANCELLED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO automation_rules (code,name,trigger_event,schedule_anchor,offset_minutes,channels_json,subject_template,body_template) VALUES
('REQUEST_RECEIVED','Solicitação recebida','RESERVATION_REQUEST_CREATED','EVENT',0,JSON_ARRAY('EMAIL','WHATSAPP'),'Recebemos sua solicitação {{reservation_code}}','Olá {{first_name}}, recebemos sua solicitação para {{checkin}} a {{checkout}}. Acompanhe em {{portal_link}}.'),
('APPROVAL_QUOTE','Aprovação e orçamento','RESERVATION_APPROVED','EVENT',0,JSON_ARRAY('EMAIL','WHATSAPP'),'Orçamento da reserva {{reservation_code}}','Sua solicitação foi aprovada. Valor: {{total}}. Consulte {{portal_link}}.'),
('PIX_CHARGE','Cobrança Pix','PAYMENT_REQUEST_CREATED','EVENT',0,JSON_ARRAY('EMAIL','WHATSAPP'),'Pagamento da reserva {{reservation_code}}','Seu pagamento vence em {{payment_due}}. Acesse {{payment_link}}.'),
('PAYMENT_REMINDER','Lembrete de vencimento','PAYMENT_REQUEST_CREATED','PAYMENT_DUE',-360,JSON_ARRAY('EMAIL','WHATSAPP'),'Lembrete de pagamento {{reservation_code}}','Lembrete: seu pagamento vence em {{payment_due}}. {{payment_link}}'),
('PAYMENT_CONFIRMED','Pagamento confirmado','PAYMENT_CONFIRMED','EVENT',0,JSON_ARRAY('EMAIL','WHATSAPP'),'Pagamento confirmado {{reservation_code}}','Pagamento confirmado. Próximos passos em {{portal_link}}.'),
('CONTRACT_AVAILABLE','Contrato disponível','CONTRACT_READY','EVENT',0,JSON_ARRAY('EMAIL','WHATSAPP'),'Contrato disponível {{reservation_code}}','Seu contrato está disponível em {{contract_link}}.'),
('CONTRACT_REMINDER','Lembrete de assinatura','CONTRACT_SENT','EVENT',1440,JSON_ARRAY('EMAIL','WHATSAPP'),'Lembrete de assinatura {{reservation_code}}','Seu contrato aguarda assinatura: {{contract_link}}.'),
('PRECHECKIN_AVAILABLE','Pré-check-in disponível','PRECHECKIN_AVAILABLE','EVENT',0,JSON_ARRAY('EMAIL','WHATSAPP'),'Pré-check-in {{reservation_code}}','Preencha o pré-check-in em {{precheckin_link}}.'),
('PRECHECKIN_REMINDER','Lembrete de pré-check-in','PRECHECKIN_AVAILABLE','CHECKIN',-2880,JSON_ARRAY('EMAIL','WHATSAPP'),'Lembrete de pré-check-in {{reservation_code}}','Precisamos do pré-check-in antes da chegada: {{precheckin_link}}.'),
('PRECHECKIN_CONFIRMED','Pré-check-in confirmado','PRECHECKIN_APPROVED','EVENT',0,JSON_ARRAY('EMAIL','WHATSAPP'),'Pré-check-in aprovado {{reservation_code}}','Pré-check-in aprovado. Consulte a jornada em {{portal_link}}.'),
('CHECKIN_3_DAYS','Check-in em 3 dias','CHECKIN_UPCOMING','CHECKIN',-4320,JSON_ARRAY('EMAIL','WHATSAPP'),'Sua estadia está próxima','Faltam 3 dias para o check-in. {{portal_link}}'),
('CHECKIN_1_DAY','Check-in amanhã','CHECKIN_UPCOMING','CHECKIN',-1440,JSON_ARRAY('EMAIL','WHATSAPP'),'Check-in amanhã','Confira horários e orientações em {{portal_link}}.'),
('CHECKIN_TODAY','Check-in hoje','CHECKIN_DAY','CHECKIN',0,JSON_ARRAY('EMAIL','WHATSAPP'),'Seu check-in é hoje','Bem-vindo! Instruções liberadas em {{portal_link}}.'),
('CHECKOUT_EVE','Checkout amanhã','CHECKOUT_UPCOMING','CHECKOUT',-720,JSON_ARRAY('EMAIL','WHATSAPP'),'Checkout amanhã','Confira o horário de checkout: {{checkout_time}}.'),
('CHECKOUT_TODAY','Checkout hoje','CHECKOUT_DAY','CHECKOUT',0,JSON_ARRAY('EMAIL','WHATSAPP'),'Checkout hoje','Esperamos que tenha aproveitado. Checkout às {{checkout_time}}.'),
('THANK_YOU','Agradecimento','RESERVATION_COMPLETED','CHECKOUT',180,JSON_ARRAY('EMAIL','WHATSAPP'),'Obrigado pela estadia','Obrigado por escolher o Refúgio do Cuscuzeiro.'),
('REVIEW_INVITATION','Convite de avaliação','REVIEW_INVITATION_AVAILABLE','CHECKOUT',1440,JSON_ARRAY('EMAIL','WHATSAPP'),'Conte como foi sua estadia','Sua opinião é importante. Avalie sua estadia em {{review_link}}.')
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO configuracoes_sistema (namespace,chave,valor_json) VALUES
('communication','WHATSAPP_TEMPLATE_ORCAMENTO',JSON_OBJECT('value',NULL,'configured',FALSE)),
('communication','WHATSAPP_TEMPLATE_COBRANCA',JSON_OBJECT('value',NULL,'configured',FALSE)),
('communication','WHATSAPP_TEMPLATE_PAGAMENTO_CONFIRMADO',JSON_OBJECT('value',NULL,'configured',FALSE)),
('communication','WHATSAPP_TEMPLATE_CONTRATO',JSON_OBJECT('value',NULL,'configured',FALSE)),
('communication','WHATSAPP_TEMPLATE_LEMBRETE_CONTRATO',JSON_OBJECT('value',NULL,'configured',FALSE)),
('communication','WHATSAPP_TEMPLATE_PRECHECKIN',JSON_OBJECT('value',NULL,'configured',FALSE)),
('communication','WHATSAPP_TEMPLATE_LEMBRETE_PRECHECKIN',JSON_OBJECT('value',NULL,'configured',FALSE)),
('communication','WHATSAPP_TEMPLATE_CHECKIN_PROXIMO',JSON_OBJECT('value',NULL,'configured',FALSE)),
('communication','WHATSAPP_TEMPLATE_CHECKIN_DIA',JSON_OBJECT('value',NULL,'configured',FALSE)),
('communication','WHATSAPP_TEMPLATE_CHECKOUT',JSON_OBJECT('value',NULL,'configured',FALSE)),
('communication','WHATSAPP_TEMPLATE_AGRADECIMENTO',JSON_OBJECT('value',NULL,'configured',FALSE)),
('communication','WHATSAPP_TEMPLATE_AVALIACAO',JSON_OBJECT('value',NULL,'configured',FALSE))
ON DUPLICATE KEY UPDATE chave=VALUES(chave);

UPDATE perfis_admin SET permissoes_json=JSON_MERGE_PRESERVE(permissoes_json,JSON_ARRAY(
    'contracts.templates.manage','contracts.templates.approve','contracts.signatures.manage','precheckin.rules.manage'
)) WHERE codigo='ADMIN';

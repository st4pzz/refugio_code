-- Calendario unificado, iCal, configuracoes comerciais e cotacoes imutaveis.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS calendar_sources (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    provider VARCHAR(32) NOT NULL DEFAULT 'OTHER',
    feed_url VARCHAR(2048) NOT NULL,
    feed_url_hash CHAR(64) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    timezone VARCHAR(80) NOT NULL DEFAULT 'America/Sao_Paulo',
    sync_interval_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    ultimo_sync_em DATETIME NULL,
    proximo_sync_em DATETIME NULL,
    ultimo_status VARCHAR(20) NULL,
    ultimo_erro TEXT NULL,
    etag VARCHAR(255) NULL,
    last_modified VARCHAR(255) NULL,
    criado_por BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_calendar_source_url (feed_url_hash),
    KEY idx_calendar_source_due (ativo,proximo_sync_em),
    CONSTRAINT fk_calendar_source_user FOREIGN KEY (criado_por) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    CONSTRAINT chk_calendar_source_provider CHECK (provider IN ('AIRBNB','BOOKING','GOOGLE','OTHER')),
    CONSTRAINT chk_calendar_source_interval CHECK (sync_interval_minutes BETWEEN 5 AND 1440)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calendar_external_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_id BIGINT UNSIGNED NOT NULL,
    external_uid VARCHAR(512) NOT NULL,
    summary VARCHAR(255) NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    all_day TINYINT(1) NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'CONFIRMED',
    sequence_no INT NOT NULL DEFAULT 0,
    raw_checksum CHAR(64) NOT NULL,
    raw_json JSON NULL,
    first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_calendar_event_uid (source_id,external_uid),
    KEY idx_calendar_event_dates (starts_at,ends_at,status,deleted_at),
    CONSTRAINT fk_calendar_event_source FOREIGN KEY (source_id) REFERENCES calendar_sources(id) ON DELETE CASCADE,
    CONSTRAINT chk_calendar_event_dates CHECK (ends_at > starts_at),
    CONSTRAINT chk_calendar_event_status CHECK (status IN ('CONFIRMED','TENTATIVE','CANCELLED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calendar_holds (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token_hash CHAR(64) NOT NULL,
    checkin DATE NOT NULL,
    checkout DATE NOT NULL,
    purpose VARCHAR(32) NOT NULL DEFAULT 'QUOTE',
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    quote_id BIGINT UNSIGNED NULL,
    reservation_id BIGINT UNSIGNED NULL,
    expires_at DATETIME NOT NULL,
    released_at DATETIME NULL,
    release_reason VARCHAR(120) NULL,
    criado_por BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_calendar_hold_token (token_hash),
    KEY idx_calendar_hold_dates (status,checkin,checkout,expires_at),
    KEY idx_calendar_hold_quote (quote_id),
    KEY idx_calendar_hold_reservation (reservation_id),
    CONSTRAINT fk_calendar_hold_reservation FOREIGN KEY (reservation_id) REFERENCES reservas(id) ON DELETE SET NULL,
    CONSTRAINT fk_calendar_hold_user FOREIGN KEY (criado_por) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    CONSTRAINT chk_calendar_hold_dates CHECK (checkout > checkin),
    CONSTRAINT chk_calendar_hold_status CHECK (status IN ('ACTIVE','CONVERTED','RELEASED','EXPIRED')),
    CONSTRAINT chk_calendar_hold_purpose CHECK (purpose IN ('QUOTE','PAYMENT','MANUAL'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calendar_sync_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL,
    http_status SMALLINT UNSIGNED NULL,
    events_seen INT UNSIGNED NOT NULL DEFAULT 0,
    events_created INT UNSIGNED NOT NULL DEFAULT 0,
    events_updated INT UNSIGNED NOT NULL DEFAULT 0,
    events_cancelled INT UNSIGNED NOT NULL DEFAULT 0,
    duration_ms INT UNSIGNED NULL,
    error_message TEXT NULL,
    started_at DATETIME NOT NULL,
    finished_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_calendar_sync_source (source_id,created_at),
    KEY idx_calendar_sync_status (status,created_at),
    CONSTRAINT fk_calendar_sync_source FOREIGN KEY (source_id) REFERENCES calendar_sources(id) ON DELETE CASCADE,
    CONSTRAINT chk_calendar_sync_status CHECK (status IN ('RUNNING','SUCCESS','NOT_MODIFIED','FAILED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calendar_export_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    expires_at DATETIME NULL,
    last_used_at DATETIME NULL,
    criado_por BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at DATETIME NULL,
    UNIQUE KEY uk_calendar_export_token (token_hash),
    CONSTRAINT fk_calendar_export_user FOREIGN KEY (criado_por) REFERENCES usuarios_admin(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS property_pricing_settings (
    id TINYINT UNSIGNED PRIMARY KEY,
    currency CHAR(3) NOT NULL DEFAULT 'BRL',
    base_daily_rate DECIMAL(12,2) NOT NULL,
    cleaning_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
    guests_included_in_base_rate SMALLINT UNSIGNED NULL,
    extra_guest_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
    extra_guest_fee_mode VARCHAR(16) NULL,
    minimum_nights SMALLINT UNSIGNED NULL,
    maximum_nights SMALLINT UNSIGNED NULL,
    public_pricing_enabled TINYINT(1) NOT NULL DEFAULT 0,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pricing_settings_user FOREIGN KEY (updated_by) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    CONSTRAINT chk_pricing_base CHECK (base_daily_rate >= 0 AND cleaning_fee >= 0 AND extra_guest_fee >= 0),
    CONSTRAINT chk_pricing_extra_mode CHECK (extra_guest_fee_mode IS NULL OR extra_guest_fee_mode IN ('PER_NIGHT','PER_STAY')),
    CONSTRAINT chk_pricing_nights CHECK (minimum_nights IS NULL OR maximum_nights IS NULL OR maximum_nights >= minimum_nights)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO property_pricing_settings (id,currency,base_daily_rate,cleaning_fee,extra_guest_fee,public_pricing_enabled)
VALUES (1,'BRL',800.00,280.00,100.00,0)
ON DUPLICATE KEY UPDATE id=id;

CREATE TABLE IF NOT EXISTS pricing_seasons (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    starts_on DATE NOT NULL,
    ends_on DATE NOT NULL,
    adjustment_type VARCHAR(16) NOT NULL,
    adjustment_value DECIMAL(12,4) NOT NULL,
    priority SMALLINT NOT NULL DEFAULT 100,
    stackable TINYINT(1) NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_pricing_season_dates (ativo,starts_on,ends_on,priority),
    CONSTRAINT fk_pricing_season_user FOREIGN KEY (created_by) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    CONSTRAINT chk_pricing_season_dates CHECK (ends_on >= starts_on),
    CONSTRAINT chk_pricing_season_type CHECK (adjustment_type IN ('FIXED_RATE','PERCENT','FIXED_AMOUNT'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pricing_special_dates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    starts_on DATE NOT NULL,
    ends_on DATE NOT NULL,
    daily_rate DECIMAL(12,2) NOT NULL,
    minimum_nights SMALLINT UNSIGNED NULL,
    priority SMALLINT NOT NULL DEFAULT 10,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_pricing_special_dates (ativo,starts_on,ends_on,priority),
    CONSTRAINT fk_pricing_special_user FOREIGN KEY (created_by) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    CONSTRAINT chk_pricing_special_range CHECK (ends_on >= starts_on AND daily_rate >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pricing_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    rule_type VARCHAR(32) NOT NULL,
    conditions_json JSON NOT NULL,
    adjustment_type VARCHAR(16) NOT NULL,
    adjustment_value DECIMAL(12,4) NOT NULL,
    priority SMALLINT NOT NULL DEFAULT 100,
    stackable TINYINT(1) NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_pricing_rule_active (ativo,priority,starts_at,ends_at),
    CONSTRAINT fk_pricing_rule_user FOREIGN KEY (created_by) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    CONSTRAINT chk_pricing_rule_type CHECK (rule_type IN ('WEEKEND','LENGTH_OF_STAY','ADVANCE','LAST_MINUTE','OCCUPANCY','CUSTOM')),
    CONSTRAINT chk_pricing_rule_adjustment CHECK (adjustment_type IN ('PERCENT','FIXED_AMOUNT'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pricing_coupons (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(64) NOT NULL,
    description VARCHAR(255) NULL,
    discount_type VARCHAR(16) NOT NULL,
    discount_value DECIMAL(12,4) NOT NULL,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    max_uses INT UNSIGNED NULL,
    uses_count INT UNSIGNED NOT NULL DEFAULT 0,
    minimum_total DECIMAL(12,2) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pricing_coupon_code (code),
    KEY idx_pricing_coupon_active (ativo,starts_at,ends_at),
    CONSTRAINT fk_pricing_coupon_user FOREIGN KEY (created_by) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    CONSTRAINT chk_pricing_coupon_type CHECK (discount_type IN ('PERCENT','FIXED_AMOUNT'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quotes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL,
    public_token_hash CHAR(64) NOT NULL,
    reservation_id BIGINT UNSIGNED NULL,
    customer_name VARCHAR(160) NULL,
    customer_email VARCHAR(190) NULL,
    customer_phone VARCHAR(30) NULL,
    checkin DATE NOT NULL,
    checkout DATE NOT NULL,
    guests SMALLINT UNSIGNED NOT NULL,
    pets SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'BRL',
    subtotal DECIMAL(12,2) NOT NULL,
    discount_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    total DECIMAL(12,2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    pricing_snapshot_json JSON NOT NULL,
    expires_at DATETIME NOT NULL,
    sent_at DATETIME NULL,
    viewed_at DATETIME NULL,
    accepted_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_quote_code (code),
    UNIQUE KEY uk_quote_token (public_token_hash),
    KEY idx_quote_status_expiry (status,expires_at),
    KEY idx_quote_reservation (reservation_id),
    CONSTRAINT fk_quote_reservation FOREIGN KEY (reservation_id) REFERENCES reservas(id) ON DELETE SET NULL,
    CONSTRAINT fk_quote_user FOREIGN KEY (created_by) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    CONSTRAINT chk_quote_dates CHECK (checkout > checkin),
    CONSTRAINT chk_quote_guests CHECK (guests BETWEEN 1 AND 10),
    CONSTRAINT chk_quote_values CHECK (subtotal >= 0 AND discount_total >= 0 AND total >= 0),
    CONSTRAINT chk_quote_status CHECK (status IN ('DRAFT','READY','SENT','VIEWED','ACCEPTED','EXPIRED','CANCELLED','CONVERTED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quote_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quote_id BIGINT UNSIGNED NOT NULL,
    item_type VARCHAR(32) NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit_amount DECIMAL(12,2) NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    metadata_json JSON NULL,
    sort_order SMALLINT NOT NULL DEFAULT 100,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_quote_item_quote (quote_id,sort_order,id),
    CONSTRAINT fk_quote_item_quote FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
    CONSTRAINT chk_quote_item_type CHECK (item_type IN ('DAILY_RATE','CLEANING','EXTRA_GUEST','PET','SURCHARGE','DISCOUNT','OTHER'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quote_applied_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quote_id BIGINT UNSIGNED NOT NULL,
    source_type VARCHAR(24) NOT NULL,
    source_id BIGINT UNSIGNED NULL,
    source_name VARCHAR(120) NOT NULL,
    rule_snapshot_json JSON NOT NULL,
    amount_effect DECIMAL(12,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_quote_rule_quote (quote_id,id),
    CONSTRAINT fk_quote_rule_quote FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
    CONSTRAINT chk_quote_rule_source CHECK (source_type IN ('SEASON','SPECIAL_DATE','RULE','COUPON','SETTING'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE calendar_holds
    ADD CONSTRAINT fk_calendar_hold_quote FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE SET NULL;

INSERT INTO configuracoes_sistema (namespace,chave,valor_json) VALUES
('property','PROPERTY_NAME',JSON_OBJECT('value','Refúgio do Cuscuzeiro','type','string','required',TRUE,'configured',TRUE)),
('property','PROPERTY_CITY',JSON_OBJECT('value','Analândia','type','string','required',TRUE,'configured',TRUE)),
('property','PROPERTY_STATE',JSON_OBJECT('value','SP','type','string','required',TRUE,'configured',TRUE)),
('property','PROPERTY_TIMEZONE',JSON_OBJECT('value','America/Sao_Paulo','type','timezone','required',TRUE,'configured',TRUE)),
('property','PROPERTY_CURRENCY',JSON_OBJECT('value','BRL','type','currency','required',TRUE,'configured',TRUE)),
('property','MAX_GUESTS',JSON_OBJECT('value',10,'type','integer','required',TRUE,'configured',TRUE)),
('property','DEFAULT_CHECKIN_TIME',JSON_OBJECT('value',NULL,'type','time','required',TRUE,'configured',FALSE)),
('property','DEFAULT_CHECKOUT_TIME',JSON_OBJECT('value',NULL,'type','time','required',TRUE,'configured',FALSE)),
('property','DEFAULT_PAYMENT_HOLD_HOURS',JSON_OBJECT('value',NULL,'type','integer','required',TRUE,'configured',FALSE)),
('property','DEFAULT_QUOTE_EXPIRATION_HOURS',JSON_OBJECT('value',NULL,'type','integer','required',TRUE,'configured',FALSE)),
('property','SECURITY_DEPOSIT_ENABLED',JSON_OBJECT('value',NULL,'type','boolean','required',TRUE,'configured',FALSE)),
('property','SECURITY_DEPOSIT_AMOUNT',JSON_OBJECT('value',NULL,'type','money','required',FALSE,'configured',FALSE)),
('property','PETS_ALLOWED',JSON_OBJECT('value',NULL,'type','boolean','required',TRUE,'configured',FALSE)),
('property','MAX_PETS',JSON_OBJECT('value',NULL,'type','integer','required',FALSE,'configured',FALSE)),
('property','PET_FEE',JSON_OBJECT('value',NULL,'type','money','required',FALSE,'configured',FALSE)),
('property','QUIET_HOURS',JSON_OBJECT('value',NULL,'type','string','required',TRUE,'configured',FALSE)),
('property','MINIMUM_NIGHTS',JSON_OBJECT('value',NULL,'type','integer','required',TRUE,'configured',FALSE)),
('property','MAXIMUM_NIGHTS',JSON_OBJECT('value',NULL,'type','integer','required',TRUE,'configured',FALSE)),
('property','CONTRACT_SIGNATURE_REQUIRED',JSON_OBJECT('value',TRUE,'type','boolean','required',TRUE,'configured',TRUE)),
('property','PRECHECKIN_REQUIRED',JSON_OBJECT('value',TRUE,'type','boolean','required',TRUE,'configured',TRUE)),
('property','PAYMENT_REQUIRED_BEFORE_CONTRACT',JSON_OBJECT('value',NULL,'type','boolean','required',TRUE,'configured',FALSE)),
('property','CONTRACT_REQUIRED_BEFORE_CHECKIN',JSON_OBJECT('value',TRUE,'type','boolean','required',TRUE,'configured',TRUE)),
('property','PRECHECKIN_DEADLINE_HOURS',JSON_OBJECT('value',NULL,'type','integer','required',TRUE,'configured',FALSE)),
('property','GUEST_PORTAL_RELEASE_HOURS',JSON_OBJECT('value',NULL,'type','integer','required',TRUE,'configured',FALSE)),
('property','CHECKIN_INSTRUCTIONS_RELEASE_HOURS',JSON_OBJECT('value',NULL,'type','integer','required',TRUE,'configured',FALSE))
,
('property','OWNER_FULL_NAME',JSON_OBJECT('value',NULL,'type','string','required',TRUE,'configured',FALSE)),
('property','OWNER_NATIONALITY',JSON_OBJECT('value',NULL,'type','string','required',TRUE,'configured',FALSE)),
('property','OWNER_MARITAL_STATUS',JSON_OBJECT('value',NULL,'type','string','required',TRUE,'configured',FALSE)),
('property','OWNER_PROFESSION',JSON_OBJECT('value',NULL,'type','string','required',TRUE,'configured',FALSE)),
('property','OWNER_RG',JSON_OBJECT('value',NULL,'type','string','required',TRUE,'configured',FALSE)),
('property','OWNER_CPF',JSON_OBJECT('value',NULL,'type','string','required',TRUE,'configured',FALSE)),
('property','OWNER_ADDRESS',JSON_OBJECT('value',NULL,'type','string','required',TRUE,'configured',FALSE)),
('property','OWNER_PHONE',JSON_OBJECT('value',NULL,'type','string','required',TRUE,'configured',FALSE)),
('property','OWNER_EMAIL',JSON_OBJECT('value',NULL,'type','string','required',TRUE,'configured',FALSE)),
('property','PROPERTY_FULL_ADDRESS',JSON_OBJECT('value',NULL,'type','string','required',TRUE,'configured',FALSE)),
('property','CONTRACT_CITY',JSON_OBJECT('value','Analândia/SP','type','string','required',TRUE,'configured',TRUE)),
('property','CONTRACT_FORUM_CITY',JSON_OBJECT('value',NULL,'type','string','required',TRUE,'configured',FALSE)),
('property','EMERGENCY_CONTACT',JSON_OBJECT('value',NULL,'type','string','required',TRUE,'configured',FALSE)),
('property','UNAUTHORIZED_VISITOR_FEE',JSON_OBJECT('value',NULL,'type','money','required',TRUE,'configured',FALSE)),
('property','CANCELLATION_POLICY',JSON_OBJECT('value','30 dias ou mais: devolução de 90%; 15 a 29 dias: devolução de 50%; menos de 15 dias: sem devolução, ressalvadas hipóteses legais ou acordo; havendo nova locação, restituição do líquido recuperado, descontados 10% e valores já devolvidos.','type','string','required',TRUE,'configured',TRUE)),
('property','CANCELLATION_POLICY_APPROVED',JSON_OBJECT('value',FALSE,'type','boolean','required',TRUE,'configured',FALSE)),
('property','PAYMENT_METHOD',JSON_OBJECT('value',NULL,'type','string','required',TRUE,'configured',FALSE)),
('property','ARRIVAL_DIRECTIONS',JSON_OBJECT('value',NULL,'type','string','required',FALSE,'configured',FALSE)),
('property','ACCESS_INSTRUCTIONS',JSON_OBJECT('value',NULL,'type','string','required',FALSE,'configured',FALSE)),
('property','WIFI_NAME',JSON_OBJECT('value',NULL,'type','string','required',FALSE,'configured',FALSE)),
('property','WIFI_PASSWORD',JSON_OBJECT('value',NULL,'type','string','required',FALSE,'configured',FALSE))
ON DUPLICATE KEY UPDATE chave=VALUES(chave);

UPDATE perfis_admin SET permissoes_json=JSON_MERGE_PRESERVE(permissoes_json,JSON_ARRAY(
    'calendar.view','calendar.manage','calendar.sync','pricing.view','pricing.manage','quotes.view','quotes.manage','quotes.send',
    'guest_portal.view','guest_portal.manage','contracts.view','contracts.generate','contracts.send','contracts.signatures.view',
    'precheckin.view','precheckin.review','automation.view','automation.manage','property_settings.manage'
)) WHERE codigo='ADMIN';

UPDATE perfis_admin SET permissoes_json=JSON_MERGE_PRESERVE(permissoes_json,JSON_ARRAY(
    'calendar.view','calendar.manage','quotes.view','quotes.manage','quotes.send','guest_portal.view','contracts.view',
    'contracts.generate','contracts.send','precheckin.view','precheckin.review','automation.view'
)) WHERE codigo='ATENDIMENTO';

UPDATE perfis_admin SET permissoes_json=JSON_MERGE_PRESERVE(permissoes_json,JSON_ARRAY(
    'calendar.view','pricing.view','quotes.view','guest_portal.view','contracts.view','precheckin.view','automation.view'
)) WHERE codigo IN ('MARKETING','FINANCEIRO','LEITURA');

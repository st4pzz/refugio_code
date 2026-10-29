SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS booking_billing_contacts (
    event_id BIGINT UNSIGNED PRIMARY KEY,
    guest_name VARCHAR(160) NOT NULL,
    phone VARCHAR(20) NULL,
    email VARCHAR(190) NULL,
    amount DECIMAL(12,2) NULL,
    due_on DATE NULL,
    paid TINYINT(1) NOT NULL DEFAULT 0,
    updated_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_booking_billing_event FOREIGN KEY (event_id) REFERENCES calendar_external_events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS booking_billing_deliveries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NOT NULL,
    request_key VARCHAR(64) NOT NULL,
    channel VARCHAR(20) NOT NULL,
    recipient VARCHAR(190) NOT NULL,
    snapshot_json JSON NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'PENDING',
    provider_message_id VARCHAR(190) NULL,
    error_message TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    UNIQUE KEY uk_booking_delivery_request (event_id,request_key,channel),
    KEY idx_booking_delivery_event (event_id,id),
    CONSTRAINT fk_booking_delivery_event FOREIGN KEY (event_id) REFERENCES calendar_external_events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

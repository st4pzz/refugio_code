SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS conversation_auto_replies (
    message_id BIGINT UNSIGNED PRIMARY KEY,
    conversation_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL,
    error_message TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_auto_conversation (conversation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

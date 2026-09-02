-- Rascunhos de atendimento gerados pela OpenAI e submetidos a revisão humana.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS conversation_ai_drafts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    model VARCHAR(100) NOT NULL,
    openai_response_id VARCHAR(190) NULL,
    input_hash CHAR(64) NOT NULL,
    draft_text TEXT NOT NULL,
    needs_human_review TINYINT(1) NOT NULL DEFAULT 1,
    review_reason VARCHAR(500) NULL,
    facts_used_json JSON NOT NULL,
    input_tokens BIGINT UNSIGNED NULL,
    output_tokens BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_conversation_ai_draft_conversation (conversation_id,created_at),
    KEY idx_conversation_ai_draft_user (created_by,created_at),
    UNIQUE KEY uk_conversation_ai_response (openai_response_id),
    CONSTRAINT fk_conversation_ai_draft_conversation FOREIGN KEY (conversation_id) REFERENCES conversas(id) ON DELETE CASCADE,
    CONSTRAINT fk_conversation_ai_draft_user FOREIGN KEY (created_by) REFERENCES usuarios_admin(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

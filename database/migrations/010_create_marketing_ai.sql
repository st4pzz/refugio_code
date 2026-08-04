-- Analises de campanhas geradas pela OpenAI a partir de dados locais consolidados.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS marketing_analises_ia (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    filtros_json JSON NOT NULL,
    entrada_hash CHAR(64) NOT NULL,
    entrada_resumo_json JSON NOT NULL,
    resposta_json JSON NOT NULL,
    resumo_executivo TEXT NOT NULL,
    nivel_confianca VARCHAR(16) NOT NULL,
    modelo VARCHAR(100) NOT NULL,
    openai_response_id VARCHAR(190) NULL,
    input_tokens BIGINT UNSIGNED NULL,
    output_tokens BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_marketing_analise_periodo (data_inicio,data_fim),
    KEY idx_marketing_analise_criada (created_at),
    CONSTRAINT fk_marketing_analise_usuario FOREIGN KEY (created_by) REFERENCES usuarios_admin(id) ON DELETE SET NULL,
    CONSTRAINT chk_marketing_analise_confianca CHECK (nivel_confianca IN ('baixo','medio','alto'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE perfis_admin
SET permissoes_json=JSON_ARRAY_APPEND(permissoes_json,'$','marketing.analyze')
WHERE codigo IN ('ADMIN','MARKETING')
  AND JSON_CONTAINS(permissoes_json,JSON_QUOTE('marketing.analyze'))=0;

-- Rollback destrutivo das avaliacoes externas. Use somente depois de backup.
DELETE FROM avaliacoes WHERE reserva_id IS NULL;

ALTER TABLE avaliacoes
    DROP FOREIGN KEY fk_avaliacao_created_by,
    DROP CHECK chk_avaliacao_origem_plataforma,
    DROP INDEX uk_avaliacao_origem_externa,
    DROP INDEX idx_avaliacao_expiracao_externa,
    DROP COLUMN created_by,
    DROP COLUMN expira_em,
    DROP COLUMN importada_em,
    DROP COLUMN avaliacao_em,
    DROP COLUMN external_url,
    DROP COLUMN external_review_id,
    DROP COLUMN origem_plataforma,
    MODIFY reserva_id BIGINT UNSIGNED NOT NULL,
    MODIFY convite_avaliacao_id BIGINT UNSIGNED NOT NULL,
    MODIFY nota_limpeza TINYINT UNSIGNED NOT NULL,
    MODIFY nota_localizacao TINYINT UNSIGNED NOT NULL,
    MODIFY nota_conforto TINYINT UNSIGNED NOT NULL,
    MODIFY nota_comunicacao TINYINT UNSIGNED NOT NULL,
    MODIFY nota_custo_beneficio TINYINT UNSIGNED NOT NULL;

DROP TABLE IF EXISTS review_integrations;

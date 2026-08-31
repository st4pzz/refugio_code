-- Rollback manual e potencialmente destrutivo: mantem somente o aceite mais recente por versao.
DELETE older
FROM house_rule_acceptances older
JOIN house_rule_acceptances newer
  ON newer.precheckin_id=older.precheckin_id
 AND newer.rule_version_id=older.rule_version_id
 AND (newer.accepted_at>older.accepted_at OR (newer.accepted_at=older.accepted_at AND newer.id>older.id));

ALTER TABLE house_rule_acceptances
    DROP INDEX idx_house_rule_acceptance_history,
    ADD UNIQUE KEY uk_house_rule_acceptance (precheckin_id,rule_version_id);

UPDATE automation_rules
SET ativo=1
WHERE code='REVIEW_INVITATION';


-- Rollback destrutivo do portal, contratos, pre-check-in e automacoes.
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS automation_runs;
DROP TABLE IF EXISTS automation_rules;
DROP TABLE IF EXISTS house_rule_acceptances;
DROP TABLE IF EXISTS house_rule_versions;
DROP TABLE IF EXISTS reservation_pets;
DROP TABLE IF EXISTS reservation_vehicles;
DROP TABLE IF EXISTS reservation_guests;
DROP TABLE IF EXISTS precheckins;
DROP TABLE IF EXISTS contract_documents;
DROP TABLE IF EXISTS contract_events;
DROP TABLE IF EXISTS contract_signers;
DROP TABLE IF EXISTS reservation_contracts;
DROP TABLE IF EXISTS contract_template_versions;
DROP TABLE IF EXISTS contract_templates;
DROP TABLE IF EXISTS guest_portal_tokens;
SET FOREIGN_KEY_CHECKS=1;

-- Rollback destrutivo de calendario, precos e cotacoes.
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS quote_applied_rules;
DROP TABLE IF EXISTS quote_items;
DROP TABLE IF EXISTS calendar_holds;
DROP TABLE IF EXISTS quotes;
DROP TABLE IF EXISTS pricing_coupons;
DROP TABLE IF EXISTS pricing_rules;
DROP TABLE IF EXISTS pricing_special_dates;
DROP TABLE IF EXISTS pricing_seasons;
DROP TABLE IF EXISTS property_pricing_settings;
DROP TABLE IF EXISTS calendar_export_tokens;
DROP TABLE IF EXISTS calendar_sync_logs;
DROP TABLE IF EXISTS calendar_external_events;
DROP TABLE IF EXISTS calendar_sources;
SET FOREIGN_KEY_CHECKS=1;

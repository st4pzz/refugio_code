-- Rollback destrutivo do modulo Conversas.
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS conversa_notas;
DROP TABLE IF EXISTS whatsapp_templates;
DROP TABLE IF EXISTS conversa_tag_vinculos;
DROP TABLE IF EXISTS conversa_tags;
DROP TABLE IF EXISTS mensagens;
DROP TABLE IF EXISTS conversas;
DROP TABLE IF EXISTS whatsapp_webhook_eventos;
SET FOREIGN_KEY_CHECKS=1;

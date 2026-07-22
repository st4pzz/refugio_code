-- Rollback destrutivo do modulo Marketing.
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS marketing_atribuicoes;
DROP TABLE IF EXISTS marketing_sincronizacoes;
DROP TABLE IF EXISTS marketing_metricas_diarias;
DROP TABLE IF EXISTS marketing_anuncios;
DROP TABLE IF EXISTS marketing_grupos_anuncios;
DROP TABLE IF EXISTS marketing_campanhas;
DROP TABLE IF EXISTS marketing_integracoes;
SET FOREIGN_KEY_CHECKS=1;

-- Rollback destrutivo: execute somente apos backup e apos remover os modulos dependentes.
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS configuracoes_sistema;
DROP TABLE IF EXISTS jobs;
DROP TABLE IF EXISTS auditoria;
DROP TABLE IF EXISTS reserva_contatos;
DROP TABLE IF EXISTS leads;
DROP TABLE IF EXISTS clientes;
DROP TABLE IF EXISTS usuarios_admin_perfis;
DROP TABLE IF EXISTS perfis_admin;
SET FOREIGN_KEY_CHECKS=1;

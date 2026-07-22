-- Rollback destrutivo: use somente após backup e confirmação explícita.
DROP TABLE IF EXISTS historico_reserva;
DROP TABLE IF EXISTS notificacoes;
DROP TABLE IF EXISTS datas_bloqueadas;
DROP TABLE IF EXISTS pagamentos;
DROP TABLE IF EXISTS reservas;
DROP TABLE IF EXISTS rate_limits;
DROP TABLE IF EXISTS reserva_mutex;
DROP TABLE IF EXISTS usuarios_admin;

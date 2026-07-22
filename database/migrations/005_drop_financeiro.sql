-- Rollback destrutivo do modulo Financeiro.
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS caucoes;
DROP TABLE IF EXISTS conciliacao_itens;
DROP TABLE IF EXISTS conciliacoes_financeiras;
DROP TABLE IF EXISTS movimentos_financeiros;
DROP TABLE IF EXISTS pagamentos_despesas;
DROP TABLE IF EXISTS contas_pagar;
DROP TABLE IF EXISTS recorrencias_financeiras;
DROP TABLE IF EXISTS recebimentos;
DROP TABLE IF EXISTS contas_receber;
DROP TABLE IF EXISTS fornecedores;
DROP TABLE IF EXISTS categorias_financeiras;
DROP TABLE IF EXISTS contas_financeiras;
SET FOREIGN_KEY_CHECKS=1;

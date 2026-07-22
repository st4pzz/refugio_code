# Financeiro

## Modelo

O módulo `/admin/financeiro` compartilha reservas, clientes, pagamentos e usuários. Valores transacionais usam `DECIMAL(14,2)` no MySQL e strings/cêntimos no PHP (`Money`), nunca float.

Tabelas principais:

- `contas_financeiras`, `categorias_financeiras`, `fornecedores`;
- `contas_receber` e `recebimentos`;
- `contas_pagar` e `pagamentos_despesas`;
- `recorrencias_financeiras`;
- `movimentos_financeiros`;
- `conciliacoes_financeiras` e `conciliacao_itens`;
- `caucoes`.

Recebimentos e pagamentos aceitam parcelas parciais, chaves idempotentes, cancelamento antes de realização e estorno parcial/total. Ajustes criam movimento inverso e auditoria; registros originais não são apagados.

## Integração com reservas

Ao criar cobranças em uma reserva, `FinancialService::syncReservationPayments` cria/atualiza o recebível por `pagamento_reserva_id` único. Ao confirmar o pagamento, cria recebimento idempotente `reserva-payment-{id}` e movimento de entrada. Sinal, saldo, integral e caução permanecem distintos.

Para dados anteriores à migration:

```bash
php scripts/sync_contacts.php
php scripts/sync_financial_reservations.php
```

Repita os comandos com segurança; as chaves evitam duplicação.

## Operação

- **A receber:** crie recebível, vincule reserva/cliente e registre uma ou mais baixas.
- **Despesas:** vincule fornecedor/categoria/conta e registre pagamentos parciais.
- **Fluxo:** compara entradas/saídas previstas e realizadas por dia.
- **Recorrências:** gera contas a pagar por competência com chave única `(recorrencia_id, competencia)`.
- **Conciliação:** informa saldo do extrato e período; o sistema calcula saldo e diferença, com itens selecionáveis pela estrutura de dados.
- **Cauções:** ficam em tabela própria, ainda que ligadas a recebível/recebimento. Podem ser recebidas, devolvidas ou retidas parcial/totalmente, preservando motivo.
- **Exportação:** CSV UTF-8 com `;`; células iniciadas por `=`, `+`, `-` ou `@` são neutralizadas contra formula injection.

Não há integração bancária automática. Conciliação e confirmação continuam manuais.

## Cron

```cron
10 1 * * * /usr/bin/php /srv/refugio/scripts/gerar_recorrencias_financeiras.php >> /var/log/refugio-financeiro.log 2>&1
```

O script gera 45 dias, ignora competências existentes e atualiza pendências vencidas. Executá-lo duas vezes não duplica lançamentos.

## Segurança e integridade

- Mutações exigem `financeiro.manage`; exportação exige `financeiro.export`.
- Toda baixa, despesa, estorno, cancelamento, conciliação e caução é auditada.
- Baixa maior que o saldo e estorno maior que o disponível são recusados dentro de transação com `FOR UPDATE`.
- A última conta ativa é usada apenas quando `FINANCIAL_DEFAULT_ACCOUNT_ID` não foi definido; configure explicitamente em produção.
- Valores negativos e notação científica são recusados.

## Teste funcional

1. Cadastre conta, categorias e fornecedor.
2. Crie recebível de `100,00`; baixe `40,00` e depois `60,00`.
3. Repita uma baixa com a mesma idempotency key e confirme uma única linha.
4. Estorne parcialmente e confira movimento inverso/saldo disponível.
5. Crie despesa, pague em duas partes, estorne e cancele uma pendente.
6. Crie recorrência, rode o cron duas vezes e confira uma linha por competência.
7. Registre caução, recebimento, retenção parcial e devolução.
8. Concilie um período e confira diferença exata.
9. Exporte CSV e abra em ambiente isolado.
10. Confirme que ATENDIMENTO/MARKETING não alteram o financeiro.

## Backup e rollback

Antes da migration:

```bash
mysqldump --single-transaction --routines --triggers -u USUARIO -p BANCO > backup-financeiro.sql
```

O rollback `database/migrations/005_drop_financeiro.sql` é destrutivo. Pare crons, exporte relatórios, faça novo dump e só então execute. Veja a ordem completa em `docs/INTEGRACOES.md`.

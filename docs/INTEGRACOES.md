# Implantação e integrações

## Requisitos

- PHP 8.2+ com `pdo_mysql`, `curl`, `openssl`, `fileinfo`, `json` e `mbstring`;
- MySQL 8+ ou MariaDB 10.5+, InnoDB e `utf8mb4`;
- Apache 2.4 com `mod_rewrite`/`mod_headers` e HTTPS;
- cron a cada minuto para a fila;
- escrita somente em `storage/comprovantes`, `storage/qrcodes` e `storage/conversas`.

OpenSSL é obrigatório para AES-256-GCM. A aplicação recusa conectar APIs se a extensão ou `MARKETING_ENCRYPTION_KEY` estiver ausente.

## Implantação

1. Coloque o site em manutenção para a migration e faça backup:

   ```bash
   mysqldump --single-transaction --routines --triggers -u USUARIO -p BANCO > backup-antes-central.sql
   ```

2. Implante os arquivos sem sobrescrever `.env` nem conteúdo de `storage`.
3. Atualize `.env` a partir de `.env.example`. Gere `MARKETING_ENCRYPTION_KEY` uma vez.
4. Valide extensões e código:

   ```bash
   php -m
   php tests/run.php
   ```

5. Aplique migrations e backfills:

   ```bash
   php scripts/migrate.php
   php scripts/sync_contacts.php
   php scripts/sync_financial_reservations.php
   ```

6. Crie usuários adicionais com perfil explícito, teste login e cada perfil.
7. Configure OAuth/webhook usando URLs HTTPS abaixo.
8. Ative crons somente depois do teste manual.

`scripts/migrate.php` mantém `schema_migrations` com SHA-256. Migration aplicada não pode ser editada; crie a próxima migration.

## URLs públicas cadastráveis

```text
Meta OAuth:   https://SEU_DOMINIO/admin/configuracoes/integracoes/meta/callback
Google OAuth: https://SEU_DOMINIO/admin/configuracoes/integracoes/google/callback
TikTok OAuth: https://SEU_DOMINIO/admin/configuracoes/integracoes/tiktok/callback
WhatsApp:     https://SEU_DOMINIO/api/whatsapp/webhook
```

Configure `APP_URL=https://SEU_DOMINIO` sem barra final. Callbacks exigem sessão administrativa e state OAuth válido.

## Crons recomendados

```cron
* * * * * /usr/bin/php /srv/refugio/scripts/process_jobs.php --limit=100 >> /var/log/refugio-jobs.log 2>&1
*/5 * * * * /usr/bin/php /srv/refugio/scripts/expirar_reservas.php >> /var/log/refugio-reservas.log 2>&1
15 * * * * /usr/bin/php /srv/refugio/scripts/enviar_convites_avaliacao.php >> /var/log/refugio-avaliacoes.log 2>&1
10 1 * * * /usr/bin/php /srv/refugio/scripts/gerar_recorrencias_financeiras.php >> /var/log/refugio-financeiro.log 2>&1
20 3 * * * /usr/bin/php /srv/refugio/scripts/aplicar_retencao.php >> /var/log/refugio-retencao.log 2>&1
30 2 * * * /usr/bin/php /srv/refugio/scripts/sync_marketing.php --inicio=$(date -d '7 days ago' +\%F) --fim=$(date +\%F) >> /var/log/refugio-marketing.log 2>&1
```

Em hospedagem que não suporta expansão de `date` no cron, use somente o job do painel ou um wrapper controlado.

## Logs e diagnóstico

- PHP/Apache: exceções com prefixo do módulo e sem secrets;
- `auditoria`: alterações administrativas, usuário, IP e correlation ID;
- `jobs`: estado, tentativa e último erro;
- `whatsapp_webhook_eventos`: recebimento/processamento;
- `marketing_sincronizacoes`: período, contagens e erro;
- `mensagens.erro`: falha de envio/status;
- `notificacoes`: SMTP/templates ligados a reservas.

Nunca copie tokens ou `.env` para chamados. Use status, IDs internos e correlation ID.

## Rollback

Rollbacks SQL são manuais e destrutivos. Pare worker/crons, faça dump e remova na ordem inversa:

```text
006_drop_marketing.sql
005_drop_financeiro.sql
004_drop_conversas.sql
003_drop_core_operacao.sql
```

Não execute `003` enquanto módulos 004–006 existirem. Para reaplicar após rollback, remova também os nomes correspondentes de `schema_migrations`; nunca faça isso sem confirmar que as tabelas foram realmente removidas. Restaurar o dump é o caminho preferencial.

## Checklist pós-implantação

- HTTPS, cookies Secure e `APP_DEBUG=false`;
- `.env`, `app`, `database`, `scripts`, `storage` e `tests` não servidos diretamente;
- OpenSSL/cURL/PDO MySQL ativos;
- menu e negações por perfil;
- webhook válido/falso, evento duplicado e mídia;
- baixa parcial, estorno, recorrência e conciliação;
- OAuth/teste/sync de uma conta por provedor;
- worker sem jobs presos e logs sem segredo;
- layout desktop, tablet, celular e teclado.

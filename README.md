# Refugio do Cuscuzeiro

Site institucional e sistema de solicitacao e gerenciamento de reservas diretas em PHP puro. O fluxo e manual: o cliente solicita datas, o administrador verifica disponibilidade, cadastra uma cobranca Pix criada no aplicativo do banco, confere o pagamento e confirma a reserva.

## Requisitos

- Apache 2.4 com `mod_rewrite`, `mod_headers`, PHP 8.2+ e HTTPS;
- MySQL 8+ ou MariaDB 10.5+ com InnoDB;
- extensoes PHP PDO MySQL, OpenSSL e JSON; Fileinfo e cURL sao recomendadas;
- acesso a cron para expiracao automatica;
- conta SMTP e, opcionalmente, WhatsApp Cloud API com templates aprovados.

O projeto nao introduz framework ou dependencias Composer. O cliente SMTP usa conexao TLS nativa e todas as consultas usam PDO preparado.

## Instalacao

1. Faca backup dos arquivos e do banco antes de implantar:

   ```bash
   mysqldump --single-transaction -u USUARIO -p BANCO > backup-antes-reservas.sql
   ```

2. Copie `.env.example` para `.env`, gere `APP_KEY` com pelo menos 32 bytes aleatorios e preencha banco, URL, SMTP e demais opcoes. Nunca versione `.env`.
3. Crie o banco vazio com `utf8mb4` e execute:

   ```bash
   php scripts/migrate.php
   php scripts/create_admin.php admin@dominio.com "Nome do administrador"
   ```

4. Garanta escrita para o usuario do PHP apenas em `storage/comprovantes` e `storage/qrcodes`. A raiz `storage` possui regra Apache que nega acesso direto.
5. Confirme que o DocumentRoot permite `.htaccess`. As rotas amigaveis dependem de `mod_rewrite`.

## Variaveis de ambiente

As opcoes estao documentadas em `.env.example`:

- aplicacao: `APP_ENV`, `APP_DEBUG`, `APP_URL`, `APP_TIMEZONE`, `APP_KEY`, `SESSION_SECURE`;
- banco: `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_CHARSET`;
- negocio: `MAX_GUESTS`, `CPF_REQUIRED`, `UPLOAD_MAX_MB`, `KEEP_RECEIPT_AFTER_EXPIRY`, `CONTACT_WHATSAPP`;
- e-mail: `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_ENCRYPTION`, `SMTP_FROM_EMAIL`, `SMTP_FROM_NAME`, `ADMIN_EMAIL`;
- WhatsApp: `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_ACCESS_TOKEN`, `WHATSAPP_API_VERSION`, `WHATSAPP_BUSINESS_ACCOUNT_ID`, idioma e nomes dos templates.

Em producao use `APP_DEBUG=false`, `SESSION_SECURE=true` e HTTPS. O access token da Meta e a senha SMTP nunca sao impressos em logs.

## SMTP

Configure um servidor com TLS (`SMTP_ENCRYPTION=tls`, geralmente porta 587) ou SSL implicito (`ssl`, geralmente 465). Teste uma solicitacao e confira `notificacoes`: falhas ficam com status `FALHOU` sem reverter a operacao principal e podem ser reenviadas no painel.

## WhatsApp Cloud API

Cadastre os seguintes templates em `pt_BR`; os nomes reais sao configuraveis:

- `WHATSAPP_TEMPLATE_SOLICITACAO_RECEBIDA`;
- `WHATSAPP_TEMPLATE_RESERVA_APROVADA`;
- `WHATSAPP_TEMPLATE_RESERVA_RECUSADA`;
- `WHATSAPP_TEMPLATE_PAGAMENTO_CONFIRMADO`;
- `WHATSAPP_TEMPLATE_RESERVA_CONFIRMADA`;
- `WHATSAPP_TEMPLATE_PAGAMENTO_EXPIRADO`;
- `WHATSAPP_TEMPLATE_RESERVA_CANCELADA`.

Os templates devem aceitar, conforme o evento, nome, codigo, check-in, check-out, valor e link publico. A cobranca sempre permanece acessivel pela pagina publica; o sistema nao depende da imagem do QR no WhatsApp.

## Cron

Execute a cada 5 ou 10 minutos, conforme a hospedagem permitir:

```cron
*/5 * * * * /usr/bin/php /caminho/do/projeto/scripts/expirar_reservas.php >> /caminho/seguro/cron-reservas.log 2>&1
```

Com `KEEP_RECEIPT_AFTER_EXPIRY=true`, reservas com comprovante enviado permanecem para analise; as demais vencidas sao expiradas e liberam as datas. O processamento e idempotente.

## Testes e roteiro funcional

Execute os testes de regras e seguranca:

```bash
php tests/run.php
```

Teste manual completo:

1. abra `/reserva/solicitar`, envie uma solicitacao e confirme e-mail/WhatsApp e registro no painel;
2. em `/admin`, aprove com valor, prazo, Pix e QR Code;
3. abra o link publico, copie o Pix e envie PDF/JPG/PNG;
4. baixe o comprovante somente no painel e confirme manualmente o pagamento;
5. verifique confirmacao, bloqueio no calendario, historico e notificacoes;
6. crie uma segunda solicitacao sobreposta e confirme que a aprovacao e impedida;
7. crie uma cobranca vencida, execute o cron e confira expiracao e liberacao.

## Estrutura

- `app/Controllers`, `Models`, `Repositories`, `Services`, `Support` e `Views`: aplicacao;
- `database/migrations`: migration e rollback SQL;
- `reserva` e `api`: endpoints publicos;
- `admin`: front controller administrativo;
- `scripts`: migration, criacao segura de administrador e cron;
- `storage`: arquivos privados com nomes aleatorios;
- `docs/FLUXO_RESERVAS.md`: estados, transicoes e concorrencia.

## Rollback

O arquivo `database/migrations/001_drop_reservas.sql` remove todas as tabelas novas e e deliberadamente manual/destrutivo. Antes de usa-lo, retire o site de operacao, confirme backup restauravel e preserve os comprovantes. Para rollback de codigo, restaure a versao anterior dos arquivos; nao execute o SQL de remocao se quiser manter dados.

## Limitacoes e proxima etapa Pix

- A disponibilidade e gerenciada localmente; nao ha sincronizacao iCal com Airbnb/Booking nesta etapa.
- O pagamento e conferido manualmente e nao existe consulta bancaria, estorno automatico ou conciliacao.
- Os textos legais em `politicas/index.php` sao modelos iniciais e precisam de revisao do responsavel/juridico.
- Para automatizar Pix no futuro, use uma instituicao com API oficial, crie uma camada `PixProvider`, armazene IDs externos e implemente webhook autenticado, idempotente e auditado. Nunca considere apenas o retorno do navegador como confirmacao.

## Comandos de implantacao

```bash
cp .env.example .env
# editar .env fora do controle de versao
php scripts/migrate.php
php scripts/create_admin.php admin@dominio.com "Administrador"
php tests/run.php
```

Depois, configure permissoes de `storage`, HTTPS e cron, e valide SMTP/WhatsApp em producao controlada.

# Refúgio do Cuscuzeiro

Site institucional e central administrativa em PHP puro para reservas diretas, avaliações verificadas, clientes, conversas via WhatsApp, marketing e controle financeiro. O painel preserva o fluxo público existente e adiciona permissões granulares, auditoria e processamento assíncrono.

## Requisitos

- Apache 2.4 com `mod_rewrite` e `mod_headers`, PHP 8.2+ e HTTPS;
- MySQL 8+ ou MariaDB 10.5+ com InnoDB e `utf8mb4`;
- extensões PHP PDO MySQL, DOM, JSON, mbstring, cURL, OpenSSL, Fileinfo e GD;
- Composer 2 para instalar as dependências PHP bloqueadas em `composer.lock`;
- acesso a cron ou scheduler para reservas, avaliações, fila, financeiro, retenção e sincronizações;
- SMTP e, conforme os módulos habilitados, credenciais oficiais de WhatsApp Cloud API, Meta Ads, Google Ads e TikTok Ads.

O projeto não usa framework. A geração de PDFs usa `dompdf/dompdf`, instalada pelo Composer; valores financeiros são manipulados como centavos/decimais exatos e as consultas usam PDO preparado.

## Implantação segura

1. Pare os workers e faça backup dos arquivos e do banco:

   ```bash
   mysqldump --single-transaction --routines --triggers -u USUARIO -p BANCO > backup-antes-central.sql
   ```

2. Instale as dependências bloqueadas e depois copie `.env.example` para `.env`. Gere chaves diferentes e aleatórias para `APP_KEY` e `MARKETING_ENCRYPTION_KEY`, cada uma com ao menos 32 bytes. Nunca versione `.env`.

   ```bash
   composer2 install --no-dev --optimize-autoloader
   ```

3. Em homologação, execute as migrations incrementais e os backfills:

   ```bash
   php scripts/migrate.php
   php scripts/sync_contacts.php
   php scripts/sync_financial_reservations.php
   php scripts/create_admin.php admin@dominio.com "Administrador" SUPER_ADMIN
   php tests/run.php
   ```

4. Garanta escrita somente nos diretórios privados necessários:

   - `storage/comprovantes`;
   - `storage/qrcodes`;
   - `storage/conversas`.
   - `storage/contracts`;
   - `storage/reservation-documents`;
   - `tmp`.

   A raiz `storage` contém regra Apache que nega acesso direto. Mídias de conversa são entregues apenas por rota autenticada e autorizada.

5. Valide em homologação os fluxos manuais, configure webhooks/redirect URIs com HTTPS e só então repita as migrations em produção. O executor registra nome e checksum em `schema_migrations` e não reaplica migrations concluídas.

## Módulos administrativos

O menu lateral responsivo possui oito áreas:

- Visão geral;
- Reservas;
- Avaliações;
- Clientes;
- Conversas;
- Marketing;
- Financeiro;
- Configurações.

As permissões são atribuídas por perfil. A migration mantém administradores existentes como `SUPER_ADMIN`. Consulte [docs/PERMISSOES.md](docs/PERMISSOES.md) antes de criar perfis operacionais.

## Variáveis de ambiente

Todas as opções e exemplos estão em `.env.example`:

- aplicação: `APP_ENV`, `APP_DEBUG`, `APP_URL`, `APP_TIMEZONE`, `APP_CURRENCY`, `APP_KEY`, `MARKETING_ENCRYPTION_KEY`, `SESSION_SECURE`;
- banco: `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_CHARSET`;
- reserva: `MAX_GUESTS`, `CPF_REQUIRED`, `UPLOAD_MAX_MB`, `KEEP_RECEIPT_AFTER_EXPIRY`, `CONTACT_WHATSAPP`;
- e-mail: `SMTP_*` e `ADMIN_EMAIL`;
- avaliações: `REVIEW_INVITATION_*` e `REVIEW_REMINDER_*`;
- conversas: `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_BUSINESS_ACCOUNT_ID`, `WHATSAPP_ACCESS_TOKEN`, `WHATSAPP_APP_SECRET`, `WHATSAPP_VERIFY_TOKEN`, `WHATSAPP_API_VERSION`, templates, limite de mídia e retenção;
- marketing: `META_*`, `GOOGLE_ADS_*`, `TIKTOK_ADS_*` e `OPENAI_*` para análises assistidas;
- financeiro: `FINANCIAL_DEFAULT_ACCOUNT_ID`.

Em produção use `APP_DEBUG=false`, `SESSION_SECURE=true` e HTTPS. `MARKETING_ENCRYPTION_KEY` protege tokens OAuth com AES-256-GCM e exige OpenSSL. Segredos e dados pessoais são saneados antes de auditoria.

## Cadastro das integrações

| Integração | Onde criar e acesso mínimo | URL cadastrada | Teste, renovação e desconexão |
|---|---|---|---|
| Meta Ads | [Meta for Developers](https://developers.facebook.com/), app Business com `ads_read` e `business_management` | `META_REDIRECT_URI` | “Testar conexão” no painel; reconecte quando o token expirar. “Desconectar” apaga os tokens e preserva o histórico. |
| Google Ads | Google Cloud OAuth Web, Google Ads API/developer token e escopo `https://www.googleapis.com/auth/adwords` | `GOOGLE_ADS_REDIRECT_URI` | O refresh token renova o acesso automaticamente; ausência/revogação exige reconexão. A desconexão remove ambos os tokens locais. |
| TikTok Ads | [TikTok for Business](https://business-api.tiktok.com/portal/docs), app autorizado para leitura das advertisers | `TIKTOK_ADS_REDIRECT_URI` | O refresh é usado quando fornecido; caso contrário, reconecte. A desconexão preserva apenas dados sincronizados. |
| WhatsApp | Meta for Developers/WhatsApp Cloud API, WABA, Phone Number ID, token e assinatura do campo `messages` | webhook `https://SEU_DOMINIO/api/whatsapp/webhook` | Valide challenge, assinatura e envio para número de teste. Rotacione o token no cofre/.env; não há token editável no painel. |

Callbacks devem coincidir exatamente com HTTPS, host e caminho do `.env`. Contas, revisões de aplicativo, métricas e permissões disponíveis dependem de cada provedor. A configuração detalhada e os links oficiais ficam em [docs/MARKETING.md](docs/MARKETING.md) e [docs/CONVERSAS_WHATSAPP.md](docs/CONVERSAS_WHATSAPP.md).

## WhatsApp Cloud API e Conversas

Configure no aplicativo Meta:

- callback: `https://SEU_DOMINIO/api/whatsapp/webhook`;
- verify token igual a `WHATSAPP_VERIFY_TOKEN`;
- app secret igual a `WHATSAPP_APP_SECRET`;
- assinatura dos eventos `X-Hub-Signature-256` habilitada;
- assinatura do campo `messages` para a conta empresarial.

O `GET` confirma o challenge e o `POST` valida HMAC antes de persistir o evento. O endpoint responde rapidamente e a fila processa mensagens/status de forma idempotente. Texto livre só é oferecido dentro da janela de 24 horas; fora dela o operador deve usar template aprovado.

O link público `/contato/whatsapp` captura UTMs/clids, cria uma referência `REF-*` e redireciona para `wa.me`. A resposta que contém a referência liga lead, conversa e atribuição.

Detalhes de templates, tipos de mensagem, mídias, retenção, webhook e testes estão em [docs/CONVERSAS_WHATSAPP.md](docs/CONVERSAS_WHATSAPP.md).

## Marketing

Integrações são conectadas por OAuth no painel, com `state` assinado/temporário e tokens criptografados. A seleção de conta é explícita. A sincronização é somente leitura, paginada, idempotente e protegida por lock; falhas transitórias usam retentativa com backoff.

O dashboard consolida investimento, impressões, alcance, cliques, CTR, CPC, CPM, conversões, receita atribuída e ROAS. Valores do Google Ads em micros são convertidos de forma explícita. A atribuição first/last touch combina UTMs e identificadores `gclid`, `gbraid`, `wbraid`, `fbclid` e `ttclid`; conversões continuam sendo indicativas, não causalidade provada.

Usuários com `marketing.analyze` podem solicitar, sob demanda, uma análise pela OpenAI para o período e filtros atuais. A solicitação entra na fila de jobs e o worker usa a Responses API em background, evitando manter o POST do painel aberto até a conclusão. A saída é JSON estruturada, com `store=false`, histórico local e auditoria. Somente métricas consolidadas, nomes de campanhas e metadados dos criativos são enviados; tokens dos provedores, hóspedes e conversas não fazem parte do contexto. A resposta sugere melhorias e testes, mas nunca altera campanhas automaticamente.

Configuração de cada provedor, redirect URIs, escopos, versões e validação estão em [docs/MARKETING.md](docs/MARKETING.md) e [docs/INTEGRACOES.md](docs/INTEGRACOES.md).

## Financeiro

O módulo inclui contas, categorias, fornecedores, contas a receber/pagar, recebimentos/pagamentos parciais, estornos, cancelamentos, recorrências, fluxo de caixa, conciliação manual, cauções e exportação CSV protegida contra formula injection.

Pagamentos confirmados de reservas são sincronizados de forma idempotente. Uma caução deve ser recebida integralmente; devolução gera movimento de saída e retenção parcial/total exige justificativa. Não há integração bancária nem confirmação automática de Pix.

Regras contábeis operacionais, fórmulas, backfill e roteiro de conferência estão em [docs/FINANCEIRO.md](docs/FINANCEIRO.md).

## Cron e workers

Exemplo para uma instalação em `/var/www/refugio`:

```cron
*/5 * * * * /usr/bin/php /var/www/refugio/scripts/process_jobs.php --limit=50 >> /var/log/refugio-jobs.log 2>&1
*/5 * * * * /usr/bin/php /var/www/refugio/scripts/expirar_reservas.php >> /var/log/refugio-reservas.log 2>&1
20 2 * * * /usr/bin/php /var/www/refugio/scripts/gerar_recorrencias_financeiras.php >> /var/log/refugio-financeiro.log 2>&1
15 * * * * /usr/bin/php /var/www/refugio/scripts/enviar_convites_avaliacao.php >> /var/log/refugio-avaliacoes.log 2>&1
10 3 * * * /usr/bin/php /var/www/refugio/scripts/aplicar_retencao.php >> /var/log/refugio-retencao.log 2>&1
0 */6 * * * /usr/bin/php /var/www/refugio/scripts/sync_marketing.php >> /var/log/refugio-marketing.log 2>&1
```

Use um gerenciador de processo para worker contínuo ou cron curto. Nunca execute dois workers de marketing para a mesma integração; o serviço também usa lock no banco como segunda proteção.

## Testes e aceite manual

Execute a suíte local:

```bash
php tests/run.php
```

Roteiro mínimo de homologação:

1. solicite, aprove, pague, confirme, finalize e cancele reservas sem regressão no calendário;
2. convide, envie, modere, publique e oculte uma avaliação;
3. verifique perfis com acesso permitido e negado em cada módulo;
4. abra `/contato/whatsapp`, responda com a referência e valide inbox, janela de 24h, template, mídia, status e idempotência;
5. conecte uma conta de teste de cada provedor, selecione a conta, sincronize duas vezes e compare os totais com a interface oficial;
6. registre recebimento/pagamento parcial, estorne, cancele saldo zerado e confira movimentos e conciliação;
7. receba, retenha parcialmente e devolva uma caução, conferindo a saída gerada;
8. una clientes duplicados, exporte os dados de um cliente e anonimize um registro de teste;
9. teste larguras de 360 px, 768 px e desktop, teclado, foco visível e contraste;
10. confira `auditoria`, `jobs` e logs sem tokens, conteúdo integral de mensagens ou dados pessoais desnecessários.

Chamadas reais aos provedores e a aplicação das migrations devem ser validadas em banco/contas de homologação. A suíte unitária não substitui esse aceite.

## Estrutura

- `app/Controllers`, `Repositories`, `Services`, `Support` e `Views`: aplicação;
- `database/migrations`: migrations incrementais e rollbacks manuais;
- `admin`: front controller administrativo;
- `api/whatsapp`: webhook da Cloud API;
- `contato`: entrada pública para WhatsApp e captura de atribuição;
- `scripts`: migrations, backfills, workers e rotinas agendadas;
- `storage`: comprovantes, QR Codes e mídias privadas;
- `docs`: fluxos, integrações, segurança, permissões e operação.

## Backup e rollback

Os arquivos `*_drop_*.sql` são manuais e destrutivos. Em uma reversão:

1. coloque a aplicação em manutenção e pare crons/workers;
2. faça novo dump do banco e preserve `storage`;
3. reverta o código para a versão compatível;
4. se for indispensável remover tabelas, execute os drops na ordem `006`, `005`, `004`, `003`; só use `002`/`001` se também quiser remover avaliações/reservas;
5. restaure o dump se qualquer validação falhar.

Prefira roll-forward: corrija a migration/aplicação com uma nova migration em vez de apagar dados. Nunca edite uma migration já registrada; o checksum detecta divergência.

## Limitações conhecidas

- disponibilidade não sincroniza iCal com Airbnb/Booking;
- integrações de anúncios são somente leitura e dependem de permissões/aprovação de cada plataforma;
- atribuição entre clique e reserva é indicativa e pode divergir dos modelos dos provedores;
- conciliação é manual por saldo/período; não há Open Finance, importação OFX ou liquidação bancária automática;
- o inbox usa polling curto no navegador, não WebSocket;
- textos legais em `politicas/index.php` são modelos iniciais e precisam de revisão jurídica.

## Documentação complementar

- [Fluxo de reservas](docs/FLUXO_RESERVAS.md)
- [Pedidos de reserva via WhatsApp](docs/PEDIDOS_WHATSAPP.md)
- [Sistema de avaliações](docs/SISTEMA_AVALIACOES.md)
- [Conversas e WhatsApp](docs/CONVERSAS_WHATSAPP.md)
- [Marketing](docs/MARKETING.md)
- [Financeiro](docs/FINANCEIRO.md)
- [Perfis e permissões](docs/PERMISSOES.md)
- [Integrações, segurança e operação](docs/INTEGRACOES.md)
# Operação integrada de reservas

O painel inclui calendário unificado/iCal, retenções e conflitos, configurações da propriedade, motor de preços, snapshots de orçamento, portal seguro, contrato versionado com PDF e assinatura auditável, pré-check-in e 17 automações de jornada.

Comece por [Configuração inicial](docs/CONFIGURACAO_INICIAL.md). Documentação por domínio: [calendário](docs/CALENDARIO_UNIFICADO.md), [preços](docs/MOTOR_DE_PRECOS.md), [orçamentos](docs/ORCAMENTOS.md), [portal](docs/PORTAL_HOSPEDE.md), [contratos](docs/CONTRATOS.md), [pré-check-in](docs/PRE_CHECKIN.md) e [automações](docs/AUTOMACOES_RESERVA.md).

As migrações novas são `007_create_calendar_pricing_quotes.sql` e `008_create_guest_journey.sql`. O preço público nasce desativado e o contrato sugerido nasce pendente de aprovação.

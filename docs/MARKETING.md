# Marketing

## Escopo

O módulo `/admin/marketing` é somente leitura e análise. Ele conecta Meta Ads, Google Ads e TikTok Ads por OAuth, seleciona a conta autorizada, sincroniza campanhas/grupos/anúncios/métricas para o MySQL e monta o dashboard exclusivamente com dados locais. Não cria, altera, ativa ou exclui campanhas.

As versões usadas em julho de 2026 são configuráveis: Meta Graph/Marketing `v24.0`, Google Ads `v24` e TikTok Business API `v1.3`. Antes de atualizar, confira os calendários de versão oficiais e execute a suíte de testes.

## Arquitetura

- `app/Marketing/MarketingProviderInterface.php`: contrato comum.
- `MetaAdsProvider`, `GoogleAdsProvider` e `TikTokAdsProvider`: diferenças de autenticação, paginação, campos e unidades.
- `MarketingOAuthService`: state OAuth de uso único com validade de 10 minutos.
- `MarketingSyncService`: lock por integração, paginação, upsert e auditoria.
- `MarketingHttpClient`: retry limitado com backoff para transporte, `429` e `5xx`.
- `MarketingRepository`: dashboard, campanhas e atribuição.
- `scripts/sync_marketing.php`: sincronização CLI.

Tokens são criptografados com AES-256-GCM e `MARKETING_ENCRYPTION_KEY`; nunca chegam às views, JavaScript ou logs. O retorno bruto relevante fica em `dados_extras_json`, sanitizado no fluxo de auditoria.

## Variáveis

```dotenv
MARKETING_ENCRYPTION_KEY=BASE64_DE_32_BYTES
META_API_VERSION=v24.0
META_APP_ID=
META_APP_SECRET=
META_REDIRECT_URI=https://SEU_DOMINIO/admin/configuracoes/integracoes/meta/callback
GOOGLE_ADS_API_VERSION=v24
GOOGLE_ADS_CLIENT_ID=
GOOGLE_ADS_CLIENT_SECRET=
GOOGLE_ADS_DEVELOPER_TOKEN=
GOOGLE_ADS_LOGIN_CUSTOMER_ID=
GOOGLE_ADS_REDIRECT_URI=https://SEU_DOMINIO/admin/configuracoes/integracoes/google/callback
TIKTOK_API_VERSION=v1.3
TIKTOK_ADS_APP_ID=
TIKTOK_ADS_APP_SECRET=
TIKTOK_ADS_REDIRECT_URI=https://SEU_DOMINIO/admin/configuracoes/integracoes/tiktok/callback
MARKETING_SYNC_DAYS_DEFAULT=30
MARKETING_SYNC_CRON_ENABLED=true

# Analise de campanhas com OpenAI
OPENAI_API_KEY=
OPENAI_PROJECT=
OPENAI_ORGANIZATION=
OPENAI_MARKETING_ENABLED=true
OPENAI_MARKETING_MODEL=gpt-5.6-sol
OPENAI_MARKETING_REASONING_EFFORT=medium
OPENAI_MARKETING_MAX_OUTPUT_TOKENS=7000
OPENAI_MARKETING_MIN_INTERVAL_SECONDS=60
OPENAI_TIMEOUT_SECONDS=90
OPENAI_MAX_RETRIES=2
```

Gere a chave uma única vez e guarde-a no cofre de segredos da hospedagem:

```bash
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

Perder ou trocar a chave sem reconectar as contas torna os tokens existentes indecifráveis.

`OPENAI_API_KEY` é uma chave da API da OpenAI e não uma assinatura do ChatGPT. Crie a chave em um projeto da plataforma OpenAI, habilite faturamento/limites e guarde-a somente no `.env` do servidor. `OPENAI_PROJECT` e `OPENAI_ORGANIZATION` são opcionais; use-os quando a chave estiver vinculada a um projeto/organização específicos.

## Análise com OpenAI

O botão “Analisar com IA” usa exclusivamente o snapshot local correspondente ao período, canal, conta, campanha e modelo de atribuição selecionados. O contexto contém:

- totais consolidados e atribuição indicativa;
- comparação por canal e série diária limitada;
- desempenho agregado por campanha;
- inventário de nomes/URLs de criativos, explicitamente identificado como sem desempenho individual.

Não são enviados tokens OAuth, dados de hóspedes, mensagens de WhatsApp ou comprovantes. A requisição usa a Responses API com `store=false`, saída estruturada por JSON Schema, identificador de segurança pseudônimo, timeout e retentativas limitadas. Uma análise concluída é armazenada em `marketing_analises_ia`, incluindo snapshot, hash da entrada, modelo, resposta e uso de tokens para rastreabilidade.

O prompt fixa o posicionamento real do Refúgio: chácara em Analândia/SP, até 10 pessoas, 4 suítes, lazer e natureza, diária de referência de R$ 800, público de famílias e grupos de amigos e proibição de festas/eventos. Recomendações são consultivas; o módulo continua sem endpoints para alterar campanhas.

Antes de habilitar em produção:

1. execute `php scripts/migrate.php` para aplicar `010_create_marketing_ai.sql`;
2. configure `OPENAI_API_KEY` e mantenha `APP_DEBUG=false`;
3. sincronize Meta, Google e/ou TikTok;
4. filtre um período curto e gere a primeira análise;
5. confira `marketing_analises_ia` e `auditoria` sem expor a chave;
6. defina limite de orçamento e alertas de uso no projeto OpenAI.

`OPENAI_MARKETING_MIN_INTERVAL_SECONDS` reduz cliques repetidos e custo acidental. Ajuste o modelo por ambiente com `OPENAI_MARKETING_MODEL`; valide qualidade, latência e custo antes de aumentar o esforço ou o limite de saída.

Referências oficiais: [Responses API](https://platform.openai.com/docs/api-reference/responses), [Structured Outputs](https://platform.openai.com/docs/guides/structured-outputs), [modelos atuais](https://developers.openai.com/api/docs/models) e [controles de dados](https://platform.openai.com/docs/models/default-usage-policies-by-endpoint).

## Meta

A seção **Marketing > Fontes da análise** detecta `META_APP_ID`, `META_APP_SECRET` e uma `MARKETING_ENCRYPTION_KEY` válida sem exibir seus valores. O estado “Aplicativo configurado” confirma apenas que o aplicativo está pronto para iniciar o OAuth. Depois, autorize a conta de anúncios, selecione a conta retornada e sincronize as campanhas. Se o OAuth já foi concluído mas a conta ainda não foi escolhida, o botão “Selecionar conta de anúncios” retoma o fluxo sem criar outra integração.

As credenciais do WhatsApp (`WHATSAPP_ACCESS_TOKEN` e `WHATSAPP_PHONE_NUMBER_ID`) não são reutilizadas pela Marketing API: os produtos têm escopos e ativos diferentes. O token com `ads_read` obtido pelo fluxo da seção Marketing é criptografado antes de ser salvo.

1. Crie um aplicativo Business no [Meta for Developers](https://developers.facebook.com/).
2. Adicione Marketing API/Facebook Login e cadastre o callback:

   ```text
   https://SEU_DOMINIO/admin/configuracoes/integracoes/meta/callback
   ```

3. Solicite somente `ads_read` e `business_management`. Para ativos de terceiros pode ser necessário Advanced Access e revisão do app.
4. Conecte no painel, escolha Business/conta e, quando retornados pela conta, página e pixel/dataset.
5. O adaptador pagina por `paging.next` e lê campanhas, ad sets, anúncios e Insights diários.

Referências oficiais: [Marketing API Get Started](https://developers.facebook.com/docs/marketing-api/get-started), [autenticação](https://developers.facebook.com/docs/marketing-api/overview/authentication), [Insights API](https://developers.facebook.com/docs/marketing-api/insights) e a [coleção oficial Meta no Postman](https://www.postman.com/meta/facebook-marketing-api/overview).

## Google Ads

1. Crie credenciais OAuth Web no Google Cloud e habilite acesso à Google Ads API.
2. Cadastre o callback:

   ```text
   https://SEU_DOMINIO/admin/configuracoes/integracoes/google/callback
   ```

3. Configure o developer token e, se usar uma conta administradora, `GOOGLE_ADS_LOGIN_CUSTOMER_ID` sem hífens.
4. O fluxo solicita `https://www.googleapis.com/auth/adwords`, `access_type=offline` e armazena refresh token criptografado.
5. A sincronização usa REST/GAQL. Valores `*_micros` são convertidos por aritmética decimal (`1.000.000 micros = 1 unidade`), sem float financeiro.

Referências oficiais: [OAuth](https://developers.google.com/google-ads/api/docs/oauth/overview), [autenticação REST](https://developers.google.com/google-ads/api/rest/auth), [GAQL](https://developers.google.com/google-ads/api/docs/query/overview) e [sunset de versões](https://developers.google.com/google-ads/api/docs/sunset-dates).

## TikTok

1. Crie um app no [TikTok for Business API for Business](https://business-api.tiktok.com/portal/docs).
2. Cadastre o callback:

   ```text
   https://SEU_DOMINIO/admin/configuracoes/integracoes/tiktok/callback
   ```

3. Autorize as contas advertiser necessárias.
4. O painel permite selecionar advertiser/Business Center retornado e pixel quando disponível.
5. O adaptador usa `campaign/get`, `adgroup/get`, `ad/get` e `report/integrated/get`, com paginação.

Referência oficial: [TikTok OAuth](https://business-api.tiktok.com/portal/docs/oauth) e [documentação da Marketing API](https://business-api.tiktok.com/portal/docs).

## Sincronização

Pelo painel, “Sincronizar agora” cria um job. O worker deve rodar a cada minuto:

```cron
* * * * * /usr/bin/php /srv/refugio/scripts/process_jobs.php --limit=100 >> /var/log/refugio-jobs.log 2>&1
```

Sincronização direta ou retroativa:

```bash
php scripts/sync_marketing.php
php scripts/sync_marketing.php --integracao=3 --inicio=2026-07-01 --fim=2026-07-22
```

O processo usa `GET_LOCK`, registra `marketing_sincronizacoes`, limita paginação/retries e faz upsert pelas chaves externas. Uma página do dashboard nunca chama uma API externa.

## Atribuição

O bootstrap preserva primeiro e último contato para `utm_*`, `gclid`, `gbraid`, `wbraid`, `fbclid`, `ttclid`, landing page e referrer. Solicitações vinculam a origem à reserva/cliente. O botão público de WhatsApp cria uma referência `REF-*`; quando ela volta na primeira mensagem, o lead e sua origem são recuperados.

Modelos disponíveis: primeiro contato e último contato. A atribuição é indicativa. Receita atribuída soma apenas `pagamentos.status='CONFIRMADO'`.

```text
CPL = gasto / leads
Custo por reserva = gasto / reservas confirmadas
ROAS = receita confirmada atribuída / gasto
Conversão = reservas confirmadas / leads
```

Divisão por zero retorna “—”. Métricas não fornecidas pelo provedor permanecem nulas; o painel não inventa valores.

## Teste e desconexão

1. Conecte uma conta de homologação/baixo risco.
2. Use “Testar conexão”.
3. Sincronize 7 dias e execute `php scripts/process_jobs.php`.
4. Confira `marketing_sincronizacoes`, campanhas, métricas e dashboard.
5. Repita o mesmo período e confirme que a contagem não duplica.
6. “Desconectar” apaga access/refresh tokens criptografados, preservando dados históricos sincronizados.

Sem credenciais reais, apenas adaptadores, OAuth, normalização e testes locais podem ser validados; a autorização final depende das contas e revisões de cada plataforma.

# Conversas e WhatsApp Cloud API

## Endpoint e configuração

Use a mesma aplicação Meta/WhatsApp já usada para templates. Não existe uma segunda integração.

```text
Webhook: https://SEU_DOMINIO/api/whatsapp/webhook
Objeto: whatsapp_business_account
Campo: messages
```

Variáveis obrigatórias:

```dotenv
WHATSAPP_API_VERSION=v24.0
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_BUSINESS_ACCOUNT_ID=
WHATSAPP_ACCESS_TOKEN=
WHATSAPP_APP_SECRET=
WHATSAPP_VERIFY_TOKEN=
WHATSAPP_MEDIA_MAX_MB=20
WHATSAPP_MEDIA_RETENTION_DAYS=180
```

No GET de verificação, `hub.verify_token` precisa coincidir com `WHATSAPP_VERIFY_TOKEN`. Todo POST precisa de `X-Hub-Signature-256`, verificada com HMAC-SHA-256 e App Secret antes de ler o evento. Sem secret ou assinatura válida, o endpoint responde `401`.

Referências oficiais: [componentes de webhook da Cloud API](https://developers.facebook.com/docs/whatsapp/cloud-api/webhooks/components), [payloads oficiais no Postman da Meta](https://www.postman.com/meta/whatsapp-business-platform/folder/tduohwq/webhook-payload-reference) e [verificação/assinatura no SDK oficial](https://whatsapp.github.io/WhatsApp-Nodejs-SDK/api-reference/webhooks/start/).

## Fluxo idempotente

1. O endpoint valida assinatura e JSON.
2. Persiste o payload em `whatsapp_webhook_eventos`, único por SHA-256.
3. Cria um job `WHATSAPP_WEBHOOK` com chave única e responde `EVENT_RECEIVED`.
4. `scripts/process_jobs.php` normaliza telefone, procura cliente/lead/reserva, cria ou atualiza conversa e persiste a mensagem por `external_message_id` único.
5. Somente a primeira mensagem de entrada de uma conversa cria um job idempotente de alerta por e-mail. Mensagens posteriores, inclusive após finalizar ou arquivar a conversa, não enviam um novo aviso. O destinatário é definido por `CONVERSATION_ALERT_EMAIL` (padrão: `refugiodocuscuzeiro@gmail.com`), e falhas temporárias usam as retentativas da fila sem bloquear a conversa.
6. Mídias criam um segundo job e são baixadas para `storage/conversas`, nunca para uma URL pública.
7. Status `sent`, `delivered`, `read` e `failed` atualizam a mesma mensagem sem regredir o status.

Tipos preservados: texto, imagem, documento, áudio, vídeo, localização, contato, botão, interativa, template, sticker e desconhecida. O payload original da mensagem é mantido enquanto a política de retenção permitir; a interface nunca reduz tudo a texto.

## Worker e retenção

```cron
* * * * * /usr/bin/php /srv/refugio/scripts/process_jobs.php --limit=100 >> /var/log/refugio-jobs.log 2>&1
20 3 * * * /usr/bin/php /srv/refugio/scripts/aplicar_retencao.php >> /var/log/refugio-retencao.log 2>&1
```

O worker faz retry com backoff e máximo de tentativas. A retenção remove somente o arquivo de mídia antigo e payloads/jobs/auditoria conforme os prazos de `.env`; o registro da mensagem permanece. Ajuste os prazos à base legal e política de privacidade antes de ativar o cron.

## Inbox

`/admin/conversas` oferece busca, status, atendente, prioridade, tags, não lidas, histórico, anexos protegidos, notas internas, vínculo com cliente/reserva e criação de solicitação de reserva. O centro é atualizado por polling autenticado a cada 5 segundos, sem recarregar a página.

Somente perfis com `conversas.view` veem conteúdo. `conversas.reply` envia; `conversas.manage` atribui, etiqueta, vincula e escreve notas.

## Regra de 24 horas e templates

Cada mensagem recebida define `janela_atendimento_ate = recebida_em + 24h`.

- Dentro da janela: texto livre e mídia.
- Fora da janela: somente template aprovado.
- Templates vêm da WABA por “Sincronizar templates” e são guardados localmente.
- Falhas de texto/template podem ser reenviadas; texto é novamente bloqueado se a janela tiver expirado.

O serviço usa o endpoint oficial `/{PHONE_NUMBER_ID}/messages`; tokens ficam apenas no servidor.

## Leads e atribuição

Telefone normalizado é único por canal. Um contato desconhecido cria lead, não múltiplos clientes. O botão público `/contato/whatsapp` inclui uma referência opaca na mensagem inicial; o webhook recupera o lead e preserva UTMs de primeiro/último contato. Mensagens privadas não são transformadas em segmentação de marketing.

## Teste

1. Confirme o GET do webhook no painel Meta.
2. Envie texto, imagem, PDF, áudio, vídeo, localização e botão para o número.
3. Rode `php scripts/process_jobs.php --limit=100` e confira inbox/mídias.
4. Reenvie o mesmo payload assinado: não deve duplicar mensagem.
5. Responda dentro da janela e confira `ENVIADA/ENTREGUE/LIDA`.
6. Simule janela expirada; texto deve falhar localmente e template aprovado deve funcionar.
7. Teste atribuição, tags, atendente, vínculo e criação de reserva.
8. Uma assinatura falsa deve responder `401` e não gravar evento.

Erros operacionais ficam em `jobs.erro_ultimo`, `whatsapp_webhook_eventos.erro`, `mensagens.erro` e no log PHP sem access token.

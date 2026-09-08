# Resumo mensal de reservas por WhatsApp

O template `resumo_reservas_mensal` e de categoria `UTILITY`, idioma `pt_BR`, e recebe quatro parametros: mes, data/hora da atualizacao, lista resumida e total. A lista inclui:

- reservas diretas com status `RESERVA_CONFIRMADA` ou `FINALIZADA` que cruzam o mes;
- eventos iCal confirmados de fontes ativas configuradas como `AIRBNB` ou `BOOKING` que cruzam o mes.

O conteudo enviado evita nomes de hospedes e usa apenas datas, origem e o codigo interno das reservas diretas.

## Ativacao em producao

Adicione ao `.env`:

```dotenv
WHATSAPP_MONTHLY_SUMMARY_ENABLED=true
WHATSAPP_MONTHLY_SUMMARY_TEMPLATE=resumo_reservas_mensal
WHATSAPP_MONTHLY_SUMMARY_RECIPIENTS=5519999725599,5519999925015
```

O token usado para criar o template precisa permitir administracao da conta do WhatsApp Business. Submeta uma unica vez:

```bash
php scripts/configure_whatsapp_monthly_summary_template.php
```

Consulte novamente com o mesmo comando ate o retorno indicar `APPROVED`. O comando e idempotente: se nome e idioma ja existirem, apenas informa o estado atual.

Agende a segunda-feira, por exemplo as 08:00 no fuso da aplicacao:

```cron
0 8 * * 1 /usr/bin/php /var/www/refugio/scripts/schedule_monthly_reservation_summary.php >> /var/log/refugio-resumo-reservas.log 2>&1
```

O `scripts/process_jobs.php` existente deve continuar rodando a cada poucos minutos. O cron semanal apenas agenda um job para cada telefone; o worker envia o template. Para homologar fora de uma segunda-feira, use `php scripts/schedule_monthly_reservation_summary.php --force`.

Confirmacoes de reservas diretas agendam o resumo do mes do check-in. Uma nova reserva confirmada encontrada em Airbnb/Booking durante o sync iCal faz o mesmo. Chaves unicas na fila impedem duplicacao em retries; numa primeira importacao com varias reservas, os eventos sao consolidados em um envio por mes e destinatario.

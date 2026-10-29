# Resumo mensal de reservas por WhatsApp

O template `resumo_reservas_mensal_v2` e de categoria `UTILITY`, idioma `pt_BR`, recebe seis parâmetros: mês, atualização, total de períodos, reservas diretas, Airbnb e Booking. As quebras de linha pertencem ao texto fixo do template para preservar a leitura no WhatsApp. A lista inclui:

- reservas diretas com status `RESERVA_CONFIRMADA` ou `FINALIZADA` que cruzam o mes;
- eventos iCal confirmados de fontes ativas configuradas como `AIRBNB` ou `BOOKING` que cruzam o mes.

O conteúdo usa apenas origem e datas de entrada e saída, sem código interno ou nome do hóspede. Eventos externos representam períodos ocupados do calendário; o iCal pode incluir bloqueios e não comprova pagamentos.

## Ativacao em producao

Adicione ao `.env`:

```dotenv
WHATSAPP_MONTHLY_SUMMARY_ENABLED=true
WHATSAPP_MONTHLY_SUMMARY_TEMPLATE=resumo_reservas_mensal_v2
WHATSAPP_MONTHLY_SUMMARY_RECIPIENTS=5519996873957,5519997815267
```

O token usado para criar o template precisa permitir administracao da conta do WhatsApp Business. Submeta uma unica vez:

```bash
php scripts/configure_whatsapp_monthly_summary_template.php
```

Consulte novamente com o mesmo comando ate o retorno indicar `APPROVED`. O comando e idempotente: se nome e idioma ja existirem, apenas informa o estado atual.

Agende diariamente, por exemplo as 08:00 no fuso da aplicacao:

```cron
0 8 * * * /usr/bin/php /var/www/refugio/scripts/schedule_monthly_reservation_summary.php >> /var/log/refugio-resumo-reservas.log 2>&1
```

O `scripts/process_jobs.php` existente deve continuar rodando a cada poucos minutos. O cron diario apenas agenda um job para cada telefone; o worker envia o template. A chave por data e destinatario impede duplicacao se a rotina executar novamente no mesmo dia. Para homologar, use `php scripts/schedule_monthly_reservation_summary.php`.

Usuarios com a permissao `reservas.manage` tambem podem usar o botao **Enviar resumo do mes** no cabecalho da secao administrativa **Reservas**. O botao agenda o mesmo resumo do mes corrente para todos os destinatarios configurados. Cada formulario recebe uma chave unica para impedir duplicacao causada por duplo clique ou reenvio do navegador.

Confirmações de reservas diretas agendam o resumo do mês corrente. Uma nova reserva confirmada encontrada em Airbnb/Booking durante o sync iCal faz o mesmo. Chaves unicas na fila impedem duplicacao em retries; numa primeira importacao com varias reservas, os eventos sao consolidados em um envio por mes e destinatario.


## Migração e verificação

Atualize explicitamente as duas variáveis no `.env` do servidor: o arquivo `.env.example` não altera uma instalação existente. Os destinatários são (19) 99687-3957 e (19) 99781-5267. Não mantenha os destinatários antigos se o envio deve ocorrer apenas para esses dois números.

Crie `resumo_reservas_mensal_v2` e aguarde o estado APPROVED. Até lá, a configuração antiga `resumo_reservas_mensal` continua compatível com dois parâmetros, já sem códigos de reserva. Use o nome v2 após a aprovação para obter o novo layout.

Execute `php scripts/check_reservation_summary.php` para consultar a configuração efetiva e os jobs recentes sem enviar mensagens. Jobs CONCLUIDO indicam que o worker terminou a chamada de envio; a entrega real deve ser conferida no provedor WhatsApp. Este checkout não contém `.env` de produção e não permite comprovar entregas no servidor.

Exemplo do novo layout:

```text
📅 Reservas de outubro de 2026
Atualizado: 29/10/2026 08:00
Total de períodos: 2

🏡 Reservas diretas
1) Entrada 10/10 → saída 12/10

🌿 Airbnb — calendário
1) Entrada 15/10 → saída 18/10

🧳 Booking — calendário
Nenhuma neste mês.

Entrada = chegada • Saída = fim da hospedagem.
```

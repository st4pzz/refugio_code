# Calendário unificado

O calendário combina reservas internas, `datas_bloqueadas`, eventos iCal externos e `calendar_holds`. A regra de sobreposição é sempre intervalo semiaberto: `início_existente < novo_fim AND fim_existente > novo_início`; assim, checkout e check-in no mesmo dia não conflitam.

## Tabelas e segurança

- `calendar_sources`: URL, provedor, timezone, ETag, frequência e saúde da fonte.
- `calendar_external_events`: `source_id + external_uid` único, checksum, alteração, cancelamento e remoção lógica.
- `calendar_holds`: retenções expirantes para cotação, pagamento ou uso manual.
- `calendar_sync_logs`: resultado e contadores de cada tentativa.
- `calendar_export_tokens`: hash de token revogável para feeds privados.

A aprovação de reserva e a criação de hold usam o mutex `reserva_mutex`, transação e nova consulta com lock. A importação recusa URLs com credenciais, hosts privados/reservados, respostas acima de 5 MB e redirects. O feed exportado contém somente ocupação; nunca nome, contato, documento ou valor.

## Operação

Cadastre e sincronize fontes em `/admin/calendario`. Ao cadastrar, a primeira importação é executada imediatamente. O botão **Sincronizar agora** repete a importação no próprio pedido e sempre registra sucesso ou falha em `calendar_sync_logs`. O painel mostra a quantidade e o período dos bloqueios ativos gravados por fonte.

O worker também procura e enfileira fontes vencidas antes de consumir a fila. Portanto, `process_jobs.php` sozinho mantém a importação automática; o cron dedicado `sync_calendars.php` pode ser mantido para reduzir a latência.

Crons recomendados:

```text
*/5 * * * * php scripts/sync_calendars.php
* * * * * php scripts/process_jobs.php --limit=100
*/5 * * * * php scripts/expire_calendar_holds.php
```

Mudanças e eventos cancelados são processados por upsert. Falhas ficam no log e entram no retry exponencial da fila. Revogue o token de exportação diretamente no painel/banco se o link for exposto. O parser cobre `UID`, `DTSTART`, `DTEND`, `DURATION` simples, `SUMMARY`, `STATUS`, `SEQUENCE`, linhas dobradas, dia inteiro e timezone.

Para diagnosticar a hospedagem via SSH, execute os dois comandos na raiz e depois atualize o painel:

```bash
php scripts/sync_calendars.php
php scripts/process_jobs.php --limit=100
```

O importador aceita até três redirecionamentos HTTP, validando cada destino contra redes privadas/reservadas.

## Limitações

Recorrência iCal (`RRULE`) não é expandida; provedores de reserva normalmente publicam cada ocupação como evento individual. A fonte externa bloqueia disponibilidade, mas não cria reserva interna nem cliente automaticamente.

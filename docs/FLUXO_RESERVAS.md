# Fluxo de reservas diretas

Uma solicitacao nunca e apresentada como reserva confirmada antes da verificacao manual do pagamento.

## Estados e transicoes

```text
AGUARDANDO_APROVACAO
  -> AGUARDANDO_PAGAMENTO
  -> RECUSADA
  -> CANCELADA

AGUARDANDO_PAGAMENTO
  -> COMPROVANTE_ENVIADO
  -> PAGAMENTO_CONFIRMADO
  -> EXPIRADA
  -> CANCELADA

COMPROVANTE_ENVIADO
  -> AGUARDANDO_PAGAMENTO (comprovante recusado)
  -> PAGAMENTO_CONFIRMADO
  -> EXPIRADA (se KEEP_RECEIPT_AFTER_EXPIRY=false)
  -> CANCELADA

PAGAMENTO_CONFIRMADO
  -> RESERVA_CONFIRMADA
  -> CANCELADA

RESERVA_CONFIRMADA
  -> CANCELADA
  -> FINALIZADA
```

`RECUSADA`, `EXPIRADA`, `CANCELADA` e `FINALIZADA` sao terminais. As regras ficam centralizadas em `app/Models/ReservationStatus.php`.

## Bloqueio de datas

Os estados `AGUARDANDO_PAGAMENTO`, `COMPROVANTE_ENVIADO`, `PAGAMENTO_CONFIRMADO` e `RESERVA_CONFIRMADA` bloqueiam datas. `AGUARDANDO_APROVACAO` aparece como conflito potencial, mas nao bloqueia.

A sobreposicao usa `checkin_existente < novo_checkout AND checkout_existente > novo_checkin`. A aprovacao abre uma transacao, bloqueia a linha unica de `reserva_mutex` com `FOR UPDATE`, verifica reservas e bloqueios novamente, cria a cobranca e o bloqueio e somente entao confirma a transacao. Assim, duas aprovacoes concorrentes nao passam juntas.

## Pagamentos

Cada reserva aceita varios pagamentos. O sinal pode confirmar a reserva enquanto o saldo continua pendente. Um comprovante somente muda o estado para analise; apenas o botao administrativo **Confirmar pagamento** confirma o valor. QR Code e Pix Copia e Cola sao cadastrados manualmente, sem API bancaria.

## Expiracao

O cron seleciona apenas pagamentos ainda `PENDENTE` e reservas em estado elegivel, bloqueia a reserva em transacao, marca a reserva e cobrancas como expiradas, remove o bloqueio e registra o historico. A repeticao nao reprocessa registros ja expirados.

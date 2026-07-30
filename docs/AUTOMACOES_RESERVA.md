# Automações da reserva

`ReservationAutomationService` recebe eventos de domínio e cria `automation_runs` idempotentes. Cada execução referencia uma das 17 regras iniciais e um job na fila existente. Não existe segunda fila.

A aprovação administrativa é uma exceção de baixa latência: o e-mail com a cobrança é enviado imediatamente depois do commit da reserva. O painel só confirma “e-mail enviado” quando o SMTP realmente aceita a mensagem. Se o SMTP falhar, a reserva e a cobrança permanecem salvas, a falha aparece em `notificacoes`, uma tentativa de cobrança entra na fila e o administrador pode usar **Reenviar e-mail**. O lembrete anterior ao vencimento continua sendo agendado pela regra `PAYMENT_REMINDER`.

Jornadas incluídas: solicitação, aprovação/orçamento, Pix, lembrete de vencimento, pagamento, contrato, lembrete de assinatura, pré-check-in, lembrete, confirmação, check-in em três dias, check-in em um dia, dia do check-in, véspera do checkout, checkout, agradecimento e avaliação. Todos os offsets ficam em `automation_rules`.

Execute a cada minuto:

```text
php scripts/process_jobs.php --limit=100
```

E diariamente/periodicamente:

```text
php scripts/schedule_reservation_automations.php
```

E-mail reutiliza SMTP e grava `notificacoes`. WhatsApp reutiliza a Cloud API e somente envia quando há consentimento e template configurado. Mensagem livre fora da janela não é usada. Configure os doze `WHATSAPP_TEMPLATE_*` no namespace `communication`; código de assinatura usa `WHATSAPP_TEMPLATE_CONTRACT_SIGNATURE_CODE` no ambiente. Falhas de e-mail assíncrono entram no retry exponencial e nunca incluem access token nos logs.

Variáveis permitidas incluem primeiro nome, código, datas, quantidade, valor, prazo, links, horários e contato. CPF, credenciais, observações internas e dados de outro hóspede não entram. Links ficam redigidos no histórico.

# Automações da reserva

`ReservationAutomationService` recebe eventos de domínio e cria `automation_runs` idempotentes. Cada execução referencia uma das 17 regras iniciais e um job na fila existente. Não existe segunda fila.

Jornadas incluídas: solicitação, aprovação/orçamento, Pix, lembrete de vencimento, pagamento, contrato, lembrete de assinatura, pré-check-in, lembrete, confirmação, check-in em três dias, check-in em um dia, dia do check-in, véspera do checkout, checkout, agradecimento e avaliação. Todos os offsets ficam em `automation_rules`.

Execute a cada minuto:

```text
php scripts/process_jobs.php --limit=100
```

E diariamente/periodicamente:

```text
php scripts/schedule_reservation_automations.php
```

E-mail reutiliza SMTP e grava `notificacoes`. WhatsApp reutiliza a Cloud API e somente envia quando há consentimento e template configurado. Mensagem livre fora da janela não é usada. Configure os doze `WHATSAPP_TEMPLATE_*` no namespace `communication`; código de assinatura usa `WHATSAPP_TEMPLATE_CONTRACT_SIGNATURE_CODE` no ambiente. Falhas entram no retry exponencial e nunca incluem access token nos logs.

Variáveis permitidas incluem primeiro nome, código, datas, quantidade, valor, prazo, links, horários e contato. CPF, credenciais, observações internas e dados de outro hóspede não entram. Links ficam redigidos no histórico.

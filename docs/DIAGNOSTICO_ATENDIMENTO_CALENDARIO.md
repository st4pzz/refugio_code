# Atendimento automático e calendário

Execute na raiz da aplicação, com o mesmo PHP e usuário usados pelo cron:

```sh
php scripts/diagnose_booking_automation.php
```

Esse diagnóstico é somente leitura: não envia mensagens, não chama a OpenAI e não exibe chaves, telefones, mensagens ou URLs privadas de iCal. Mostra configuração, tabelas, estados da fila e sincronizações. Ele não comprova que o modelo configurado esteja disponível para a conta OpenAI; erros da API devem ser conferidos nos logs privados dos jobs.

Verifique especialmente:

- Migrações 015 (rascunhos), 018 (respostas automáticas) e 019 (cobrança Booking). Publique os arquivos e execute `php scripts/migrate.php`, seguindo o procedimento de backup do projeto. A falta de 018 impede o envio automático; a falta de 019 agora desabilita apenas a seção de cobrança no calendário, com aviso visível.
- `OPENAI_CONVERSATIONS_ENABLED=true`, `OPENAI_CONVERSATIONS_AUTO_REPLY=true` e chave disponível no ambiente do worker. Chave ausente passa a registrar falha, em vez de concluir o job sem responder.
- Cron `* * * * * php /CAMINHO/DO/SITE/scripts/process_jobs.php --limit=100`. Esse comando processa jobs reais, inclusive mensagens; não é um teste. `sync_calendars.php` sozinho apenas enfileira sincronizações.
- Jobs `CONVERSATION_AI_REPLY` pendentes por vários minutos indicam atraso do worker. Jobs com falha exigem consultar `erro_ultimo` nos logs/banco privados. Não reinicie em massa jobs de envio: timeouts podem ter enviado a mensagem mesmo sem confirmação local.
- Conversas `AGUARDANDO_EQUIPE` continuam pausadas para preservar a intervenção humana. Revise individualmente antes de mudar para `EM_ATENDIMENTO`. A mudança não reenvia mensagens antigas automaticamente; novos textos recebidos dentro da janela do WhatsApp geram novos jobs.

Calendário desatualizado agora é informado à IA como `temporarily_unknown`, sem solicitar intervenção humana apenas por esse motivo. Bloqueios externos continuam significando indisponibilidade. A IA não pode confirmar reserva nem pagamento.

Se `/admin/calendario` continuar exibindo a página genérica de falha, consulte o erro correspondente no log PHP da hospedagem. O tratamento de tabela ausente é restrito às tabelas opcionais `booking_billing_contacts` e `booking_billing_deliveries`; erros no calendário principal não são ocultados como uma agenda vazia.

Validação após publicação: abrir a agenda, conferir fontes e cobranças, enviar uma nova mensagem de teste para uma conversa elegível e verificar o job, a resposta recebida e a ausência de duplicidade. Nenhuma mensagem de teste foi enviada pelo diagnóstico.

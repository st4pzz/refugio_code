# Cobranças de reservas Booking

Em **Calendário → Booking · contatos e cobranças**, selecione o mês e abra a reserva importada. Cadastre nome e telefone, e-mail ou ambos. É possível salvar apenas o contato; salvar não envia mensagem.

Para cobrar, preencha o valor ainda devido e o vencimento, selecione WhatsApp e/ou e-mail, confira a prévia e confirme que a plataforma indica pagamento diretamente ao anfitrião. Clique em **Salvar e enviar cobrança**. O valor não é calculado pelas tarifas do site: deve refletir o acordo da reserva Booking. Não há criação de uma segunda reserva nem alteração do bloqueio externo.

O iCal contém também bloqueios sem hóspede. Não envie cobranças para esses eventos. A importação de calendário não identifica automaticamente o responsável pelo pagamento. Cancelamentos, exclusões e fontes desativadas impedem o envio. Mudanças nos contatos, valor, vencimento, datas ou situação de pagamento depois do agendamento invalidam a mensagem pendente; revise e envie novamente.

## Mensagem

O modelo mostra nome, entrada, saída, valor desta cobrança, vencimento, chave aleatória 6f3673aa-73f2-49ba-a956-e395535607e2, Sergio Ramella e Banco Inter. Solicita o comprovante e orienta a não pagar novamente caso já tenha pago. WhatsApp e e-mail usam o mesmo texto. Não há verificação bancária automática; após conferir o pagamento, marque **Pagamento já conferido — não cobrar** e salve. Essa marcação controla esta seção; não lança uma receita no módulo financeiro.

## Ativação

1. Execute `php scripts/migrate.php` (migration 019).
2. Configure `WHATSAPP_BOOKING_BILLING_TEMPLATE=booking_cobranca_pix_v1` no `.env`.
3. Com as credenciais WhatsApp configuradas, execute `php scripts/configure_whatsapp_booking_billing_template.php` e aguarde `APPROVED`. O comando cria o template UTILITY com cinco parâmetros. Não envia cobranças a hóspedes.
4. Para e-mail, configure SMTP_HOST, SMTP_PORT, SMTP_ENCRYPTION, SMTP_USERNAME, SMTP_PASSWORD e SMTP_FROM_EMAIL. Para WhatsApp, use as configurações existentes da Cloud API.
5. Mantenha `php scripts/process_jobs.php` em execução pelo cron. Cada canal selecionado tem um job independente.

## Histórico e falhas

O painel registra destinatário, canal, horário e resultado. “Enviado ao provedor” significa que a API ou servidor SMTP aceitou o envio, não comprova leitura nem pagamento. Duplo clique no mesmo formulário não cria dois envios por canal. Uma nova submissão após recarregar a página é um novo pedido explícito de envio.

Falhas não são reenviadas automaticamente, pois um timeout pode ocorrer depois de o provedor ter aceitado a mensagem. Confira o provedor antes de reenviar. Um registro “Envio iniciado” que não termina pode indicar interrupção do worker e também exige conferência. As últimas seis tentativas por evento aparecem no painel; os registros anteriores permanecem no banco.

A seção exige `calendar.view` e `reservas.manage`; gravações e envios exigem sessão administrativa e CSRF. Os contatos são mantidos separadamente dos eventos iCal, preservados nas sincronizações do mesmo UID.

## Homologação

Teste contato só com telefone, só com e-mail e com ambos; cobrança paga; evento cancelado; alteração de datas antes do worker; duplo clique; erro em um canal; modelo ainda não aprovado e timeout do provedor. Não use dados de hóspedes reais para esses testes.

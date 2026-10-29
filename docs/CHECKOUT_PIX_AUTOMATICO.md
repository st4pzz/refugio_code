# Checkout Pix e atendimento automático

O site cria a reserva diretamente em AGUARDANDO_PAGAMENTO. Na mesma transação, trava reserva_mutex, verifica reservas, bloqueios manuais, eventos externos e holds, recalcula o preço com o motor oficial, cria uma cobrança integral e bloqueia as datas. Um período ocupado não gera cobrança. O importador iCal usa o mesmo mutex para serializar importações e reservas locais.

O modal mostra a chave CPF 74373137820, Sergio Ramella, Banco Safra, o valor e o prazo. É uma transferência manual por chave, não um código BR Code nem uma integração com o Banco Central. O envio do comprovante não confirma pagamento; a conferência continua administrativa. PIX_PAYMENT_MINUTES controla o prazo (5–120 minutos; padrão 30). Comprovante enviado dentro do prazo mantém o bloqueio quando KEEP_RECEIPT_AFTER_EXPIRY=true.

## Ativação no servidor

1. Faça backup do banco e execute `php scripts/migrate.php` para aplicar a migration 018.
2. Mantenha os preços públicos configurados e habilitados (`public_pricing_enabled`). Sem preço válido o checkout não cria cobrança.
3. Configure OPENAI_API_KEY e mantenha OPENAI_CONVERSATIONS_ENABLED=true e OPENAI_CONVERSATIONS_AUTO_REPLY=true.
4. Execute `php scripts/process_jobs.php` pelo menos a cada minuto (ou por worker contínuo). Ele agenda sincronizações iCal e processa as respostas. Mantenha também `php scripts/expirar_reservas.php` a cada minuto para liberar datas não pagas.
5. Confira as fontes Booking/Airbnb ativas e sincronizadas. Configure intervalos de até 120 minutos. Fontes com erro, nunca sincronizadas, com sincronização vencida ou com mais de duas horas impedem nova cobrança. iCal não elimina o atraso de publicação de eventos pelos canais externos.
6. Valide em homologação com credenciais de teste antes de publicar: datas ocupadas, datas livres, duas solicitações concorrentes, reenvio de formulário, expiração, comprovante, falha de envio WhatsApp e pausa humana.

## Conversas

Mensagens de texto recebidas geram jobs únicos por mensagem. A resposta usa o histórico mais recente e as ferramentas oficiais de disponibilidade e preço. Antes do envio, a conversa é relida: mensagem nova ou resposta da equipe invalida a resposta antiga. Mensagens de mídia exigem atendimento humano.

AGUARDANDO_EQUIPE, CONVERTIDA, FINALIZADA, ARQUIVADA e SPAM pausam as respostas automáticas. Para assumir, selecione AGUARDANDO_EQUIPE. Para retomar, selecione EM_ATENDIMENTO; a próxima mensagem de texto recebida será atendida pela IA. Exceções e pedidos de atendente vão para AGUARDANDO_EQUIPE com uma nota contendo motivo e sugestão. A janela de 24 horas do WhatsApp continua válida.

A tabela conversation_auto_replies registra tentativas. Se houver falha ambígua após iniciar um envio, não há reenvio automático: conferir no provedor antes de responder manualmente. Registros SENDING antigos indicam worker interrompido e também exigem conferência. OPENAI_CONVERSATIONS_AUTO_REPLY=false desliga o envio automático globalmente, preservando sugestões manuais.

## Verificação local

`php tests/run.php` executa a suíte. É necessário PHP 8.2 ou superior com as extensões usadas no projeto. Testes com MySQL e integrações reais exigem um ambiente configurado; não devem ser executados contra produção.


## Formulário em etapas

A página `/reserva/solicitar` inicia somente com check-in, check-out e total de hóspedes (adultos e crianças). “Consultar disponibilidade” faz uma chamada ao endpoint público de orçamento, com CSRF e limitação de requisições. O servidor valida datas, conflitos e atualização dos calendários antes de calcular o preço.

Durante a consulta há um indicador de carregamento. Apenas uma resposta válida libera a segunda etapa com resumo, valor, nome, e-mail, WhatsApp e consentimentos. CPF aparece somente se for obrigatório pela configuração. Crianças, pets e observações ficam em uma área expansível; crianças já pertencem ao total informado, que deve incluir ao menos um adulto. Alterar pets recalcula o valor antes de permitir pagamento.

Alterar datas ou hóspedes invalida a consulta anterior. Respostas antigas são descartadas, falhas e timeout permitem tentar novamente, e os dados preenchidos são preservados ao voltar. A consulta não bloqueia as datas: a criação da reserva revalida tudo no servidor e somente então libera o Pix. Sem JavaScript, a página orienta o contato pelo WhatsApp.

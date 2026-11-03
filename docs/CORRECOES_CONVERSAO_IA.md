# Atendimento e fechamento de reservas

As alterações usam as tabelas existentes das migrations 015 e 018. Não há nova migration.

- Segunda consulta com falha de sincronização, sem consulta bem-sucedida entre elas: pausa automática, prioridade alta, nota com parâmetros consultados e mensagens do hóspede, e uma confirmação de encaminhamento. Novas mensagens permanecem na mesma conversa e não reativam a IA.
- Respostas humanas por texto, template, mídia e documento atribuem o atendente e pausam a IA. O envio humano e o worker compartilham um lock por conversa; um envio em andamento termina antes da troca de controle.
- Para retomar, salvar Não atribuído e EM_ATENDIMENTO. A IA responderá à próxima mensagem do hóspede; mensagens antigas não são reenviadas.
- Orçamentos retornam links com checkin, checkout, hospedes e pets. O formulário valida novamente os dados, preços e disponibilidade; o link não reserva nem fixa valores.
- Períodos ocupados consultam até três alternativas próximas, com a mesma duração, hóspedes e pets. Calendários desatualizados não autorizam alternativas.

## Validação antes de publicar

Executar `php tests/run.php` e lint dos PHP alterados em ambiente com PHP. Validar em homologação: duas falhas sucessivas; falha seguida de sucesso; resposta humana seguida de nova mensagem; concorrência humano/worker; orçamento disponível; alternativas ocupadas; link preenchido e alteração de disponibilidade antes do checkout. Não testar enviando mensagens a leads reais.

Neste ambiente só foi possível validar a sintaxe com tree-sitter e executar git diff --check; PHP CLI não está instalado. As alterações ainda não foram publicadas na Hostinger.

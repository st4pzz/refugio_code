# Sistema de avaliações verificadas

## Visão geral

O sistema vincula cada avaliação a uma reserva existente, sem login do hóspede. Depois do check-out, um convite exclusivo é enviado por SMTP e, quando autorizado/configurado, pela WhatsApp Cloud API. A avaliação nasce como `PENDENTE`; somente `APROVADA` com consentimento aparece na API e no carrossel público.

Fluxo:

1. a reserva é concluída e passa pela regra central de elegibilidade;
2. o cron ou um administrador cria um token aleatório de 256 bits;
3. somente o SHA-256 do token é persistido em `convites_avaliacao`;
4. o link `/avaliar/{token}` é enviado ao hóspede;
5. o formulário valida novamente convite, reserva, conteúdo e consentimento;
6. a transação cria uma única avaliação e torna o convite `UTILIZADO`;
7. o administrador aprova, rejeita, oculta, republica ou responde;
8. a API pública entrega exclusivamente avaliações publicáveis.

## Banco de dados

A migration `002_create_avaliacoes.sql` cria:

- `convites_avaliacao`: uma linha por reserva, hash único, expiração, revogação, uso, entregas e lembrete;
- `avaliacoes`: uma linha por reserva e convite, seis notas, comentário original, consentimento, anonimato, resposta e dados de moderação.

As restrições `UNIQUE(reserva_id)` e `UNIQUE(convite_avaliacao_id)` impedem duplicidade mesmo sob concorrência. Todas as chaves estrangeiras usam InnoDB. O comentário original não é atualizado pelo fluxo de moderação.

Estados do convite:

- `PENDENTE`: gerado, mas nenhum canal confirmou entrega;
- `ENVIADO`: e-mail ou WhatsApp enviado;
- `UTILIZADO`: avaliação recebida;
- `EXPIRADO`: prazo encerrado;
- `REVOGADO`: invalidado pelo administrador.

Estados da avaliação:

- `PENDENTE`: aguardando moderação;
- `APROVADA`: disponível publicamente;
- `REJEITADA`: mantida apenas no painel;
- `OCULTA`: foi publicada e depois retirada da vitrine.

Transições aceitas: `PENDENTE → APROVADA/REJEITADA`, `APROVADA → OCULTA` e `REJEITADA/OCULTA → APROVADA`.

## Elegibilidade

`ReviewEligibilityService` concentra a regra. Uma reserva precisa existir, estar `FINALIZADA` ou `RESERVA_CONFIRMADA` com check-out passado, não possuir avaliação e ter pagamento confirmado. Reservas `AIRBNB`, `BOOKING` ou `MANUAL` podem dispensar pagamento local somente quando o administrador as marcou como `FINALIZADA`; isso representa a validação manual da hospedagem externa.

O convite só funciona durante a janela configurada. A abertura padrão é 24 horas após o check-out e a expiração padrão é 90 dias após ele.

## Segurança e privacidade

- token criado com `bin2hex(random_bytes(32))` e validado em formato fixo;
- banco armazena apenas `hash('sha256', $token)`; reenvio rotaciona o link anterior;
- o conteúdo persistido em `notificacoes` mascara o token, embora o SMTP/WhatsApp receba o link original;
- token não contém ID, código, CPF, telefone ou timestamp;
- páginas com token usam `no-store`, `noindex` e `Referrer-Policy: no-referrer`;
- submissão usa CSRF, rate limit, honeypot, prepared statements e transação com `FOR UPDATE`;
- notas são inteiros de 1 a 5 e o comentário é limitado a 10–2.000 caracteres;
- tags HTML, controles e caracteres invisíveis abusivos são removidos;
- e-mail, telefone, CPF, valores, datas completas e códigos internos não saem na API pública;
- a saída HTML é escapada e o carrossel cria nós com `textContent`;
- mensagens públicas para token inválido, expirado ou revogado não identificam a reserva.

## Endpoints

Públicos:

- `GET /avaliar/{token}`: formulário;
- `POST /api/avaliacoes/{token}`: submissão;
- `GET /api/avaliacoes/publicas`: avaliações aprovadas, contagem e média.

Administrativos, todos autenticados e com CSRF nas mutações:

- `GET /admin/avaliacoes` e `GET /admin/avaliacoes/{id}`;
- `POST /admin/avaliacoes/{id}/{aprovar|rejeitar|ocultar|republicar|responder}`;
- `POST /admin/reservas/{id}/{enviar-convite-avaliacao|reenviar-convite-avaliacao|revogar-convite-avaliacao}`.

## Convites, cron e lembrete

Execute o processador pelo menos uma vez por hora:

```cron
15 * * * * /usr/bin/php /caminho/do/projeto/scripts/enviar_convites_avaliacao.php >> /caminho/seguro/cron-avaliacoes.log 2>&1
```

O script:

- expira convites vencidos;
- seleciona reservas após a tolerância de envio;
- ignora avaliações existentes e convites válidos já enviados;
- mantém uma única linha de convite por reserva;
- tenta novamente convites `PENDENTE` quando nenhum canal entregou;
- envia no máximo um lembrete, após o prazo configurado;
- registra eventos no histórico e resultados em `notificacoes`.

Um convite expirado ou revogado não é recriado automaticamente. O administrador pode gerar um novo link conscientemente; como o token original nunca é armazenado, qualquer reenvio rotaciona e invalida o link anterior. Depois da geração manual, o painel exibe o endereço completo uma única vez para cópia. O feedback diferencia entrega confirmada por e-mail/WhatsApp de convite criado sem entrega, permitindo o envio manual sem afirmar um sucesso inexistente.

## E-mail e WhatsApp

O sistema reutiliza `SmtpClient`, `WhatsAppService` e `NotificationService`. Configure SMTP nas variáveis já existentes e cadastre dois templates iniciados pela empresa em `pt_BR`:

- `WHATSAPP_TEMPLATE_CONVITE_AVALIACAO`;
- `WHATSAPP_TEMPLATE_LEMBRETE_AVALIACAO`.

Parâmetros, nesta ordem: primeiro nome, `Refugio do Cuscuzeiro`, link exclusivo e data de expiração. Falhas ficam em `notificacoes` como `FALHOU`; credenciais não entram no conteúdo ou no histórico.

## Formulário e moderação

O hóspede escolhe primeiro nome, nome abreviado, nome completo ou anônimo. O padrão é nome abreviado. As seis notas, comentário e consentimento são obrigatórios. O JavaScript melhora teclado, contador e clique duplo, mas todas as regras são repetidas no backend.

No painel, a fila permite busca por hóspede/código e filtros por status, nota, origem e período. Rejeitar e ocultar exigem motivo interno. A resposta pública é sanitizada e limitada a 1.000 caracteres. Todas as ações registram o administrador e o histórico; não há exclusão permanente na interface.

## Carrossel e média

Os depoimentos estáticos foram removidos de `index.php`. O JavaScript consulta `/api/avaliacoes/publicas`, preserva controles/animação e mostra estrelas, comentário, nome de exibição, mês/ano, selo verificado e resposta. A consulta usa:

```sql
status = 'APROVADA' AND autoriza_publicacao = 1
```

O mesmo filtro calcula `COUNT(*)` e `AVG(nota_geral)`. Sem itens, o carrossel e a média ficam ocultos e aparece uma mensagem institucional neutra.

## Teste manual

1. use uma reserva de teste paga, com check-out passado, e marque-a `FINALIZADA`;
2. abra a reserva no painel e envie o convite;
3. capture o link no e-mail/WhatsApp de teste e abra-o em celular e desktop;
4. envie notas e comentário, confirmando que um segundo envio é recusado;
5. em **Avaliações**, aprove o item e abra a home;
6. confirme item, selo, média e resposta; depois oculte e confirme a remoção;
7. rode `php scripts/enviar_convites_avaliacao.php` duas vezes e confira que não surge outro convite válido.

Testes automatizados:

```bash
php tests/run.php
php -l app/Services/ReviewService.php
node --check assets/js/reviews-carousel.js
```

## Implantação, backup e rollback

Antes da implantação:

```bash
mysqldump --single-transaction -u USUARIO -p BANCO > backup-antes-avaliacoes.sql
php scripts/migrate.php
php tests/run.php
```

Depois, configure cron, SMTP e templates, valide com destinatários de teste e só então habilite a rotina. O rollback de banco é manual e destrutivo: `002_drop_avaliacoes.sql` remove primeiro `avaliacoes` e depois `convites_avaliacao`. Faça backup e retire o cron antes de executá-lo. O token em texto puro não é recuperável do banco, por definição; para reenviar é necessário rotacioná-lo.

## Limitações e evolução

- não existe sincronização automática de conclusão com Airbnb/Booking; a validação externa é administrativa;
- entrega de WhatsApp depende de template aprovado e autorização de contato da reserva;
- o status de entrega posterior da Meta não é atualizado por webhook nesta etapa;
- cache adicional da API pode ser adotado se o volume crescer; hoje a consulta é indexada e limitada;
- destaque editorial não foi implementado para evitar ordenação artificial; a ordem padrão é aprovação mais recente.

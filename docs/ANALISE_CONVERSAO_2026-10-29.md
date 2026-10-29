# Análise de conversão — Refúgio do Cuscuzeiro

Análise em 29/10/2026. Base: página pública e formulário inspecionados no navegador, código local, fluxo de calendário, pagamento, atribuição e eventos. Não houve acesso ao GA4, contas de anúncios, métricas reais de abandono ou entregas bancárias. Prioridades são hipóteses fundamentadas em problemas observáveis, não promessas de aumento percentual.

## Diagnóstico principal

O imóvel tem material forte para vender: quatro suítes, piscina, hidromassagem, lazer e avaliações reais. A página funciona como apresentação extensa, mas poderia responder mais cedo às perguntas de compra: onde fica, quantas pessoas acomoda, quais datas estão livres, quanto custa e como é o pagamento.

No navegador, a página pública de reserva ainda mostrou adultos, crianças, pets, nome, CPF, e-mail, telefone, observações e consentimentos simultaneamente. O novo formulário em etapas está no workspace e precisa ser publicado e homologado. A home pública também combina explicação de aprovação manual com Pix direto; nesta alteração o texto local foi corrigido.

## Prioridades

| Ordem | Evidência observada | Implementação recomendada | Resultado a medir |
|---|---|---|---|
| 1 | Formulário completo ainda publicado | Publicar e testar datas → disponibilidade → contato → Pix | Consultas por sessão e reservas pagas por consulta |
| 2 | Instagram, WhatsApp e Airbnb chamam a mesma conversão Google Ads no código | Separar cliques, conversas qualificadas, cobrança criada e pagamento conferido; revisar objetivo de lance na conta | Custo por reserva paga e receita atribuída |
| 3 | A home não inicializa o capturador de atribuição; links simples perdem parâmetros da entrada | Preservar origem ao entrar no checkout; depois estender a navegação entre páginas e vincular pagamento à origem no servidor | Proporção de reservas com campanha identificada |
| 4 | Headline genérica de boas-vindas e capacidade pouco visível no topo | Texto específico: “Sua chácara de temporada em Analândia”, capacidade oficial, quatro suítes e lazer; CTA “Consultar datas e valores” | Cliques qualificados e consultas concluídas |
| 5 | Vídeo de fundo local com cerca de 13 MiB e autoplay | Versão curta/comprimida; imagem prioritária no celular ou conexão lenta; carregar vídeo depois | LCP real no percentil 75 e abandono por dispositivo |
| 6 | Consulta indisponível termina com erro, sem oferecer alternativas | Sugerir três períodos próximos realmente livres, preservando duração e hóspedes; permitir escolha de outro mês | Reservas após primeira consulta indisponível |
| 7 | Vários caminhos concorrentes: Instagram, Airbnb, WhatsApp, disponibilidade e reserva | Manter reserva direta como ação principal; WhatsApp como ajuda; deslocar redes sociais para menor destaque | Saídas para redes versus progresso no checkout |
| 8 | Cobrança Pix manual exige confiança e conferência | Resumo da estadia e política, identificação clara do anfitrião, valor integral/sinal conforme política definida, prazo de análise verdadeiro | Cobranças criadas → comprovantes → pagamentos confirmados |
| 9 | Avaliações carregam, mas resumo e carrossel podem representar conjuntos diferentes | Explicar origem da nota ou alinhar contagem; usar somente URLs públicas de avaliação | Interação com prova social e avanço ao formulário |
| 10 | Galeria longa com legendas genéricas | Seleção inicial de fotos: piscina/vista, conjunto do imóvel, suítes/camas, cozinha, lazer; galeria completa expansível | Uso da galeria e retorno ao CTA |

A prioridade 2 é particularmente relevante: o código registra uma conversão Google Ads de valor fixo 1,00 nos cliques externos. Não foi verificado se essa ação é primária na conta. Se for usada para lances, a campanha pode otimizar para o clique que você mede em vez de reserva paga. A migração deve ser planejada na conta, preservando medição comparável.

## Pop-up sugerido: implementado localmente

Um cartão compacto, sem escurecer nem bloquear a página, aparece após dois segundos na home `/` para tráfego com `utm_medium` de mídia paga ou identificador de anúncio Google/TikTok. Meta deve usar UTMs, por exemplo `utm_source=instagram&utm_medium=paid_social`: `fbclid` isolado também pode aparecer em links orgânicos e não é tratado como prova de anúncio.

Texto: “Viva o Refúgio do Cuscuzeiro. Não perca a chance de se hospedar no Refúgio. Consulte as datas e planeje sua próxima estadia.” Botão: **Reserve já**. Alternativa: **Quero conhecer a chácara primeiro**.

Regras implementadas:

- Uma exibição por sessão da aba; não reaparece ao recarregar depois de visto.
- Fechamento pelo botão, opção de continuar conhecendo ou Escape.
- Aguarda aviso de cookies, menu ou outro diálogo; não aparece durante digitação nem com a aba em segundo plano. Desiste após aproximadamente um minuto de impedimento.
- Não exige cadastro nem bloqueia a navegação; não anuncia desconto ou escassez inventada.
- Ao abrir, oculta temporariamente os botões sociais flutuantes para evitar sobreposição.
- Encaminha UTMs e identificadores presentes na URL aos links diretos de reserva da home, com limite de tamanho. Não resolve ainda visitas que passam por outras páginas antes do checkout.
- Eventos `reservation_prompt_view`, `reservation_prompt_click` e `reservation_prompt_dismiss` entram no dataLayer somente com consentimento de marketing já aceito. Não são eventos de compra. O GTM precisa ser configurado para recebê-los.
- Sem sessionStorage disponível, não exibe, evitando repetição incontrolável.

A hipótese é aumentar consultas de disponibilidade. O pop-up também pode afastar visitantes; maior taxa de clique não prova maior conversão em reservas. Recomendo comparação controlada entre sem pop-up e pop-up de dois segundos, com distribuição estável por visitante/campanha, sem alterar simultaneamente todos os demais elementos. Esse experimento ainda não foi implementado.

## Melhorias comerciais e de conteúdo

1. **Capacidade e camas claras:** exibir o limite oficial de hóspedes e composição dos quartos. Há depoimentos que mencionam mais de dez pessoas, enquanto o formulário informa dez. Depoimento não deve substituir regra da propriedade.
2. **Informações antes do Pix:** check-in/out, animais, roupa de cama, cozinha, estacionamento, política de cancelamento e proibição de festas em linguagem curta, com links para detalhes. Confirmar cada informação com a configuração vigente antes de publicar.
3. **Oferta coerente com anúncio:** separar campanha de fim de semana em família, lazer e descanso; cada destino de anúncio deve abrir com promessa correspondente. Não prometer menor preço que Booking/Airbnb sem comparar condições.
4. **Preço transparente:** total para o período e grupo, taxas discriminadas; preço médio por noite como informação secundária. “A partir de” somente se houver tarifa real disponível e condições visíveis.
5. **Recuperação de pagamento:** lembretes apenas para contatos fornecidos/autorizados, com link para a cobrança ainda válida; cessar após pagamento, cancelamento ou expiração. Não enviar lembretes genéricos de Pix vencido.
6. **Datas flexíveis:** aceitar “próximos fins de semana” como exploração e mostrar janelas livres. Um período lotado deve gerar alternativa útil, sem insistência.
7. **CTA no celular:** testar barra discreta “Consultar datas” e ajuda WhatsApp; não acumular barra, cookies, pop-up e três botões sociais sobre o conteúdo.
8. **Confiança nas avaliações:** foi observado um link de avaliação Booking que aponta para a extranet administrativa; substituir por URL pública sem parâmetros de sessão. Não expor links administrativos na vitrine. O resumo mostrou sete avaliações enquanto o carrossel apresentava mais itens; validar a base e explicar quando a nota se refere apenas a parte das fontes.

## Medição do funil

Acompanhar separadamente: entrada por campanha → consulta iniciada → disponível/indisponível/erro → contato enviado → cobrança criada → comprovante recebido → pagamento conferido → receita. Não confundir “copiar chave”, “já paguei” ou clique WhatsApp com pagamento.

Métrica principal: reservas pagas por sessão elegível, custo por reserva paga e receita por campanha. Secundárias: consulta concluída, disponibilidade encontrada, abandono por etapa, tempo de conferência, abertura e fechamento do pop-up. Separar celular/desktop, campanha e primeira visita/retorno. A escassez real de datas limita conversão mesmo quando a interface funciona bem.

Revisar deduplicação pelo identificador interno da reserva e momento da confirmação. Não mandar telefone, nome, e-mail, CPF ou token público de reserva em parâmetros livres de analytics. Campanhas podem precisar de evento intermediário qualificado quando o volume de pagamentos é baixo; decidir com dados, não pelo número bruto de cliques.

## Limites e validação

Não foram medidos Core Web Vitals reais nem determinada a taxa atual de conversão. O tamanho do vídeo é evidência de arquivo local, não medição de transferência completa ou tempo de carregamento do visitante. O navegador de inspeção encontrou uma verificação inicial de acesso; isso não prova que todos os visitantes a recebam. Testar o acesso real por anúncios no Chrome/Safari e navegadores internos Instagram/WhatsApp.

Nenhuma alteração foi publicada nesta análise. O pop-up e os textos corrigidos são mudanças locais. O checkout e as integrações devem ser testados em ambiente PHP/MySQL antes de publicar. O novo pop-up deve ser validado com e sem UTMs, consentimento pendente, fechamento, retorno e tamanho de tela reduzido.

Referências primárias:

- [Home inspecionada](https://www.refugiodocuscuzeiro.com.br/)
- [Google Ads: ações de conversão primárias e secundárias](https://support.google.com/google-ads/answer/11461796)
- [Google Search: diálogos e intersticiais](https://developers.google.com/search/docs/appearance/avoid-intrusive-interstitials)
- [web.dev: desempenho de vídeo](https://web.dev/learn/performance/video-performance)


Validação realizada nesta sessão: análise de sintaxe PHP/JavaScript sem erros; `git diff --check` sem problemas; prévia isolada do componente em navegador confirmou exibição em entrada `utm_medium=cpc`, encaminhamento das UTMs, fechamento e supressão após recarregar na mesma sessão. Entrada com `fbclid` isolado permaneceu sem pop-up. A prévia não executa o checkout PHP nem simula o banco e não substitui homologação no site completo.

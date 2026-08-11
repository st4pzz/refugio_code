<?php
declare(strict_types=1);

$home = ['label' => 'Home', 'path' => '/'];
$rent = ['label' => 'Alugar chácara', 'path' => '/alugar-chacara/'];

return [
    'alugar-chacara' => [
        'type' => 'landing', 'path' => '/alugar-chacara/',
        'title' => 'Chácara para Alugar em Analândia | Refúgio do Cuscuzeiro',
        'description' => 'Conheça uma chácara para alugar em Analândia, no interior de SP, com estrutura para descansar e aproveitar em família. Consulte disponibilidade.',
        'h1' => 'Chácara para alugar no interior de São Paulo',
        'intro' => 'O Refúgio do Cuscuzeiro reúne hospedagem, lazer e contato com a natureza em Analândia para viagens de família, amigos e fins de semana.',
        'image' => '/assets/images/seo/chacara-refugio-cuscuzeiro-analandia.webp', 'alt' => 'Área externa iluminada do Refúgio do Cuscuzeiro em Analândia',
        'breadcrumbs' => [$home, $rent],
        'sections' => [
            ['heading' => 'Uma chácara preparada para a sua estadia', 'paragraphs' => [
                'Alugar uma chácara é uma forma de reunir o grupo em um só espaço, organizar as refeições com liberdade e alternar momentos de descanso com atividades ao ar livre. No Refúgio do Cuscuzeiro, a hospedagem é proprietária deste site e fica em <a href="/alugar-chacara/analandia/">Analândia</a>, cidade do interior paulista conhecida pela paisagem natural.',
                'A estrutura apresentada no site inclui quatro suítes, piscina, hidromassagem, churrasqueira, varanda, salão de jogos, campo de futebol, quadra de areia e garagem coberta para quatro veículos. Antes da reserva, consulte a disponibilidade e confirme pelo atendimento as condições aplicáveis ao seu grupo.',
            ], 'list' => ['Espaços de convivência para o grupo permanecer junto;', 'Áreas de lazer para aproveitar também durante a hospedagem;', 'Base em Analândia para combinar descanso e passeios;', 'Solicitação de reserva direta, sujeita à confirmação de disponibilidade.']],
            ['heading' => 'Escolha a opção que combina com a sua viagem', 'paragraphs' => [
                'Quem já decidiu o destino pode consultar a página de <a href="/alugar-chacara/analandia/">chácara em Analândia</a>. Para uma viagem curta, veja a orientação de <a href="/alugar-chacara/final-de-semana/">fim de semana</a>. Há ainda conteúdos específicos para <a href="/alugar-chacara/para-familia/">viagem em família</a>, <a href="/alugar-chacara/interior-de-sp/">interior de São Paulo</a> e o hub para <a href="/chacara-perto-de/">planejar a saída pela sua cidade</a>.',
            ]],
            ['heading' => 'O que fazer antes de solicitar a reserva', 'paragraphs' => [
                'Defina as datas, o número de hóspedes e o perfil do grupo. Depois, confira as <a href="/galeria-de-fotos">fotos</a> e envie uma solicitação pelo fluxo de reserva direta. A equipe verifica as datas manualmente; o envio não representa confirmação automática.',
            ]],
        ],
        'faq' => [
            ['question' => 'Onde fica o Refúgio do Cuscuzeiro?', 'answer' => 'O Refúgio do Cuscuzeiro fica em Analândia, no interior do estado de São Paulo.'],
            ['question' => 'A chácara é indicada para famílias?', 'answer' => 'A estrutura reúne suítes e áreas de convivência e lazer. Consulte as regras e informe a composição do grupo ao solicitar as datas.'],
            ['question' => 'Como consultar disponibilidade?', 'answer' => 'Use a página de solicitação de reserva. As datas são verificadas manualmente e a reserva depende de confirmação.'],
            ['question' => 'Quais espaços aparecem no site?', 'answer' => 'O site apresenta quatro suítes, piscina, hidromassagem, churrasqueira, varanda, salão de jogos, campo de futebol, quadra de areia e garagem coberta.'],
        ],
        'cta_title' => 'Quer reunir seu grupo em Analândia?',
        'related' => ['onde-ficar-em-analandia', 'final-de-semana-em-analandia', 'chacara-ou-pousada-em-analandia'],
    ],
    'alugar-chacara/interior-de-sp' => [
        'type' => 'landing', 'path' => '/alugar-chacara/interior-de-sp/',
        'title' => 'Chácara para Alugar no Interior de SP | Refúgio do Cuscuzeiro',
        'description' => 'Planeje uma estadia em chácara no interior de São Paulo. Natureza, lazer e privacidade em Analândia para família e amigos.',
        'h1' => 'Chácara para alugar no interior de SP',
        'intro' => 'Uma hospedagem em Analândia para desacelerar, aproveitar a natureza e compartilhar o fim de semana com pessoas importantes.',
        'image' => '/assets/images/seo/varanda-refugio-cuscuzeiro-analandia.webp', 'alt' => 'Varanda do Refúgio do Cuscuzeiro voltada para a área verde',
        'breadcrumbs' => [$home, $rent, ['label' => 'Interior de SP', 'path' => '/alugar-chacara/interior-de-sp/']],
        'sections' => [
            ['heading' => 'Por que escolher uma chácara no interior paulista?', 'paragraphs' => [
                'A viagem para o interior permite trocar a rotina urbana por mais espaço, natureza e tempo de convivência. Em uma chácara, o próprio local também faz parte do passeio: é possível preparar refeições, organizar atividades e descansar sem depender de deslocamentos durante todo o dia.',
                'Analândia combina esse tipo de estadia com atrativos de ecoturismo. A cidade pode servir como base para conhecer formações rochosas, trilhas e cachoeiras, sempre respeitando condições climáticas, regras de acesso e orientações dos responsáveis por cada atração.',
            ]],
            ['heading' => 'Estrutura para uma viagem em grupo', 'paragraphs' => [
                'O Refúgio do Cuscuzeiro oferece áreas de acomodação e lazer que aparecem detalhadas na <a href="/comodidades">página de comodidades</a>. Quatro suítes ajudam a distribuir o grupo, enquanto piscina, churrasqueira, jogos e áreas esportivas permitem montar uma programação sem sair da propriedade.',
            ], 'list' => ['Combine previamente compras e refeições;', 'Leve em conta as idades e interesses do grupo;', 'Reserve tempo para descanso e para passeios em Analândia;', 'Confirme regras, datas e demais condições antes da viagem.']],
            ['heading' => 'Como planejar o deslocamento', 'paragraphs' => [
                'O tempo de viagem varia conforme a cidade de origem, o trânsito, a rota e as paradas. Para não apresentar uma distância imprecisa, consulte um aplicativo de mapas pouco antes da saída usando Analândia – SP como destino. Se você parte da capital, veja o guia <a href="/blog/analandia-fica-perto-de-sao-paulo/">onde fica Analândia e como chegar saindo de São Paulo</a>.',
            ], 'note' => '<strong>Planejamento responsável:</strong> confira rota, pedágios e condições da estrada em fonte atualizada no dia da viagem.'],
        ],
        'faq' => [
            ['question' => 'Analândia fica no interior de São Paulo?', 'answer' => 'Sim. Analândia é um município do interior paulista e integra uma região procurada por natureza e ecoturismo.'],
            ['question' => 'Dá para combinar hospedagem e passeios?', 'answer' => 'Sim. Planeje os atrativos desejados e reserve períodos livres para aproveitar a estrutura da chácara.'],
            ['question' => 'Quanto tempo leva a viagem?', 'answer' => 'Depende da origem, da rota e do trânsito. Consulte um mapa atualizado antes de sair; esta página não fixa um tempo que possa ficar incorreto.'],
        ],
        'cta_title' => 'Seu descanso no interior pode começar em Analândia',
        'related' => ['o-que-fazer-em-analandia', 'final-de-semana-em-analandia', 'analandia-fica-perto-de-sao-paulo'],
    ],
    'alugar-chacara/analandia' => [
        'type' => 'landing', 'path' => '/alugar-chacara/analandia/',
        'title' => 'Chácara para Alugar em Analândia SP | Refúgio do Cuscuzeiro',
        'description' => 'Chácara para temporada em Analândia, interior de São Paulo, com suítes e áreas de lazer. Conheça o Refúgio do Cuscuzeiro.',
        'h1' => 'Chácara para alugar em Analândia',
        'intro' => 'Hospede-se perto da natureza e tenha uma base confortável para conhecer os atrativos de Analândia no seu ritmo.',
        'image' => '/assets/images/seo/piscina-refugio-cuscuzeiro-analandia.webp', 'alt' => 'Piscina e área de lazer do Refúgio do Cuscuzeiro em Analândia',
        'breadcrumbs' => [$home, $rent, ['label' => 'Analândia', 'path' => '/alugar-chacara/analandia/']],
        'sections' => [
            ['heading' => 'Hospedagem de temporada em Analândia', 'paragraphs' => [
                'Quem procura uma chácara em Analândia geralmente quer unir duas experiências: aproveitar os espaços da hospedagem e conhecer a paisagem que tornou a cidade um destino de turismo de natureza. O Refúgio do Cuscuzeiro atende a essa intenção com quatro suítes e áreas de convivência e lazer.',
                'A proposta é adequada a grupos que valorizam privacidade e autonomia. Piscina, hidromassagem, churrasqueira, salão de jogos, campo e quadra de areia ajudam a preencher os momentos livres, enquanto a localização em Analândia facilita a organização de passeios pela cidade.',
            ]],
            ['heading' => 'Uma base para conhecer Analândia', 'paragraphs' => [
                'Entre os temas mais procurados estão a <a href="/blog/pedra-do-cuscuzeiro-analandia/">Pedra do Cuscuzeiro</a>, o <a href="/blog/morro-do-camelo-analandia/">Morro do Camelo</a> e as <a href="/blog/cachoeiras-em-analandia/">cachoeiras</a>. A disponibilidade, as regras e eventuais cobranças dos atrativos podem mudar; confirme as informações com seus responsáveis antes de sair.',
                'Para organizar a programação completa, consulte o <a href="/analandia/">guia de Analândia</a> e o roteiro de <a href="/blog/final-de-semana-em-analandia/">dois ou três dias</a>.',
            ]],
            ['heading' => 'Reserve com as informações certas', 'paragraphs' => [
                'Informe datas, número de hóspedes e dados de contato na solicitação. A disponibilidade é analisada manualmente. Antes de concluir, confira fotos, regras e políticas e esclareça qualquer necessidade específica com o atendimento.',
            ], 'table' => ['headers' => ['Etapa', 'O que fazer'], 'rows' => [['1. Conhecer', 'Veja comodidades e fotos reais do imóvel.'], ['2. Planejar', 'Defina datas e composição do grupo.'], ['3. Consultar', 'Envie a solicitação de reserva direta.'], ['4. Confirmar', 'Aguarde a validação de disponibilidade e as orientações.']]]],
        ],
        'faq' => [
            ['question' => 'Tem chácara para alugar em Analândia?', 'answer' => 'Sim. O Refúgio do Cuscuzeiro é uma chácara de aluguel por temporada em Analândia e é a hospedagem proprietária deste site.'],
            ['question' => 'O que fazer em Analândia durante a estadia?', 'answer' => 'A cidade é procurada por paisagens, trilhas, formações rochosas e cachoeiras. Consulte o guia de Analândia e confirme as condições de cada atração.'],
            ['question' => 'A reserva é confirmada na hora?', 'answer' => 'Não. O formulário registra uma solicitação, e a disponibilidade é confirmada manualmente.'],
            ['question' => 'Posso viajar com crianças?', 'answer' => 'Famílias podem consultar a hospedagem, mas devem avaliar cada passeio e as áreas de lazer conforme idade, supervisão e condições do dia.'],
        ],
        'cta_title' => 'Consulte sua estadia em Analândia',
        'related' => ['onde-ficar-em-analandia', 'o-que-fazer-em-analandia', 'chacara-ou-pousada-em-analandia'],
    ],
    'alugar-chacara/final-de-semana' => [
        'type' => 'landing', 'path' => '/alugar-chacara/final-de-semana/',
        'title' => 'Chácara para Final de Semana em Analândia | Refúgio do Cuscuzeiro',
        'description' => 'Planeje um fim de semana em chácara no interior de SP com família ou amigos. Hospedagem e lazer no Refúgio do Cuscuzeiro.',
        'h1' => 'Chácara para passar o final de semana',
        'intro' => 'Poucos dias bem planejados podem combinar descanso, convivência e alguns dos principais passeios de Analândia.',
        'image' => '/assets/images/seo/churrasqueira-refugio-cuscuzeiro.webp', 'alt' => 'Área de churrasqueira do Refúgio do Cuscuzeiro',
        'breadcrumbs' => [$home, $rent, ['label' => 'Final de semana', 'path' => '/alugar-chacara/final-de-semana/']],
        'sections' => [
            ['heading' => 'Como aproveitar uma viagem curta', 'paragraphs' => ['Para um fim de semana funcionar bem, evite preencher todos os horários. Escolha um ou dois passeios prioritários e preserve tempo para usufruir a hospedagem. Assim, a viagem não vira apenas uma sequência de deslocamentos.', 'Na chácara, o grupo pode alternar piscina, jogos, esportes, churrasqueira e descanso. Em Analândia, uma atração de natureza pode ocupar parte do dia; horário, acesso e clima devem ser confirmados previamente.']],
            ['heading' => 'Um roteiro simples e flexível', 'table' => ['headers' => ['Momento', 'Sugestão'], 'rows' => [['Chegada', 'Organize os quartos, compras e refeições do grupo.'], ['Primeiro dia', 'Aproveite as áreas de lazer e descanse da viagem.'], ['Dia principal', 'Faça um passeio planejado e retorne para curtir a chácara.'], ['Saída', 'Reserve tempo para organizar pertences e cumprir as orientações recebidas.']]], 'paragraphs' => ['Para ideias mais detalhadas, consulte o <a href="/blog/final-de-semana-em-analandia/">roteiro de 2 ou 3 dias em Analândia</a>.']],
            ['heading' => 'O que alinhar com o grupo', 'list' => ['Datas e quantidade de hóspedes;', 'Divisão de compras e refeições;', 'Passeios adequados ao perfil e preparo de todos;', 'Transporte, previsão do tempo e itens pessoais;', 'Regras da propriedade e dos atrativos.']],
        ],
        'faq' => [
            ['question' => 'Vale a pena alugar uma chácara por um final de semana?', 'answer' => 'Para grupos que valorizam convivência e querem aproveitar a própria hospedagem, pode ser uma boa escolha. Compare a proposta com o ritmo da viagem.'],
            ['question' => 'Dá para conhecer Analândia em dois dias?', 'answer' => 'É possível conhecer uma seleção de atrações, mas não todas. Priorize os passeios e deixe margem para clima e descanso.'],
            ['question' => 'Como consultar datas?', 'answer' => 'Envie uma solicitação de reserva direta com o período e o número de hóspedes.'],
        ],
        'cta_title' => 'Planeje seu próximo fim de semana',
        'related' => ['final-de-semana-em-analandia', 'o-que-fazer-em-analandia', 'melhor-epoca-para-visitar-analandia'],
    ],
    'alugar-chacara/para-familia' => [
        'type' => 'landing', 'path' => '/alugar-chacara/para-familia/',
        'title' => 'Chácara para Família em Analândia | Refúgio do Cuscuzeiro',
        'description' => 'Chácara para temporada em família em Analândia, com suítes e espaços de lazer. Planeje sua viagem ao interior de São Paulo.',
        'h1' => 'Chácara para temporada em família',
        'intro' => 'Um espaço para reunir gerações, compartilhar refeições e viver o interior de São Paulo com uma programação que respeite cada pessoa.',
        'image' => '/assets/images/seo/varanda-refugio-cuscuzeiro-analandia.webp', 'alt' => 'Varanda ampla do Refúgio do Cuscuzeiro em meio à natureza',
        'breadcrumbs' => [$home, $rent, ['label' => 'Para família', 'path' => '/alugar-chacara/para-familia/']],
        'sections' => [
            ['heading' => 'Mais convivência, menos pressa', 'paragraphs' => ['Uma chácara permite que a família permaneça junta sem abrir mão de diferentes atividades. Enquanto parte do grupo descansa, outros podem aproveitar jogos ou áreas externas. As quatro suítes ajudam na organização, e a cozinha e a churrasqueira favorecem refeições compartilhadas.', 'Viajar em família exige combinar expectativas: quem deseja fazer trilhas, quem prefere atividades leves, horários das crianças e necessidades de pessoas idosas. Planejar com antecedência evita que uma única programação seja imposta a todos.']],
            ['heading' => 'Cuidados ao viajar com crianças', 'paragraphs' => ['Água, áreas esportivas e ambientes externos exigem supervisão constante de um adulto responsável. Também é importante escolher passeios adequados à idade, ao preparo físico e às condições do terreno e do clima. Leia o guia <a href="/blog/analandia-com-criancas/">Analândia com crianças</a> antes de montar o roteiro.'], 'list' => ['Mantenha crianças acompanhadas nas áreas de lazer;', 'Leve água, proteção solar, repelente e roupas compatíveis;', 'Não conte com sinal de celular em todos os passeios;', 'Confirme acesso e dificuldade diretamente com o atrativo.']],
            ['heading' => 'A hospedagem como parte do passeio', 'paragraphs' => ['Nem todo dia precisa ter uma atração externa. Reservar períodos para a piscina, a varanda, os jogos e as refeições em grupo reduz deslocamentos e cria uma experiência mais tranquila.']],
        ],
        'faq' => [
            ['question' => 'A chácara recebe famílias?', 'answer' => 'A proposta e a estrutura são adequadas a estadias em grupo. Informe a composição e as necessidades da família ao consultar a reserva.'],
            ['question' => 'Analândia é boa para viajar com crianças?', 'answer' => 'Pode ser um destino interessante, desde que a família escolha atividades compatíveis com a idade e mantenha supervisão, especialmente em água, trilhas e desníveis.'],
            ['question' => 'Há atividades dentro da chácara?', 'answer' => 'O site apresenta piscina, hidromassagem, salão de jogos, campo, quadra de areia, churrasqueira e varanda.'],
        ],
        'cta_title' => 'Reúna a família no Refúgio',
        'related' => ['analandia-com-criancas', 'final-de-semana-em-analandia', 'chacara-ou-pousada-em-analandia'],
    ],
    'alugar-chacara/perto-de-sao-paulo' => [
        'type' => 'landing', 'path' => '/alugar-chacara/perto-de-sao-paulo/',
        'redirect_to' => '/chacara-perto-de/sao-paulo/',
        'title' => 'Chácara no Interior para Viajar Saindo de São Paulo | Refúgio',
        'description' => 'Conheça uma opção de chácara em Analândia para planejar uma viagem de fim de semana saindo de São Paulo. Consulte rota e disponibilidade.',
        'h1' => 'Chácara no interior para quem sai de São Paulo',
        'intro' => 'O Refúgio do Cuscuzeiro fica em Analândia e pode entrar no planejamento de quem busca natureza e hospedagem em grupo no interior paulista.',
        'image' => '/assets/images/seo/paisagem-analandia-cuscuzeiro.webp', 'alt' => 'Paisagem rural de Analândia com o Cuscuzeiro ao fundo',
        'breadcrumbs' => [$home, $rent, ['label' => 'Saindo de São Paulo', 'path' => '/alugar-chacara/perto-de-sao-paulo/']],
        'sections' => [
            ['heading' => 'Analândia como destino de fim de semana', 'paragraphs' => ['Para quem parte da capital, a escolha do destino envolve mais do que quilômetros: horário de saída, trânsito, pedágios, paradas e perfil do grupo influenciam a experiência. Analândia oferece uma combinação de paisagem natural, atividades ao ar livre e ritmo de cidade do interior.', 'O Refúgio do Cuscuzeiro funciona como base de hospedagem e também como parte da programação, com áreas de lazer e convivência. Isso permite equilibrar passeios com momentos sem deslocamento.']],
            ['heading' => 'Distância e tempo de viagem', 'paragraphs' => ['Rotas e tempos mudam conforme o ponto exato de partida e as condições da estrada. Por isso, esta página não publica uma promessa fixa de duração. Consulte um serviço de mapas atualizado usando seu endereço de saída e Analândia – SP como destino. Veja também o artigo <a href="/blog/analandia-fica-perto-de-sao-paulo/">como chegar a Analândia saindo de São Paulo</a>.'], 'note' => '<strong>Campo preparado para atualização:</strong> quando uma rota oficial e estável for definida, a distância poderá ser centralizada nos dados desta página sem replicar conteúdo.'],
            ['heading' => 'Antes de pegar a estrada', 'paragraphs' => ['Use o hub de <a href="/chacara-perto-de/">chácara perto de diferentes cidades</a> para consultar páginas editoriais específicas de São Carlos, Campinas, Ribeirão Preto, São Paulo e outras origens.'], 'list' => ['Simule a rota no mesmo dia e confira o trânsito;', 'Verifique pedágios e autonomia do veículo;', 'Confirme previsão do tempo e condições dos passeios;', 'Evite uma programação apertada no horário de chegada;', 'Aguarde a confirmação da hospedagem antes de organizar a viagem.']],
        ],
        'faq' => [
            ['question' => 'Analândia fica a quantas horas de São Paulo?', 'answer' => 'O tempo varia conforme o ponto de partida, o trânsito e a rota. Consulte um aplicativo de mapas atualizado para obter uma estimativa adequada ao dia da viagem.'],
            ['question' => 'É possível ir para um final de semana?', 'answer' => 'Sim, desde que o grupo considere o deslocamento e monte uma programação realista. Um roteiro de dois ou três dias ajuda a priorizar.'],
            ['question' => 'A chácara fica em Analândia?', 'answer' => 'Sim. O Refúgio do Cuscuzeiro está localizado em Analândia, no interior de São Paulo.'],
        ],
        'cta_title' => 'Troque a rotina pela natureza de Analândia',
        'related' => ['analandia-fica-perto-de-sao-paulo', 'final-de-semana-em-analandia', 'melhor-epoca-para-visitar-analandia'],
    ],
    'analandia' => [
        'type' => 'tourism-hub', 'path' => '/analandia/',
        'title' => 'Analândia SP: Guia Completo para Conhecer a Cidade',
        'description' => 'Planeje sua viagem a Analândia: Pedra do Cuscuzeiro, Morro do Camelo, cachoeiras, trilhas, hospedagem e roteiro no interior de SP.',
        'h1' => 'Analândia SP: guia completo para conhecer a cidade',
        'intro' => 'Um ponto de partida para organizar atrações, natureza, hospedagem e os detalhes práticos da sua viagem.',
        'image' => '/assets/images/seo/pedra-do-cuscuzeiro-analandia.webp', 'alt' => 'Pedra do Cuscuzeiro vista em meio à paisagem de Analândia',
        'breadcrumbs' => [$home, ['label' => 'Analândia', 'path' => '/analandia/']],
        'sections' => [
            ['heading' => 'Por que conhecer Analândia?', 'paragraphs' => ['Analândia é um destino do interior paulista associado ao turismo de natureza. Sua paisagem reúne formações rochosas, áreas rurais, trilhas e quedas d’água. É uma viagem que funciona melhor quando o visitante respeita o próprio ritmo e confirma previamente como acessar cada local.', 'A <a href="/blog/pedra-do-cuscuzeiro-analandia/">Pedra do Cuscuzeiro</a> é o cartão-postal mais lembrado. O <a href="/blog/morro-do-camelo-analandia/">Morro do Camelo</a> amplia as opções de paisagem, e as <a href="/blog/cachoeiras-em-analandia/">cachoeiras de Analândia</a> podem entrar no roteiro conforme clima e condições de acesso.']],
            ['heading' => 'Planeje a viagem por interesse', 'table' => ['headers' => ['Interesse', 'Comece por'], 'rows' => [['Primeira visita', '<a href="/blog/o-que-fazer-em-analandia/">O que fazer em Analândia</a>'], ['Fim de semana', '<a href="/blog/final-de-semana-em-analandia/">Roteiro de 2 ou 3 dias</a>'], ['Com crianças', '<a href="/blog/analandia-com-criancas/">Analândia em família</a>'], ['Clima e temporada', '<a href="/blog/melhor-epoca-para-visitar-analandia/">Melhor época para visitar</a>'], ['Saindo da capital', '<a href="/blog/analandia-fica-perto-de-sao-paulo/">Onde fica e como chegar</a>']]]],
            ['heading' => 'Onde ficar em Analândia', 'paragraphs' => ['A escolha depende do perfil da viagem. Pousadas podem ser práticas para estadias menores; uma chácara favorece grupos que buscam convivência e autonomia. O <a href="/alugar-chacara/analandia/">Refúgio do Cuscuzeiro</a>, hospedagem proprietária deste site, oferece quatro suítes e áreas de lazer. Veja a comparação entre <a href="/blog/chacara-ou-pousada-em-analandia/">chácara e pousada</a> e o guia de <a href="/blog/onde-ficar-em-analandia/">onde ficar</a>.']],
            ['heading' => 'Dicas práticas para os passeios', 'list' => ['Consulte horários, acesso, ingressos e necessidade de guia com cada atrativo;', 'Verifique previsão do tempo e evite trilhas em condições inseguras;', 'Use calçado compatível, proteção solar e leve água;', 'Não deixe resíduos e siga as regras ambientais;', 'Reserve períodos livres para refeições e descanso.']],
        ],
        'faq' => [
            ['question' => 'Onde fica Analândia?', 'answer' => 'Analândia é um município do interior do estado de São Paulo. Consulte a rota atualizada a partir da sua origem antes da viagem.'],
            ['question' => 'O que fazer em Analândia?', 'answer' => 'Os roteiros costumam incluir paisagens, formações rochosas, trilhas e cachoeiras, além de gastronomia e descanso. As condições de acesso devem ser confirmadas.'],
            ['question' => 'Dá para conhecer Analândia em um final de semana?', 'answer' => 'Dá para conhecer uma seleção de atrações. Priorize o que combina com seu grupo e evite tentar encaixar todos os passeios.'],
            ['question' => 'Onde se hospedar em Analândia?', 'answer' => 'Há diferentes formatos de hospedagem. O Refúgio do Cuscuzeiro é a chácara proprietária deste site e atende grupos que procuram mais convivência e áreas de lazer.'],
            ['question' => 'Analândia é boa para crianças?', 'answer' => 'Pode ser, com atividades escolhidas conforme idade e preparo, supervisão constante e atenção especial a água, trilhas e desníveis.'],
        ],
        'cta_title' => 'Faça do Refúgio sua base em Analândia',
        'related' => ['o-que-fazer-em-analandia', 'pedra-do-cuscuzeiro-analandia', 'onde-ficar-em-analandia'],
    ],
];

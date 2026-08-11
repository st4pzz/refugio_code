# SEO programático por cidade de origem

## Arquitetura

O cluster comercial utiliza uma única estrutura de URL:

`/chacara-perto-de/{cidade}/`

Ela foi escolhida por expressar diretamente a intenção transacional “chácara perto de”, sem misturar esse objetivo com o cluster informacional do blog. O hub é `/chacara-perto-de/`.

Os registros ficam em `app/Seo/Data/cities.php`. Adicionar uma cidade não exige criar rota, view ou componente: o repositório resolve o slug e reutiliza `app/Views/seo/city-page.php`.

## Barreira de indexação

`CityContentValidator` calcula a indexabilidade. Não basta definir `requested_indexable=true`: a cidade precisa estar ativa e possuir metadata, intenção, conteúdo específico, roteiro, FAQ, cidades relacionadas, distância, duração, acessos, fonte e data de verificação.

Uma cidade incompleta:

- continua disponível para revisão quando `active=true`;
- recebe `noindex,follow`;
- não entra no sitemap;
- expõe os erros no registro carregado para os testes de desenvolvimento.

Execute:

```bash
php tests/city_seo_validation.php
```

## Dados de rota

Distâncias e durações são dados editoriais aproximados, não consultas em tempo real. Cada registro guarda fonte e data de verificação. Revalide periodicamente e antes de fazer afirmações novas sobre rodovias, pedágios ou duração.

## Analytics

As páginas incluem `data-seo-landing-type="city"` e `data-seo-origin-city="slug"` no `body`. O GTM pode ler esses atributos futuramente sem duplicar PageView ou alterar os pixels atuais.

## Próximo cluster

O cluster futuro `/viagem-perto-de/{cidade}/` deve responder intenção informacional (“onde viajar perto de Campinas”), com comparação de destinos e roteiro. Não deve reutilizar o H1, title ou texto comercial de `/chacara-perto-de/{cidade}/`.

Antes de publicar esse segundo cluster:

1. confirme volume e intenção nas consultas do Search Console;
2. crie uma fonte de dados separada, por exemplo `travel-cities.php`;
3. exija conteúdo que compare possibilidades e responda dúvidas de planejamento;
4. direcione o CTA comercial para a página de chácara correspondente;
5. monitore canibalização entre as duas URLs por cidade.

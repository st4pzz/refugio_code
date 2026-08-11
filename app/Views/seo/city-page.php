<?php
require __DIR__ . '/_components.php';
require __DIR__ . '/_top.php';
[$heroWidth, $heroHeight] = seo_image_dimensions($page['image']);
$attractions = $repository->related(['pedra-do-cuscuzeiro-analandia', 'morro-do-camelo-analandia', 'cachoeiras-em-analandia', 'o-que-fazer-em-analandia', 'analandia-com-criancas', 'final-de-semana-em-analandia']);
$relatedCities = [];
foreach ($page['nearby_cities'] as $citySlug) {
    $relatedCity = $repository->city($citySlug);
    if ($relatedCity !== null && ($relatedCity['indexable'] ?? false)) {
        $relatedCities[] = $relatedCity;
    }
}
$roads = implode(' e ', $page['main_roads']);
?>
<main>
    <section class="seo-hero city-page-hero">
        <div class="seo-hero-media"><img src="<?= e($page['image']) ?>" alt="<?= e($page['alt']) ?>" width="<?= $heroWidth ?>" height="<?= $heroHeight ?>" loading="eager" fetchpriority="high" decoding="async"></div>
        <div class="seo-hero-overlay"></div>
        <div class="seo-container seo-hero-content">
            <?php seo_render_breadcrumbs($page['breadcrumbs']); ?>
            <span class="seo-eyebrow">Saindo de <?= e($page['name']) ?></span>
            <h1><?= e($page['h1']) ?></h1>
            <p><?= e($page['intro']) ?></p>
            <div class="seo-hero-actions"><a class="seo-button" href="/reserva/solicitar">Consultar disponibilidade</a><a class="seo-button seo-button-ghost" href="/a-chacara">Conhecer a chácara</a></div>
        </div>
    </section>

    <section class="travel-distance" aria-labelledby="travel-title">
        <div class="seo-container travel-distance-grid">
            <div>
                <span class="seo-eyebrow">Trajeto editorial</span>
                <h2 id="travel-title">De <?= e($page['name']) ?> até Analândia</h2>
                <p><?= e($page['route_description']) ?></p>
                <p class="route-disclaimer">A duração varia conforme endereço de saída, trânsito, paradas e rota indicada no momento. Consulte novamente antes de viajar.</p>
                <a class="route-source" href="<?= e($page['route_source_url']) ?>" target="_blank" rel="noopener noreferrer">Conferir rota atual no <?= e($page['route_source_label']) ?> <span aria-hidden="true">↗</span></a>
            </div>
            <div class="travel-flow" aria-label="Resumo do deslocamento de <?= e($page['name']) ?> a Analândia">
                <div class="travel-stop"><strong><?= e($page['name']) ?></strong><span><?= e($page['state']) ?></span></div>
                <span class="travel-arrow" aria-hidden="true">↓</span>
                <div class="travel-metrics"><span><strong>~<?= e($page['distance_km']) ?> km</strong>Distância aproximada</span><span><strong><?= e($page['travel_time']) ?></strong>Referência de carro</span></div>
                <span class="travel-arrow" aria-hidden="true">↓</span>
                <div class="travel-stop"><strong>Analândia</strong><span>Refúgio do Cuscuzeiro</span></div>
                <small>Via <?= e($roads) ?> · verificado em <?= e(date('d/m/Y', strtotime($page['route_verified_at']))) ?></small>
            </div>
        </div>
    </section>

    <section class="city-story">
        <div class="seo-container city-story-grid">
            <article><span class="seo-eyebrow">Contexto da viagem</span><h2>Por que escolher Analândia saindo de <?= e($page['name']) ?>?</h2><?php foreach ($page['why_visit'] as $paragraph): ?><p><?= e($paragraph) ?></p><?php endforeach; ?></article>
            <aside class="trip-profile"><h3>Para quem esta viagem faz sentido</h3><p><?= e($page['trip_profile']) ?></p><h3>Como organizar o deslocamento</h3><p><?= e($page['travel_context']) ?></p></aside>
        </div>
    </section>

    <section class="property-highlight">
        <div class="seo-container property-highlight-grid">
            <div class="property-highlight-image"><img src="/assets/images/seo/varanda-refugio-cuscuzeiro-analandia.webp" alt="Varanda do Refúgio do Cuscuzeiro em Analândia" width="900" height="1600" loading="lazy" decoding="async"></div>
            <div><span class="seo-eyebrow">Hospedagem proprietária</span><h2>Conheça o Refúgio do Cuscuzeiro</h2><p>O Refúgio fica em Analândia e é a hospedagem responsável por este conteúdo. A estrutura confirmada no site inclui quatro suítes, piscina, hidromassagem, churrasqueira, varanda, salão de jogos, campo, quadra de areia e garagem coberta para quatro veículos.</p><p>Consulte as condições da estadia e informe a composição do grupo. A solicitação depende de confirmação manual de disponibilidade.</p><div class="property-actions"><a class="seo-button seo-button-secondary" href="/galeria-de-fotos">Ver fotos</a><a class="seo-button" href="/reserva/solicitar">Consultar disponibilidade</a></div></div>
        </div>
    </section>

    <section class="weekend-itinerary" aria-labelledby="itinerary-title">
        <div class="seo-container"><div class="section-heading"><span>Roteiro flexível</span><h2 id="itinerary-title">Sugestão de final de semana saindo de <?= e($page['name']) ?></h2></div><div class="itinerary-grid"><?php $step = 1; foreach ($page['weekend_plan'] as $day => $plan): ?><article><span><?= $step++ ?></span><h3><?= e($day) ?></h3><p><?= e($plan) ?></p></article><?php endforeach; ?></div></div>
    </section>

    <section class="city-attractions" aria-labelledby="attractions-title">
        <div class="seo-container"><div class="section-heading"><span>Explore a região</span><h2 id="attractions-title">O que fazer durante sua viagem</h2></div><div class="attraction-grid"><?php foreach ($attractions as $attraction): seo_render_attraction_link($attraction); endforeach; ?></div><p class="cluster-links">Veja também o <a href="/analandia/">guia completo de Analândia</a>, compare <a href="/blog/onde-ficar-em-analandia/">onde ficar</a> e conheça a página de <a href="/alugar-chacara/analandia/">chácara para alugar em Analândia</a>.</p></div>
    </section>

    <section class="stay-comparison"><div class="seo-container stay-comparison-grid"><div><span class="seo-eyebrow">Escolha de hospedagem</span><h2>Por que comparar chácara, hotel e pousada?</h2><p>Não existe formato melhor para todas as viagens. A chácara pode favorecer privacidade do grupo, refeições próprias e áreas comuns. Hotel ou pousada podem ser mais práticos para casais e roteiros com pouco tempo na hospedagem.</p><p>Compare o custo total, as regras, a localização e o que será realmente utilizado. Leia a análise de <a href="/blog/chacara-ou-pousada-em-analandia/">chácara ou pousada em Analândia</a>.</p></div><ul><li>Convivência em espaços compartilhados apenas pelo grupo;</li><li>Autonomia para organizar refeições e horários;</li><li>Lazer dentro da própria hospedagem;</li><li>Flexibilidade para famílias e amigos;</li><li>Responsabilidade do grupo pela organização da estadia.</li></ul></div></section>

    <div class="seo-container city-faq-wrap"><?php seo_render_faq($page['faq']); ?><p class="content-author">Dados de rota verificados editorialmente em <?= e(date('d/m/Y', strtotime($page['route_verified_at']))) ?>. Conteúdo produzido pelo Refúgio do Cuscuzeiro, hospedagem localizada em Analândia – SP.</p></div>
    <div class="seo-container"><?php seo_render_booking_cta($page['cta_title']); ?></div>

    <section class="related-cities" aria-labelledby="related-cities-title"><div class="seo-container"><div class="section-heading"><span>Outras origens</span><h2 id="related-cities-title">Outras cidades para planejar a viagem</h2></div><div class="city-grid city-grid-related"><?php foreach ($relatedCities as $city): seo_render_city_card($city); endforeach; ?></div><a class="text-arrow city-hub-link" href="/chacara-perto-de/">Ver todas as cidades <span aria-hidden="true">→</span></a></div></section>
</main>
<?php require __DIR__ . '/_bottom.php'; ?>

<?php require __DIR__ . '/_components.php'; require __DIR__ . '/_top.php'; [$heroWidth, $heroHeight] = seo_image_dimensions($page['image']); $cityList = $repository->cities(true); ?>
<main>
    <section class="seo-hero city-hub-hero">
        <div class="seo-hero-media"><img src="<?= e($page['image']) ?>" alt="<?= e($page['alt']) ?>" width="<?= $heroWidth ?>" height="<?= $heroHeight ?>" loading="eager" fetchpriority="high"></div>
        <div class="seo-hero-overlay"></div>
        <div class="seo-container seo-hero-content">
            <?php seo_render_breadcrumbs($page['breadcrumbs']); ?>
            <span class="seo-eyebrow">Planeje sua saída</span>
            <h1><?= e($page['h1']) ?></h1>
            <p><?= e($page['intro']) ?></p>
            <div class="seo-hero-actions"><a class="seo-button" href="/reserva/solicitar">Consultar disponibilidade</a><a class="seo-button seo-button-ghost" href="/alugar-chacara/analandia/">Conhecer o Refúgio</a></div>
        </div>
    </section>

    <section class="city-hub-intro">
        <div class="seo-container city-hub-copy">
            <div><span class="seo-eyebrow">Uma hospedagem, diferentes origens</span><h2>Escolha de onde começa sua viagem</h2></div>
            <div><p>O Refúgio do Cuscuzeiro fica em Analândia. As páginas abaixo usam a região central de cada cidade como referência editorial para ajudar no planejamento — não como promessa de duração.</p><p>Distância, tempo e acesso foram verificados na data informada em cada página. Recalcule sempre a rota com seu endereço real antes de sair.</p></div>
        </div>
    </section>

    <section class="city-directory" aria-labelledby="city-directory-title">
        <div class="seo-container">
            <div class="section-heading"><span>Cidades prioritárias</span><h2 id="city-directory-title">Encontre sua cidade de origem</h2></div>
            <div class="city-grid"><?php foreach ($cityList as $city): seo_render_city_card($city); endforeach; ?></div>
        </div>
    </section>

    <section class="seo-container city-hub-guidance">
        <div class="city-guidance-card"><h2>O que você encontra em cada guia</h2><ul><li>Distância e duração aproximadas até Analândia;</li><li>Contexto específico para o tamanho da viagem;</li><li>Roteiro flexível de sexta a domingo;</li><li>Atrações e conteúdos locais relacionados;</li><li>CTA para consultar a hospedagem diretamente.</li></ul></div>
        <div class="city-guidance-card city-guidance-links"><h2>Conheça primeiro o destino</h2><p>Se ainda está comparando opções, comece pelo <a href="/analandia/">guia de Analândia</a>, veja <a href="/blog/o-que-fazer-em-analandia/">o que fazer na cidade</a> e entenda <a href="/blog/onde-ficar-em-analandia/">onde ficar</a>.</p><a class="text-arrow" href="/alugar-chacara/interior-de-sp/">Conhecer a hospedagem no interior <span aria-hidden="true">→</span></a></div>
    </section>
    <div class="seo-container"><?php seo_render_booking_cta('Encontre uma data para conhecer Analândia'); ?></div>
</main>
<?php require __DIR__ . '/_bottom.php'; ?>


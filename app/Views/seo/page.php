<?php require __DIR__ . '/_components.php'; require __DIR__ . '/_top.php'; [$heroWidth, $heroHeight] = seo_image_dimensions($page['image']); ?>
<main>
    <section class="seo-hero">
        <div class="seo-hero-media">
            <img src="<?= e($page['image']) ?>" alt="<?= e($page['alt']) ?>" width="<?= $heroWidth ?>" height="<?= $heroHeight ?>" loading="eager" fetchpriority="high" decoding="async">
        </div>
        <div class="seo-hero-overlay"></div>
        <div class="seo-container seo-hero-content">
            <?php seo_render_breadcrumbs($page['breadcrumbs']); ?>
            <?php if (($page['type'] ?? '') === 'article'): ?><span class="seo-eyebrow"><?= e($page['category']) ?></span><?php endif; ?>
            <h1><?= e($page['h1']) ?></h1>
            <p><?= e($page['intro']) ?></p>
            <?php if (($page['type'] ?? '') === 'article'): ?>
                <div class="article-meta article-meta-hero"><time datetime="<?= e($page['date']) ?>"><?= e($page['date_label']) ?></time><span><?= e($page['read_time']) ?></span></div>
            <?php else: ?>
                <div class="seo-hero-actions"><a class="seo-button" href="/reserva/solicitar">Consultar disponibilidade</a><a class="seo-button seo-button-ghost" href="/galeria-de-fotos">Ver fotos da chácara</a></div>
            <?php endif; ?>
        </div>
    </section>

    <div class="seo-container seo-layout">
        <article class="seo-article">
            <?php seo_render_sections($page['sections']); ?>
            <?php seo_render_faq($page['faq'] ?? []); ?>
            <p class="content-author">Conteúdo produzido pelo Refúgio do Cuscuzeiro, hospedagem localizada em Analândia – SP.</p>
        </article>
    </div>

    <div class="seo-container"><?php seo_render_booking_cta($page['cta_title'] ?? 'Vai conhecer Analândia?'); ?></div>

    <?php if (!empty($page['related'])): ?>
        <section class="related-content">
            <div class="seo-container">
                <div class="section-heading"><span>Continue planejando</span><h2><?= ($page['type'] ?? '') === 'article' ? 'Artigos relacionados' : 'Veja também' ?></h2></div>
                <div class="article-grid article-grid-related">
                    <?php foreach ($repository->related($page['related']) as $related): seo_render_article_card($related); endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/_bottom.php'; ?>

<?php require __DIR__ . '/_components.php'; require __DIR__ . '/_top.php'; $featured = $articles[0]; [$featuredWidth, $featuredHeight] = seo_image_dimensions($featured['image']); ?>
<main>
    <section class="blog-hero">
        <div class="seo-container">
            <?php seo_render_breadcrumbs($page['breadcrumbs']); ?>
            <span class="seo-eyebrow">Blog</span>
            <h1><?= e($page['h1']) ?></h1>
            <p><?= e($page['intro']) ?></p>
        </div>
    </section>

    <section class="featured-section">
        <div class="seo-container">
            <div class="section-heading"><span>Comece por aqui</span><h2>Artigo em destaque</h2></div>
            <article class="featured-article">
                <a class="featured-image" href="<?= e($featured['path']) ?>"><img src="<?= e($featured['image']) ?>" alt="<?= e($featured['alt']) ?>" width="<?= $featuredWidth ?>" height="<?= $featuredHeight ?>" loading="eager" fetchpriority="high"></a>
                <div class="featured-copy">
                    <span class="article-category"><?= e($featured['category']) ?></span>
                    <h2><a href="<?= e($featured['path']) ?>"><?= e($featured['h1']) ?></a></h2>
                    <p><?= e($featured['excerpt']) ?></p>
                    <div class="article-meta"><time datetime="<?= e($featured['date']) ?>"><?= e($featured['date_label']) ?></time><span><?= e($featured['read_time']) ?></span></div>
                    <a class="text-arrow" href="<?= e($featured['path']) ?>">Ler guia completo <span aria-hidden="true">→</span></a>
                </div>
            </article>
        </div>
    </section>

    <section class="articles-section">
        <div class="seo-container">
            <div class="section-heading"><span>Guias locais</span><h2>Artigos recentes</h2></div>
            <div class="article-grid">
                <?php foreach (array_slice($articles, 1) as $article): seo_render_article_card($article); endforeach; ?>
            </div>
        </div>
    </section>
    <div class="seo-container"><?php seo_render_booking_cta(); ?></div>
</main>
<?php require __DIR__ . '/_bottom.php'; ?>

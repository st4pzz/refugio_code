<?php
declare(strict_types=1);

function seo_render_breadcrumbs(array $breadcrumbs): void
{
    if ($breadcrumbs === []) {
        return;
    }
    ?>
    <nav class="seo-breadcrumbs" aria-label="Breadcrumb">
        <ol>
            <?php foreach ($breadcrumbs as $index => $crumb): ?>
                <li>
                    <?php if ($index === array_key_last($breadcrumbs)): ?>
                        <span aria-current="page"><?= e($crumb['label']) ?></span>
                    <?php else: ?>
                        <a href="<?= e($crumb['path']) ?>"><?= e($crumb['label']) ?></a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </nav>
    <?php
}

function seo_image_dimensions(string $src): array
{
    return match ($src) {
        '/assets/images/seo/chacara-refugio-cuscuzeiro-analandia.webp' => [1200, 1600],
        '/assets/images/seo/pedra-do-cuscuzeiro-analandia.webp' => [1600, 898],
        '/assets/images/seo/varanda-refugio-cuscuzeiro-analandia.webp', '/assets/images/seo/piscina-refugio-cuscuzeiro-analandia.webp', '/assets/images/seo/churrasqueira-refugio-cuscuzeiro.webp', '/assets/images/seo/ecoturismo-analandia.webp' => [900, 1600],
        '/assets/images/seo/paisagem-analandia-cuscuzeiro.webp' => [1600, 898],
        '/assets/images/seo/passeio-ao-ar-livre-analandia.webp' => [1074, 1600],
        '/assets/images/cachoeira.webp' => [338, 450],
        default => [1600, 900],
    };
}

function seo_render_sections(array $sections): void
{
    foreach ($sections as $section) {
        ?>
        <section class="seo-content-section">
            <h2><?= e($section['heading']) ?></h2>
            <?php foreach ($section['paragraphs'] ?? [] as $paragraph): ?>
                <p><?= $paragraph ?></p>
            <?php endforeach; ?>
            <?php if (!empty($section['subsections'])): ?>
                <?php foreach ($section['subsections'] as $subsection): ?>
                    <h3><?= e($subsection['heading']) ?></h3>
                    <?php foreach ($subsection['paragraphs'] ?? [] as $paragraph): ?>
                        <p><?= $paragraph ?></p>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if (!empty($section['list'])): ?>
                <ul>
                    <?php foreach ($section['list'] as $item): ?><li><?= $item ?></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if (!empty($section['table'])): ?>
                <div class="seo-table-wrap">
                    <table>
                        <thead><tr><?php foreach ($section['table']['headers'] as $header): ?><th scope="col"><?= e($header) ?></th><?php endforeach; ?></tr></thead>
                        <tbody><?php foreach ($section['table']['rows'] as $row): ?><tr><?php foreach ($row as $cell): ?><td><?= $cell ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
                    </table>
                </div>
            <?php endif; ?>
            <?php if (!empty($section['note'])): ?><aside class="seo-note"><?= $section['note'] ?></aside><?php endif; ?>
        </section>
        <?php
    }
}

function seo_render_faq(array $faq): void
{
    if ($faq === []) {
        return;
    }
    ?>
    <section class="seo-faq" aria-labelledby="faq-title">
        <h2 id="faq-title">Perguntas frequentes</h2>
        <div class="seo-faq-list">
            <?php foreach ($faq as $item): ?>
                <details>
                    <summary><?= e($item['question']) ?></summary>
                    <div><p><?= $item['answer'] ?></p></div>
                </details>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
}

function seo_render_booking_cta(string $context = 'Vai conhecer Analândia?'): void
{
    ?>
    <aside class="booking-cta">
        <div>
            <span class="booking-cta-kicker">Reserva direta</span>
            <h2><?= e($context) ?></h2>
            <p>Conheça o Refúgio do Cuscuzeiro e consulte as datas para aproveitar Analândia com privacidade e tranquilidade.</p>
        </div>
        <div class="booking-cta-actions">
            <a class="seo-button seo-button-secondary" href="/a-chacara">Conhecer a chácara</a>
            <a class="seo-button" href="/reserva/solicitar">Consultar disponibilidade</a>
        </div>
    </aside>
    <?php
}

function seo_render_article_card(array $article): void
{
    [$width, $height] = seo_image_dimensions($article['image']);
    ?>
    <article class="article-card">
        <a class="article-card-image" href="<?= e($article['path']) ?>" tabindex="-1" aria-hidden="true">
            <img src="<?= e($article['image']) ?>" alt="<?= e($article['alt']) ?>" loading="lazy" decoding="async" width="<?= $width ?>" height="<?= $height ?>">
        </a>
        <div class="article-card-body">
            <span class="article-category"><?= e($article['category']) ?></span>
            <h2><a href="<?= e($article['path']) ?>"><?= e($article['h1']) ?></a></h2>
            <p><?= e($article['excerpt']) ?></p>
            <div class="article-meta"><time datetime="<?= e($article['date']) ?>"><?= e($article['date_label']) ?></time><span><?= e($article['read_time']) ?></span></div>
        </div>
    </article>
    <?php
}

function seo_render_city_card(array $city): void
{
    ?>
    <article class="city-card">
        <a href="/chacara-perto-de/<?= e($city['slug']) ?>/">
            <span class="city-card-state"><?= e($city['state']) ?></span>
            <h3><?= e($city['name']) ?></h3>
            <p><?= e($city['distance_km']) ?> km aproximadamente · <?= e($city['travel_time']) ?></p>
            <span class="city-card-link">Planejar saída de <?= e($city['name']) ?> <span aria-hidden="true">→</span></span>
        </a>
    </article>
    <?php
}

function seo_render_attraction_link(array $article): void
{
    [$width, $height] = seo_image_dimensions($article['image']);
    ?>
    <article class="attraction-card">
        <a href="<?= e($article['path']) ?>">
            <img src="<?= e($article['image']) ?>" alt="<?= e($article['alt']) ?>" width="<?= $width ?>" height="<?= $height ?>" loading="lazy" decoding="async">
            <span><?= e($article['category']) ?></span>
            <h3><?= e($article['h1']) ?></h3>
        </a>
    </article>
    <?php
}

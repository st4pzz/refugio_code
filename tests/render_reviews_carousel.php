<?php
declare(strict_types=1);
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) { http_response_code(404); exit; }
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Teste do carrossel</title><link rel="stylesheet" href="/style.css"><link rel="stylesheet" href="/assets/css/reviews-public.css"></head><body>
<section class="reviews" id="reviews"><div class="section-container"><h2 class="section-title">O que nossos hóspedes dizem</h2><p class="section-subtitle">Avaliações verificadas</p>
<div class="review-summary" data-review-summary hidden aria-live="polite"><strong><span data-review-average></span> de 5</strong><span data-review-count></span></div>
<p class="review-empty" data-review-empty>As avaliações verificadas aparecerão aqui em breve.</p>
<div class="review-carousel" data-source="/tests/mock_public_reviews.php" aria-roledescription="carousel" aria-label="Avaliações verificadas" hidden><div class="review-slides preload" aria-live="polite"></div><div class="review-controls"><button class="review-prev" type="button" aria-label="Avaliação anterior">‹</button><div class="review-dots" role="tablist" aria-label="Navegar pelas avaliações"></div><button class="review-next" type="button" aria-label="Próxima avaliação">›</button></div></div>
</div></section><script defer src="/assets/js/reviews-carousel.js?v=2"></script></body></html>

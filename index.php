<?php
$cookieConsent = (string) ($_COOKIE['refugio_cookie_consent'] ?? '');
$showCookieBanner = $cookieConsent === '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="description" content="Refúgio do Cuscuzeiro - Chácara de aluguel por temporada em Analândia, São Paulo. Piscina, suítes, churrasqueira e muito mais.">
    <meta name="keywords" content="chácara, aluguel, temporada, Analândia, piscina, refúgio">
    <meta property="og:title" content="Refúgio do Cuscuzeiro - Chácara de Aluguel">
    <meta property="og:description" content="Seu refúgio perfeito em Analândia. Natureza, conforto e exclusividade.">
    <meta property="og:type" content="website">
    <meta property="og:image" content="assets/images/logo_refugio.webp">
    <link rel="icon" type="image/png" href="assets/images/logo_refugio.png">
    <meta name="facebook-domain-verification" content="sm11y3l28i1v2s2g36avcu3z3vejtg" />
    <script>
        (function (window, document) {
            var googleAdsId = 'AW-18158463231';
            var googleConversionId = 'AW-18158463231/1kiHCPjr7q0cEP_R0NJD';
            var metaPixelId = '1375499027959251';
            var tiktokPixelId = 'D9FTH53C77UBS5FSK8MG';
            var consentCookieName = 'refugio_cookie_consent';
            var consentCookieMaxAge = 31536000;

            window.dataLayer = window.dataLayer || [];
            window.gtag = window.gtag || function () {
                window.dataLayer.push(arguments);
            };

            if (!window.fbq) {
                var fbq = window.fbq = function () {
                    fbq.callMethod ? fbq.callMethod.apply(fbq, arguments) : fbq.queue.push(arguments);
                };
                if (!window._fbq) window._fbq = fbq;
                fbq.push = fbq;
                fbq.loaded = true;
                fbq.version = '2.0';
                fbq.queue = [];
            }

            function loadScript(src, id) {
                if (id && document.getElementById(id)) return;
                var script = document.createElement('script');
                if (id) script.id = id;
                script.async = true;
                script.src = src;
                document.head.appendChild(script);
            }

            function getCookie(name) {
                var prefix = name + '=';
                var parts = document.cookie ? document.cookie.split('; ') : [];
                for (var i = 0; i < parts.length; i++) {
                    if (parts[i].indexOf(prefix) === 0) {
                        return decodeURIComponent(parts[i].slice(prefix.length));
                    }
                }
                return '';
            }

            function setCookie(name, value, maxAge) {
                document.cookie = name + '=' + encodeURIComponent(value) + '; Max-Age=' + maxAge + '; Path=/; SameSite=Lax';
            }

            function hasMarketingConsent() {
                return getCookie(consentCookieName) === 'accepted';
            }

            function loadTikTokPixel() {
                if (window.__tiktokTagsLoaded) return;
                window.__tiktokTagsLoaded = true;

                var ttq = window.ttq = window.ttq || [];
                ttq.methods = ['page', 'track', 'identify', 'instances', 'debug', 'on', 'off', 'once', 'ready', 'alias', 'group', 'enableCookie', 'disableCookie', 'holdConsent', 'revokeConsent', 'grantConsent'];
                ttq.setAndDefer = function (target, method) {
                    target[method] = function () {
                        target.push([method].concat(Array.prototype.slice.call(arguments, 0)));
                    };
                };
                for (var i = 0; i < ttq.methods.length; i++) {
                    ttq.setAndDefer(ttq, ttq.methods[i]);
                }
                ttq.instance = function (pixelId) {
                    var instance = ttq._i[pixelId] || [];
                    for (var j = 0; j < ttq.methods.length; j++) ttq.setAndDefer(instance, ttq.methods[j]);
                    return instance;
                };
                ttq.load = function (pixelId, options) {
                    var src = 'https://analytics.tiktok.com/i18n/pixel/events.js';
                    var partner = options && options.partner;
                    ttq._i = ttq._i || {};
                    ttq._i[pixelId] = [];
                    ttq._i[pixelId]._u = src;
                    ttq._i[pixelId]._partner = partner || 'GoogleTagManagerClient';
                    ttq._t = ttq._t || {};
                    ttq._t[pixelId] = +new Date();
                    ttq._o = ttq._o || {};
                    ttq._o[pixelId] = options || {};
                    ttq._partner = ttq._partner || 'GoogleTagManagerClient';
                    loadScript(src + '?sdkid=' + pixelId + '&lib=ttq', 'tiktok-pixel-loader');
                };

                ttq.load(tiktokPixelId);
                ttq.page();
            }

            function loadMarketingTags() {
                if (window.__marketingTagsLoaded || !hasMarketingConsent()) return;
                window.__marketingTagsLoaded = true;

                loadScript('https://www.googletagmanager.com/gtm.js?id=GTM-5D4WKTM7', 'gtm-loader');
                loadScript('https://www.googletagmanager.com/gtag/js?id=' + googleAdsId, 'google-ads-loader');
                window.gtag('js', new Date());
                window.gtag('config', googleAdsId);

                loadScript('https://connect.facebook.net/en_US/fbevents.js', 'meta-pixel-loader');
                window.fbq('init', metaPixelId);
                window.fbq('track', 'PageView');
                window.fbq('track', 'ViewContent');

                loadTikTokPixel();
            }

            function hideBanner() {
                var banner = document.querySelector('[data-cookie-consent-banner]');
                if (!banner) return;
                banner.classList.add('is-hidden');
                window.setTimeout(function () {
                    banner.hidden = true;
                }, 250);
                document.body.classList.remove('cookie-consent-visible');
            }

            function setConsentChoice(value) {
                setCookie(consentCookieName, value, consentCookieMaxAge);
                if (value === 'accepted') {
                    loadMarketingTags();
                }
                hideBanner();
            }

            window.gtag_report_conversion = function () {
                if (!hasMarketingConsent()) return false;
                window.gtag('event', 'conversion', {
                    send_to: googleConversionId,
                    value: 1.0,
                    currency: 'BRL'
                });
                return true;
            };

            window.trackOutboundClick = function (metaEvent) {
                if (!hasMarketingConsent()) return true;
                loadMarketingTags();
                if (metaEvent && typeof window.fbq === 'function') {
                    window.fbq('track', metaEvent);
                }
                window.gtag_report_conversion();
                return true;
            };

            window.acceptCookieConsent = function () {
                setConsentChoice('accepted');
                return true;
            };

            window.rejectCookieConsent = function () {
                setConsentChoice('rejected');
                return true;
            };

            function bindConsentBanner() {
                var banner = document.querySelector('[data-cookie-consent-banner]');
                if (!banner) return;
                var acceptButton = banner.querySelector('[data-cookie-consent-accept]');
                var rejectButton = banner.querySelector('[data-cookie-consent-reject]');

                if (acceptButton) {
                    acceptButton.addEventListener('click', function () {
                        window.acceptCookieConsent();
                    });
                }

                if (rejectButton) {
                    rejectButton.addEventListener('click', function () {
                        window.rejectCookieConsent();
                    });
                }
            }

            if (hasMarketingConsent()) {
                loadMarketingTags();
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bindConsentBanner, { once: true });
            } else {
                bindConsentBanner();
            }
        })(window, document);
    </script>
    <title>Refúgio do Cuscuzeiro - Chácara de Aluguel por Temporada</title>
    <link rel="stylesheet" href="style.css?v=20260807-cookie-consent">
    <link rel="stylesheet" href="assets/css/reviews-public.css?v=1">
    <style>
        .cookie-consent-banner{display:grid !important;}
        .cookie-consent-actions{display:grid !important;grid-template-columns:1fr 1fr !important;gap:.6rem !important;align-items:stretch !important;}
        .cookie-consent-link{grid-column:1/-1 !important;display:block !important;}
        .cookie-consent-button{display:inline-flex !important;align-items:center;justify-content:center;min-height:46px;}
        .floating-buttons-container{position:fixed !important;display:flex !important;flex-direction:column !important;gap:1.5rem !important;right:2rem !important;bottom:2rem !important;z-index:9999 !important;visibility:visible !important;opacity:1 !important;}
        body.cookie-consent-visible .floating-buttons-container{bottom:11rem !important;}
        .float-btn{display:flex !important;}
        @media (max-width:480px){
            .cookie-consent-banner{left:.75rem !important;right:.75rem !important;bottom:.75rem !important;max-width:none !important;width:auto !important;}
            .cookie-consent-actions{grid-template-columns:1fr !important;}
            .floating-buttons-container{right:1rem !important;bottom:12rem !important;}
        }
    </style>
    <!-- Font Awesome CSS (defer loading, não-crítico) -->
    <link rel="preload" as="style" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&family=Playfair+Display:wght@700&display=swap">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <!-- Preload do logo; o vídeo da hero carrega apenas os metadados inicialmente -->
    <link rel="preload" as="image" href="assets/images/logo_crema.webp" type="image/webp">
</head>
<body<?= $showCookieBanner ? ' class="cookie-consent-visible"' : '' ?>>
    <!-- HEADER STICKY -->
    <header class="header scrolled">
         <div class="header-container">
        <div class="header-inner">
            <div class="logo-section">
                <picture>
                    <source media="(max-width: 768px)" srcset="assets/images/logo_crema.webp" type="image/webp">
                    <source media="(min-width: 769px)" srcset="assets/images/logo_crema.webp" type="image/webp">
                    <img class="logo-img" src="assets/images/logo_crema.webp" alt="Refúgio do Cuscuzeiro Logo" loading="eager" fetchpriority="low" decoding="async" width="50" height="50">
                </picture>
            </div>
            
            <nav class="navigation">
                <button class="menu-toggle" id="menuToggle">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <ul class="nav-menu" id="navMenu">
                    <li><a href="#chacara" class="nav-link">A Chácara</a></li>
                    <li><a href="#galeria" class="nav-link">Fotos</a></li>
                    <li><a href="#videos" class="nav-link">Vídeos</a></li>
                    <li><a href="#anlandia" class="nav-link">Analândia</a></li>
                    <li><a href="#localizacao" class="nav-link">Localização</a></li>
                    <li><a href="reserva/solicitar" class="nav-link nav-reserva">Reserva direta</a></li>
                </ul>
            </nav>
        </div>
         </div>
    </header>

    <!-- HERO SECTION -->
    <section class="hero" id="chacara">
        <div class="hero-background">
            <video
                class="hero-video"
                autoplay
                muted
                loop
                playsinline
                preload="metadata"
                poster="assets/images/imagens_a_rotacionar/Noturno.webp"
                aria-hidden="true"
                tabindex="-1"
            >
                <source src="assets/videos/IMG_0277.mp4" type="video/mp4">
            </video>
            <div class="hero-overlay"></div>
        </div>
        <div class="hero-content">
            <h2 class="hero-title">Bem-vindo ao Refúgio do Cuscuzeiro</h2>
            <p class="hero-subtitle">Natureza, conforto e exclusividade em um único lugar</p>
            <div class="hero-actions">
                <a class="cta-button cta-reserva" href="reserva/solicitar">Solicitar reserva direta</a>
                <button class="cta-button cta-secondary" onclick="scrollToComodidades()">Conheça Nossas Comodidades</button>
            </div>
        </div>
    </section>

    <!-- PROVA SOCIAL / AVALIAÇÕES -->
    <section class="reviews" id="reviews">
        <div class="section-container">
            <h2 class="section-title">O que nossos hóspedes dizem</h2>
            <p class="section-subtitle">Avaliações reais de quem já desfrutou do Refúgio do Cuscuzeiro</p>

            <div class="review-summary" data-review-summary hidden aria-live="polite">
                <strong><span data-review-average></span> de 5</strong>
                <span data-review-count></span>
            </div>
            <p class="review-empty" data-review-empty>As avaliações verificadas aparecerão aqui em breve.</p>

            <div class="review-carousel" data-source="api/avaliacoes/publicas" aria-roledescription="carousel" aria-label="Avaliações verificadas" hidden>
                <div class="review-slides preload" aria-live="polite"></div>
                <div class="review-controls">
                    <button class="review-prev" type="button" aria-label="Avaliação anterior">‹</button>
                    <div class="review-dots" role="tablist" aria-label="Navegar pelas avaliações"></div>
                    <button class="review-next" type="button" aria-label="Próxima avaliação">›</button>
                </div>
            </div>
        </div>
    </section>

    <!-- COMODIDADES SECTION -->
    <section class="comodidades" id="comodidades">
        <div class="section-container">
            <h2 class="section-title">Nossas Comodidades</h2>
            <p class="section-subtitle">Tudo que você precisa para uma estadia inesquecível</p>
            
            <div class="comodidades-grid">
                <div class="comodidade-card" id="quartos-card">
                    <div class="comodidade-icon">
                        <i class="fas fa-door-open"></i>
                    </div>
                    <h3>4 Suítes</h3>
                    <p>Quartos espaçosos e confortáveis para sua família</p>
                </div>

                <div class="comodidade-card" id="piscina-card">
                    <div class="comodidade-icon">
                        <i class="fas fa-water"></i>
                    </div>
                    <h3>Piscina</h3>
                    <p>Piscina bem cuidada para refrescantes mergulhos</p>
                </div>

                <div class="comodidade-card" id="jogos-card">
                    <div class="comodidade-icon">
                        <i class="fas fa-dice"></i>
                    </div>
                    <h3>Salão de Jogos</h3>
                    <p>Diversão garantida para toda a família</p>
                </div>

                <div class="comodidade-card" id="garagem-card">
                    <div class="comodidade-icon">
                        <i class="fas fa-car"></i>
                    </div>
                    <h3>Garagem Coberta</h3>
                    <p>Espaço seguro para 4 veículos</p>
                </div>

                <div class="comodidade-card" id="futebol-card">
                    <div class="comodidade-icon">
                        <i class="fas fa-futbol"></i>
                    </div>
                    <h3>Campinho de Futebol</h3>
                    <p>Diversão ao ar livre para os esportistas</p>
                </div>

                <div class="comodidade-card" id="quadra-card">
                    <div class="comodidade-icon">
                        <i class="fas fa-square"></i>
                    </div>
                    <h3>Quadra de Areia</h3>
                    <p>Perfeita para vôlei e outros esportes</p>
                </div>

                <div class="comodidade-card" id="churrasqueira-card">
                    <div class="comodidade-icon">
                        <i class="fas fa-fire"></i>
                    </div>
                    <h3>Churrasqueira</h3>
                    <p>Churrascarias memoráveis com amigos</p>
                </div>

                <div class="comodidade-card" id="varanda-card">
                    <div class="comodidade-icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <h3>Varanda Térrea</h3>
                    <p>Espaço perfeito para aproveitar a vista e o ar livre</p>
                </div>
            </div>
        </div>
    </section>

    <!-- GALERIA DE FOTOS -->
    <section class="galeria" id="galeria">
        <div class="section-container">
            <h2 class="section-title">Galeria de Fotos</h2>
            <p class="section-subtitle">Conheça cada detalhe da nossa chácara</p>

            <div class="galeria-grid">
                <div class="galeria-item">
                    <img src="assets/images/imagens_a_rotacionar/4_quartos.webp" alt="4 Quartos" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>4 Quartos</p></div>
                </div>

                <div class="galeria-item">
                    <img src="assets/images/imagens_a_rotacionar/campo_futebol.webp" alt="Campo de Futebol" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>Campo de Futebol</p></div>
                </div>

                <div class="galeria-item">
                    <img src="assets/images/galeria/churrasqueira.webp" alt="Churrasqueira" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>Churrasqueira</p></div>
                </div>

                <div class="galeria-item">
                    <img src="assets/images/galeria/garagem.webp" alt="Garagem Coberta" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>Garagem Coberta</p></div>
                </div>

                <div class="galeria-item">
                    <img src="assets/images/imagens_a_rotacionar/IMG_3401 (1).webp" alt="Ambiente" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>Ambiente</p></div>
                </div>

                <div class="galeria-item">
                    <img src="assets/images/imagens_a_rotacionar/IMG_3881.webp" alt="Piscina & Lazer" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>Piscina & Lazer</p></div>
                </div>

                <div class="galeria-item">
                    <img src="assets/images/imagens_a_rotacionar/IMG_3891.webp" alt="Interior" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>Interior</p></div>
                </div>

                <div class="galeria-item">
                    <img src="assets/images/imagens_a_rotacionar/IMG_4293.webp" alt="Suítes" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>Suítes</p></div>
                </div>

                <div class="galeria-item">
                    <img src="assets/images/imagens_a_rotacionar/IMG_4303.webp" alt="Comodidades" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>Comodidades</p></div>
                </div>

                <div class="galeria-item">
                    <img src="assets/images/imagens_a_rotacionar/IMG_4310.webp" alt="Piscina" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>Piscina</p></div>
                </div>

                <div class="galeria-item">
                    <img src="assets/images/imagens_a_rotacionar/IMG_4319.webp" alt="Espaço" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>Espaço</p></div>
                </div>

                <div class="galeria-item">
                    <img src="assets/images/imagens_a_rotacionar/IMG_4341.webp" alt="Detalhes" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>Detalhes</p></div>
                </div>

                <div class="galeria-item">
                    <img src="assets/images/imagens_a_rotacionar/IMG_4476.webp" alt="Chácara" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>Chácara</p></div>
                </div>

                <div class="galeria-item">
                    <img src="assets/images/galeria/IMG_8232.webp" alt="Ambiente" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>Ambiente</p></div>
                </div>

                <div class="galeria-item">
                    <img src="assets/images/galeria/IMG_8237.webp" alt="Espaço" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>Espaço</p></div>
                </div>

                <div class="galeria-item">
                    <img src="assets/images/galeria/IMG_8241.webp" alt="Detalhe" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>Detalhe</p></div>
                </div>

                <div class="galeria-item">
                    <img src="assets/images/galeria/IMG_8242.webp" alt="Lazer" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>Lazer</p></div>
                </div>

                <div class="galeria-item">
                    <img src="assets/images/galeria/IMG_8258.webp" alt="Ambiente" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>Ambiente</p></div>
                </div>

                <div class="galeria-item">
                    <img src="assets/images/galeria/IMG_8260 2.webp" alt="Espaço" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>Espaço</p></div>
                </div>

                <div class="galeria-item">
                    <img src="assets/images/galeria/IMG_8266.webp" alt="Detalhes" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>Detalhes</p></div>
                </div>

                <div class="galeria-item">
                    <img src="assets/images/galeria/piscina.webp" alt="Piscina" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>Piscina</p></div>
                </div>

                <div class="galeria-item">
                    <img src="assets/images/imagens_a_rotacionar/quadra_de_areia.webp" alt="Quadra de Areia" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>Quadra de Areia</p></div>
                </div>

                <div class="galeria-item">
                    <img src="assets/images/imagens_a_rotacionar/sala_de_jogos.webp" alt="Salão de Jogos" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>Salão de Jogos</p></div>
                </div>

                <div class="galeria-item">
                    <img src="assets/images/galeria/varanda.webp" alt="Varanda" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>Varanda</p></div>
                </div>

                <div class="galeria-item">
                    <img src="assets/images/galeria/IMG_6618.webp" alt="Suíte com cama de casal e bicama" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>Suíte com cama de casal</p></div>
                </div>

                <div class="galeria-item">
                    <img src="assets/images/galeria/IMG_6614.webp" alt="Bicama em uma das suítes" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>Conforto para a família</p></div>
                </div>

                <div class="galeria-item">
                    <img src="assets/images/galeria/foto_spa.webp" alt="Hidromassagem com vista para a natureza e o Morro do Cuscuzeiro" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>Hidromassagem com vista</p></div>
                </div>

                <div class="galeria-item">
                    <img src="assets/images/galeria/IMG_6608.webp" alt="Suíte aconchegante com cama de casal" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>Suíte aconchegante</p></div>
                </div>

                <div class="galeria-item">
                    <img src="assets/images/galeria/IMG_0553.webp" alt="Suíte com camas de solteiro e penteadeira" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>Suíte completa</p></div>
                </div>

                <div class="galeria-item">
                    <img src="assets/images/galeria/IMG_0550.webp" alt="Banheiro privativo com box de vidro" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>Banheiro da suíte</p></div>
                </div>

                <div class="galeria-item">
                    <img src="assets/images/galeria/IMG_0549.webp" alt="Bancada do banheiro preparada com toalhas" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>Detalhes do banheiro</p></div>
                </div>

                <div class="galeria-item">
                    <img src="assets/images/galeria/IMG_0544.webp" alt="Suíte com duas camas de solteiro" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>Suíte com camas de solteiro</p></div>
                </div>

                <div class="galeria-item">
                    <img src="assets/images/galeria/IMG_0308.webp" alt="Quarto com cama de casal e cama de solteiro" loading="lazy" decoding="async" width="400" height="300">
                    <div class="galeria-overlay"><p>Quarto para a família</p></div>
                </div>
            </div>
        </div>
    </section>

    <!-- VÍDEOS -->
    <section class="videos" id="videos" aria-labelledby="videos-title">
        <div class="section-container">
            <h2 class="section-title" id="videos-title">Vídeos do Refúgio</h2>
            <p class="section-subtitle">Sinta um pouco da atmosfera da chácara antes da sua estadia</p>

            <div class="video-carousel" data-video-carousel role="region" aria-roledescription="carousel" aria-label="Vídeos do Refúgio">
                <div class="video-viewport">
                    <div class="video-track" data-video-track>
                        <article class="video-slide" data-video-slide role="group" aria-roledescription="slide" aria-label="1 de 1">
                            <div class="video-slide-card">
                                <div class="video-slide-copy">
                                    <span class="video-kicker">Refúgio em movimento</span>
                                    <h3>Um olhar sobre a chácara</h3>
                                    <p>Conheça o clima de tranquilidade, natureza e lazer do Refúgio do Cuscuzeiro.</p>
                                    <small>Use os controles do vídeo para reproduzir, pausar ou assistir em tela cheia.</small>
                                </div>
                                <div class="video-frame">
                                    <video controls playsinline preload="metadata" poster="assets/images/imagens_a_rotacionar/Noturno.webp" aria-label="Vídeo apresentando o Refúgio do Cuscuzeiro">
                                        <source src="assets/videos/IMG_0277.mp4" type="video/mp4">
                                        Seu navegador não suporta a reprodução deste vídeo.
                                    </video>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>

                <button class="video-carousel-button video-prev" type="button" data-video-prev aria-label="Vídeo anterior">‹</button>
                <button class="video-carousel-button video-next" type="button" data-video-next aria-label="Próximo vídeo">›</button>

                <div class="video-carousel-footer">
                    <div class="video-dots" data-video-dots aria-label="Escolher vídeo"></div>
                    <span class="video-status" data-video-status aria-live="polite">1 / 1</span>
                </div>
            </div>
        </div>
    </section>

    <!-- SEÇÃO ANALÂNDIA -->
    <section class="anlandia" id="anlandia">
        <div class="section-container">
            <h2 class="section-title">Explore Analândia</h2>
            <p class="section-subtitle">Descubra a beleza e os atrativos da região</p>
            
            <div class="cards-grid">
                <div class="explore-card" id="cuscuzeiro-card">
                    <div class="card-image">
                        <img src="assets/images/cuscuzeiro.webp" alt="Cuscuzeiro" loading="lazy" decoding="async" width="400" height="300">
                        <span class="card-badge">Natureza</span>
                    </div>
                    <div class="card-content">
                        <h3>Cuscuzeiro</h3>
                        <p>Formação geológica única com paisagens espetaculares. Um passeio imperdível para apreciar a natureza selvagem de Analândia.</p>
                    </div>
                </div>

                <div class="explore-card" id="cachoeira-card">
                    <div class="card-image">
                        <img src="assets/images/cachoeira.webp" alt="Cachoeiras" loading="lazy" decoding="async" width="400" height="300">
                        <span class="card-badge">Aventura</span>
                    </div>
                    <div class="card-content">
                        <h3>Cachoeiras</h3>
                        <p>Diversos pontos de queda d'água perfeitos para banhos refrescantes e fotos inesquecíveis. Ideal para trilhas e contato com a natureza.</p>
                    </div>
                </div>

                <div class="explore-card" id="ecoturismo-card">
                    <div class="card-image">
                        <img src="assets/images/foto_ecoturismo.webp" alt="Ecoturismo" loading="lazy" decoding="async" width="400" height="300">
                        <span class="card-badge">Ecologia</span>
                    </div>
                    <div class="card-content">
                        <h3>Ecoturismo</h3>
                        <p>Trilhas ecológicas pela Serra Geral, observação de fauna e flora regional. Uma experiência enriquecedora em harmonia com a natureza.</p>
                    </div>
                </div>

                <div class="explore-card" id="gastronomia-card">
                    <div class="card-image">
                        <img src="assets/images/comida_Analandia.webp" alt="Gastronomia Local" loading="lazy" decoding="async" width="400" height="300">
                        <span class="card-badge">Cultura</span>
                    </div>
                    <div class="card-content">
                        <h3>Gastronomia Local</h3>
                        <p>Prove os sabores da culinária caipira. Restaurantes e bares com comidas típicas e autênticas que refletem a cultura da região.</p>
                    </div>
                </div>

                <div class="explore-card" id="ciclismo-card">
                    <div class="card-image">
                        <img src="assets/images/ciclismo.webp" alt="Ciclismo" loading="lazy" decoding="async" width="400" height="300">
                        <span class="card-badge">Aventura</span>
                    </div>
                    <div class="card-content">
                        <h3>Ciclismo</h3>
                        <p>Aventure-se em trilhas de bicicleta pela região. Paisagens incríveis e caminhos perfeitos para ciclistas de todos os níveis.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="reservation-promo" id="reserva-direta">
        <div class="section-container reservation-promo-inner">
            <div>
                <span class="reservation-kicker">Atendimento direto</span>
                <h2 class="section-title">Solicite sua reserva pelo nosso site</h2>
                <p>Envie as datas desejadas sem compromisso. Confirmamos a disponibilidade manualmente e, se aprovado, voce recebe uma pagina segura com a cobranca Pix.</p>
                <p class="reservation-disclaimer">Esta e uma solicitacao de reserva e esta sujeita a confirmacao de disponibilidade.</p>
            </div>
            <a class="reservation-button" href="reserva/solicitar">Solicitar reserva direta</a>
        </div>
    </section>

    <!-- LOCALIZAÇÃO - GOOGLE MAPS -->
    <section class="localizacao" id="localizacao">
        <div class="section-container">
            <h2 class="section-title">Localização</h2>
            <p class="section-subtitle">Encontre-nos facilmente em Analândia</p>
            
            <div class="mapa-container">
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3658.9029382895996!2d-47.6694005!3d-22.1337652!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1sRefúgio%20do%20Cuscuzeiro!2s-22.1337652,-47.6694005!5e0!3m2!1spt-BR!2sbr!4v1715701800000" width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>

            <div class="info-contact">
                <div class="info-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <div>
                        <h4>Endereço</h4>
                        <p>Analândia, São Paulo, Brasil</p>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-phone"></i>
                    <div>
                        <h4>Contato</h4>
                        <p><a href="https://wa.me/5516997376487" target="_blank" rel="noopener noreferrer">+55 16 99737-6487</a></p>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-envelope"></i>
                    <div>
                        <h4>Email</h4>
                        <p><a href="mailto:reservas@refugiodocuscuzeiro.com.br">reservas@refugiodocuscuzeiro.com.br</a></p>
                    </div>
                </div>
                <a href="https://www.instagram.com/refugiodocuscuzeiro/" target="_blank" rel="noopener noreferrer" class="info-item info-social">
                    <i class="fab fa-instagram"></i>
                    <div>
                        <h4>Instagram</h4>
                        <p>@refugiodocuscuzeiro</p>
                    </div>
                </a>
                <a href="https://www.tiktok.com/@refugio_do_cuscuzeiro" target="_blank" rel="noopener noreferrer" class="info-item info-social">
                    <i class="fab fa-tiktok"></i>
                    <div>
                        <h4>TikTok</h4>
                        <p>@refugio_do_cuscuzeiro</p>
                    </div>
                </a>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="section-container">
            <p>&copy; 2026 Refúgio do Cuscuzeiro. Todos os direitos reservados.</p>
            <p>Desenvolvido com <i class="fas fa-heart"></i> para sua comodidade</p>
            <p class="footer-legal"><a href="politicas/privacidade">Privacidade</a> · <a href="politicas/termos">Termos de serviço</a> · <a href="politicas/exclusao-de-dados">Exclusão de dados</a> · <a href="politicas/cancelamento">Cancelamento</a> · <a href="politicas/regras">Regras</a></p>
        </div>
    </footer>

    <?php if ($showCookieBanner): ?>
    <div class="cookie-consent-banner" data-cookie-consent-banner role="dialog" aria-live="polite" aria-label="Consentimento de cookies">
        <div class="cookie-consent-copy">
            <p class="cookie-consent-kicker">Cookies e consentimento</p>
            <strong>Usamos cookies para melhorar sua navegação.</strong>
            <p>Os essenciais mantêm o site funcionando. Com sua autorização, usamos cookies de análise e marketing para medir visitas e conversões.</p>
        </div>
        <div class="cookie-consent-actions">
            <a class="cookie-consent-link" href="<?= e(base_url('politicas/privacidade')) ?>">Ver política</a>
            <button type="button" class="cookie-consent-button secondary" data-cookie-consent-reject>Somente essenciais</button>
            <button type="button" class="cookie-consent-button primary" data-cookie-consent-accept>Aceitar Cookies</button>
        </div>
    </div>
    <?php endif; ?>

    <!-- BOTÕES FLUTUANTES (CALL TO ACTION) -->
   <div class="floating-buttons-container">
    <div class="float-button-wrapper" data-tooltip="Fale conosco no WhatsApp">
        <a href="https://wa.me/5516997376487"
           onclick="trackOutboundClick('Lead')"
           target="_blank" rel="noopener noreferrer" class="float-btn float-whatsapp" aria-label="Contato via WhatsApp">
            <span class="float-ping"></span>
            <span class="float-glow"></span>
            <svg viewBox="0 0 24 24" fill="currentColor" class="float-icon">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
            </svg>
        </a>
        <div class="float-tooltip">Fale conosco no WhatsApp</div>
    </div>

    <div class="float-button-wrapper" data-tooltip="Instagram">
        <a href="https://www.instagram.com/refugiodocuscuzeiro/" 
           onclick="trackOutboundClick('Lead')"
           target="_blank" rel="noopener noreferrer" class="float-btn float-instagram" aria-label="Instagram do Refúgio do Cuscuzeiro">
            <span class="float-ping"></span>
            <span class="float-glow"></span>
            <i class="fab fa-instagram"></i>
        </a>
        <div class="float-tooltip">Instagram</div>
    </div>

    <div class="float-button-wrapper" data-tooltip="Reservar no Airbnb">
        <a href="https://www.airbnb.com/h/refugiodocuscuzeiro" 
           onclick="trackOutboundClick('InitiateCheckout')"
           target="_blank" rel="noopener noreferrer" class="float-btn float-airbnb" aria-label="Reservar no Airbnb">
            <span class="float-ping"></span>
            <span class="float-glow"></span>
            <i class="fab fa-airbnb"></i>
        </a>
        <div class="float-tooltip">Reservar no Airbnb</div>
    </div>
</div>

    <script>
        // Menu Mobile Toggle
        const menuToggle = document.getElementById('menuToggle');
        const navMenu = document.getElementById('navMenu');

        menuToggle.addEventListener('click', () => {
            navMenu.classList.toggle('active');
            menuToggle.classList.toggle('active');
        });

        // Fechar menu ao clicar em um link
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                navMenu.classList.remove('active');
                menuToggle.classList.remove('active');
            });
        });

        // Scroll suave
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });

        // Função para scroll para comodidades
        function scrollToComodidades() {
            document.getElementById('comodidades').scrollIntoView({ behavior: 'smooth' });
        }

        // Floating Buttons - Tooltip Hover
        document.querySelectorAll('.float-button-wrapper').forEach(wrapper => {
            const btn = wrapper.querySelector('.float-btn');
            const tooltip = wrapper.querySelector('.float-tooltip');

            btn.addEventListener('mouseenter', () => {
                tooltip.classList.add('show');
            });

            btn.addEventListener('mouseleave', () => {
                tooltip.classList.remove('show');
            });
        });

        // Animação de aparecimento ao scroll (Intersection Observer)
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -100px 0px'
        };

        const revealElements = document.querySelectorAll('.comodidade-card, .explore-card, .galeria-item');

        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('fade-in');
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            revealElements.forEach(el => observer.observe(el));
        }
    </script>
    <!-- Lazy Loading Script (defer para não bloquear renderização) -->
    <script defer src="assets/js/reviews-carousel.js?v=2"></script>
    <script defer src="assets/js/videos-carousel.js?v=1"></script>
    <!-- Service Worker Registration (async pois é não-crítico) -->
    <script async src="assets/js/register-sw.js"></script>
</body>
</html>

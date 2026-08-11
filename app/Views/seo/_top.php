<?php
declare(strict_types=1);

$cookieConsent = (string) ($_COOKIE['refugio_cookie_consent'] ?? '');
$showCookieBanner = $cookieConsent === '';
$canonicalUrl = $site['url'] . $page['path'];
$socialImage = $site['url'] . $page['image'];
$isArticle = ($page['type'] ?? '') === 'article';
$isIndexable = ($page['indexable'] ?? true) === true && ($page['type'] ?? '') !== 'not-found';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="robots" content="<?= $isIndexable ? 'index,follow,max-image-preview:large' : 'noindex,follow' ?>">
    <title><?= e($page['title']) ?></title>
    <meta name="description" content="<?= e($page['description']) ?>">
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:type" content="<?= $isArticle ? 'article' : 'website' ?>">
    <meta property="og:site_name" content="<?= e($site['name']) ?>">
    <meta property="og:title" content="<?= e($page['title']) ?>">
    <meta property="og:description" content="<?= e($page['description']) ?>">
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <meta property="og:image" content="<?= e($socialImage) ?>">
    <meta property="og:image:alt" content="<?= e($page['alt']) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($page['title']) ?>">
    <meta name="twitter:description" content="<?= e($page['description']) ?>">
    <meta name="twitter:image" content="<?= e($socialImage) ?>">
<?php if ($isArticle): ?>
    <meta property="article:published_time" content="<?= e($page['date']) ?>">
    <meta property="article:modified_time" content="<?= e($page['modified']) ?>">
<?php endif; ?>
    <link rel="icon" type="image/png" href="/assets/images/logo_refugio.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@700&display=swap">
    <link rel="stylesheet" href="/assets/css/seo-content.css?v=20260811-city-seo">
    <meta name="facebook-domain-verification" content="sm11y3l28i1v2s2g36avcu3z3vejtg">
    <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR) ?></script>
    <script>
    (function(window, document) {
        var googleAdsId = 'AW-18158463231';
        var googleConversionId = 'AW-18158463231/1kiHCPjr7q0cEP_R0NJD';
        var metaPixelId = '1375499027959251';
        var tiktokPixelId = 'D9FTH53C77UBS5FSK8MG';
        var cookieName = 'refugio_cookie_consent';
        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function(){ window.dataLayer.push(arguments); };
        if (!window.fbq) {
            var fbq = window.fbq = function(){ fbq.callMethod ? fbq.callMethod.apply(fbq, arguments) : fbq.queue.push(arguments); };
            if (!window._fbq) window._fbq = fbq;
            fbq.push = fbq; fbq.loaded = true; fbq.version = '2.0'; fbq.queue = [];
        }
        function getCookie(name) {
            var prefix = name + '='; var parts = document.cookie ? document.cookie.split('; ') : [];
            for (var i = 0; i < parts.length; i++) if (parts[i].indexOf(prefix) === 0) return decodeURIComponent(parts[i].slice(prefix.length));
            return '';
        }
        function loadScript(src, id) {
            if (document.getElementById(id)) return;
            var script = document.createElement('script'); script.id = id; script.async = true; script.src = src; document.head.appendChild(script);
        }
        function hasConsent(){ return getCookie(cookieName) === 'accepted'; }
        function loadTikTok(){
            if (window.__tiktokTagsLoaded) return; window.__tiktokTagsLoaded = true;
            var ttq = window.ttq = window.ttq || [];
            ttq.methods = ['page','track','identify','instances','debug','on','off','once','ready','alias','group','enableCookie','disableCookie','holdConsent','revokeConsent','grantConsent'];
            ttq.setAndDefer = function(target, method){ target[method] = function(){ target.push([method].concat(Array.prototype.slice.call(arguments))); }; };
            for (var i = 0; i < ttq.methods.length; i++) ttq.setAndDefer(ttq, ttq.methods[i]);
            ttq.load = function(id, options){ var src='https://analytics.tiktok.com/i18n/pixel/events.js'; ttq._i=ttq._i||{}; ttq._i[id]=[]; ttq._i[id]._u=src; ttq._t=ttq._t||{}; ttq._t[id]=+new Date(); ttq._o=ttq._o||{}; ttq._o[id]=options||{}; loadScript(src+'?sdkid='+id+'&lib=ttq','tiktok-pixel-loader'); };
            ttq.load(tiktokPixelId); ttq.page();
        }
        function loadMarketing(){
            if (window.__marketingTagsLoaded || !hasConsent()) return; window.__marketingTagsLoaded = true;
            loadScript('https://www.googletagmanager.com/gtm.js?id=GTM-5D4WKTM7','gtm-loader');
            loadScript('https://www.googletagmanager.com/gtag/js?id='+googleAdsId,'google-ads-loader');
            window.gtag('js', new Date()); window.gtag('config', googleAdsId);
            loadScript('https://connect.facebook.net/en_US/fbevents.js','meta-pixel-loader');
            window.fbq('init', metaPixelId); window.fbq('track','PageView'); window.fbq('track','ViewContent'); loadTikTok();
        }
        function choose(value){
            document.cookie=cookieName+'='+value+'; Max-Age=31536000; Path=/; SameSite=Lax'; if(value==='accepted') loadMarketing();
            var banner=document.querySelector('[data-cookie-consent-banner]'); if(banner) banner.remove(); document.body.classList.remove('cookie-consent-visible');
        }
        window.trackOutboundClick=function(eventName){ if(!hasConsent()) return true; loadMarketing(); if(eventName && typeof window.fbq==='function') window.fbq('track',eventName); window.gtag('event','conversion',{send_to:googleConversionId,value:1.0,currency:'BRL'}); return true; };
        window.refugioConsent=choose;
        if(hasConsent()) loadMarketing();
    })(window, document);
    </script>
</head>
<body<?= $showCookieBanner ? ' class="cookie-consent-visible"' : '' ?><?= ($page['type'] ?? '') === 'city-landing' ? ' data-seo-landing-type="city" data-seo-origin-city="' . e($page['slug']) . '"' : '' ?>>
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
<header class="seo-header">
    <div class="seo-container seo-header-inner">
        <a class="seo-logo" href="/" aria-label="Refúgio do Cuscuzeiro — página inicial">
            <img src="/assets/images/logo_crema.webp" alt="" width="52" height="52" loading="eager" decoding="async">
            <span>Refúgio do Cuscuzeiro</span>
        </a>
        <button class="seo-menu-toggle" type="button" aria-expanded="false" aria-controls="seo-menu"><span></span><span></span><span></span><span class="sr-only">Abrir menu</span></button>
        <nav aria-label="Navegação principal">
            <ul class="seo-menu" id="seo-menu">
                <li><a href="/a-chacara">A chácara</a></li>
                <li><a href="/alugar-chacara/">Hospedagem</a></li>
                <li><a href="/analandia/">Conheça Analândia</a></li>
                <li><a href="/blog/">Blog</a></li>
                <li><a class="seo-nav-cta" href="/reserva/solicitar">Reservar</a></li>
            </ul>
        </nav>
    </div>
</header>
<div id="conteudo"></div>

<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','GTM-5D4WKTM7');</script>
    <!-- End Google Tag Manager -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="<?= e($robots ?? 'noindex,nofollow') ?>">
    <meta name="description" content="<?= e($metaDescription ?? 'Reservas do Refúgio do Cuscuzeiro em Analândia/SP') ?>">
    <?php if (!empty($canonical)): ?><link rel="canonical" href="<?= e($canonical) ?>"><?php endif; ?>
    <title><?= e($title ?? 'Reservas') ?> | Refugio do Cuscuzeiro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/reservas.css?v=2')) ?>">
    <?php if (($robots ?? '') === 'index,follow'): ?><link rel="stylesheet" href="<?= e(base_url('assets/css/policies.css?v=1')) ?>"><?php endif; ?>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/reviews.css?v=1')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/guest-portal.css?v=3')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/guest-portal-upload.css?v=1')) ?>">
</head>
<body class="reservation-app">
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-5D4WKTM7" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
<header class="reservation-header"><a href="<?= e(base_url()) ?>" aria-label="Voltar ao inicio"><img src="<?= e(base_url('assets/images/logo_crema.webp')) ?>" alt="Refugio do Cuscuzeiro" width="58" height="58"><span>Refugio do Cuscuzeiro</span></a></header>
<main class="reservation-main">

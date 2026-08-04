<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="pt-BR">
<head>
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
    <link rel="stylesheet" href="<?= e(base_url('assets/css/guest-portal.css?v=2')) ?>">
</head>
<body class="reservation-app">
<header class="reservation-header"><a href="<?= e(base_url()) ?>" aria-label="Voltar ao inicio"><img src="<?= e(base_url('assets/images/logo_crema.webp')) ?>" alt="Refugio do Cuscuzeiro" width="58" height="58"><span>Refugio do Cuscuzeiro</span></a></header>
<main class="reservation-main">

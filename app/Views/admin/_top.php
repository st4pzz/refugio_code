<?php
declare(strict_types=1);
$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/admin'), PHP_URL_PATH) ?: '/admin';
$active = static fn(string $path): string => ($path === '/admin' ? rtrim($requestPath, '/') === '/admin' : str_starts_with($requestPath, $path)) ? 'active' : '';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
    <title><?= e($title ?? 'Painel') ?> | Refúgio</title>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/admin.css?v=5')) ?>"><link rel="stylesheet" href="<?= e(base_url('assets/css/admin-operations.css?v=7')) ?>"><link rel="stylesheet" href="<?= e(base_url('assets/css/admin-calendar.css?v=1')) ?>"><link rel="stylesheet" href="<?= e(base_url('assets/css/reviews-admin.css?v=1')) ?>">
</head>
<body class="admin-app">
<div class="admin-shell">
    <aside class="admin-sidebar" data-admin-nav>
        <a class="admin-brand" href="<?= e(base_url('admin')) ?>"><span class="brand-mark">R</span><span>Refúgio<small>Central de operação</small></span></a>
        <nav aria-label="Navegação principal">
            <?php if(can('dashboard.view')): ?><a class="<?= $active('/admin') ?>" href="<?= e(base_url('admin')) ?>"><span aria-hidden="true">⌂</span>Dashboard</a><?php endif; ?>
            <?php if(can('reservas.view')): ?><a class="<?= $active('/admin/reservas') ?>" href="<?= e(base_url('admin/reservas')) ?>"><span aria-hidden="true">▣</span>Reservas</a><?php endif; ?>
            <?php if(can('reservas.view')): ?><a class="<?= $active('/admin/pedidos-whatsapp') ?>" href="<?= e(base_url('admin/pedidos-whatsapp')) ?>"><span aria-hidden="true">WA</span>Pedidos WhatsApp</a><?php endif; ?>
            <?php if(can('calendar.view')): ?><a class="<?= $active('/admin/calendario') ?>" href="<?= e(base_url('admin/calendario')) ?>"><span aria-hidden="true">▦</span>Calendário</a><?php endif; ?>
            <?php if(can('pricing.view')): ?><a class="<?= $active('/admin/precos') ?>" href="<?= e(base_url('admin/precos')) ?>"><span aria-hidden="true">R$</span>Preços</a><?php endif; ?>
            <?php if(can('quotes.view')): ?><a class="<?= $active('/admin/orcamentos') ?>" href="<?= e(base_url('admin/orcamentos')) ?>"><span aria-hidden="true">◇</span>Orçamentos</a><?php endif; ?>
            <?php if(can('contracts.view')): ?><a class="<?= $active('/admin/contratos') ?>" href="<?= e(base_url('admin/contratos')) ?>"><span aria-hidden="true">§</span>Contratos</a><?php endif; ?>
            <?php if(can('precheckin.view')): ?><a class="<?= $active('/admin/pre-checkins') ?>" href="<?= e(base_url('admin/pre-checkins')) ?>"><span aria-hidden="true">✓</span>Pré-check-in</a><?php endif; ?>
            <?php if(can('automation.view')): ?><a class="<?= $active('/admin/automacoes') ?>" href="<?= e(base_url('admin/automacoes')) ?>"><span aria-hidden="true">↻</span>Automações</a><?php endif; ?>
            <?php if(can('clientes.view')): ?><a class="<?= $active('/admin/clientes') ?>" href="<?= e(base_url('admin/clientes')) ?>"><span aria-hidden="true">♙</span>Clientes</a><?php endif; ?>
            <?php if(can('conversas.view')): ?><a class="<?= $active('/admin/conversas') ?>" href="<?= e(base_url('admin/conversas')) ?>"><span aria-hidden="true">◌</span>Conversas</a><?php endif; ?>
            <?php if(can('marketing.view')): ?><a class="<?= $active('/admin/marketing') ?>" href="<?= e(base_url('admin/marketing')) ?>"><span aria-hidden="true">↗</span>Marketing</a><?php endif; ?>
            <?php if(can('financeiro.view')): ?><a class="<?= $active('/admin/financeiro') ?>" href="<?= e(base_url('admin/financeiro')) ?>"><span aria-hidden="true">$</span>Financeiro</a><?php endif; ?>
            <?php if(can('avaliacoes.view')): ?><a class="<?= $active('/admin/avaliacoes') ?>" href="<?= e(base_url('admin/avaliacoes')) ?>"><span aria-hidden="true">☆</span>Avaliações</a><?php endif; ?>
            <?php if(can('configuracoes.view')): ?><a class="<?= $active('/admin/configuracoes') ?>" href="<?= e(base_url('admin/configuracoes/integracoes')) ?>"><span aria-hidden="true">⚙</span>Configurações</a><?php endif; ?>
            <?php if(can('property_settings.manage')): ?><a class="<?= $active('/admin/configuracoes/propriedade') ?>" href="<?= e(base_url('admin/configuracoes/propriedade')) ?>"><span aria-hidden="true">⌂</span>Propriedade</a><?php endif; ?>
        </nav>
        <div class="sidebar-footer"><span><?= e($_SESSION['admin_name'] ?? 'Administrador') ?></span><?php if(can('reservas.view')): ?><a href="<?= e(base_url('admin/calendario')) ?>">Calendário</a><?php endif; ?><form action="<?= e(base_url('admin/logout')) ?>" method="post"><?= csrf_field() ?><button type="submit">Sair</button></form></div>
    </aside>
    <div class="admin-content">
        <header class="admin-mobile-header"><button class="nav-toggle" type="button" data-nav-toggle aria-expanded="false" aria-label="Abrir menu">☰</button><strong>Refúgio</strong><span><?= e($_SESSION['admin_name'] ?? '') ?></span></header>
        <main class="admin-main">
            <?php if ($message=flash('success')): ?><div class="admin-alert success" role="status"><?= e($message) ?></div><?php endif; ?>
            <?php if ($message=flash('error')): ?><div class="admin-alert error" role="alert"><?= e($message) ?></div><?php endif; ?>

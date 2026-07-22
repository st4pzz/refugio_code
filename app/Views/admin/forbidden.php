<?php declare(strict_types=1); require BASE_PATH . '/app/Views/admin/_top.php'; ?>
<section class="admin-panel empty-state">
    <p class="eyebrow">Permissão necessária</p>
    <h1>Acesso negado</h1>
    <p>Seu perfil não possui autorização para esta área ou operação.</p>
    <a class="admin-secondary" href="<?= e(base_url('admin')) ?>">Voltar ao painel</a>
</section>
<?php require BASE_PATH . '/app/Views/admin/_bottom.php'; ?>

<?php
$title = 'Reservas';
$reviewInviteUrl = flash('review_invite_url');
require __DIR__ . '/_top.php';
?>
<div class="page-heading"><div><p class="eyebrow">Gerenciamento</p><h1>Reservas</h1></div></div>

<?php if ($reviewInviteUrl): ?>
    <div class="export-link-card" role="status">
        <strong>Convite criado. Copie o link privado agora.</strong>
        <p>O envio foi processado pelos canais configurados; confira o resultado acima. O endereço completo não será exibido novamente.</p>
        <div class="copy-link-row">
            <input id="review-invite-url" type="text" readonly value="<?= e($reviewInviteUrl) ?>" aria-label="Link privado da avaliação">
            <button class="admin-secondary" type="button" data-copy-target="review-invite-url" data-copy-feedback="review-invite-copy-feedback">Copiar link</button>
            <a class="admin-secondary" href="<?= e($reviewInviteUrl) ?>" target="_blank" rel="noopener">Abrir formulário</a>
        </div>
        <small id="review-invite-copy-feedback" class="copy-feedback" aria-live="polite"></small>
    </div>
<?php endif; ?>

<form class="filter-bar" method="get" action="<?= e(base_url('admin/reservas')) ?>">
    <input type="search" name="q" placeholder="Nome, codigo, e-mail ou telefone" value="<?= e($filters['q'] ?? '') ?>">
    <select name="status"><option value="">Todos os status</option><?php foreach (ReservationStatus::cases() as $s): ?><option value="<?= e($s->value) ?>" <?= ($filters['status'] ?? '') === $s->value ? 'selected' : '' ?>><?= e($s->label()) ?></option><?php endforeach; ?></select>
    <select name="origem"><option value="">Todas as origens</option><?php foreach (['SITE_DIRETO','AIRBNB','BOOKING','MANUAL'] as $o): ?><option <?= ($filters['origem'] ?? '') === $o ? 'selected' : '' ?>><?= e($o) ?></option><?php endforeach; ?></select>
    <input type="date" name="inicio" value="<?= e($filters['inicio'] ?? '') ?>">
    <input type="date" name="fim" value="<?= e($filters['fim'] ?? '') ?>">
    <select name="ordem"><option value="created_at">Mais recentes</option><option value="checkin" <?= ($filters['ordem'] ?? '') === 'checkin' ? 'selected' : '' ?>>Check-in</option><option value="nome_cliente" <?= ($filters['ordem'] ?? '') === 'nome_cliente' ? 'selected' : '' ?>>Nome</option></select>
    <button class="admin-primary" type="submit">Filtrar</button>
</form>

<section class="admin-panel"><div class="table-wrap"><table>
    <thead><tr><th>Codigo</th><th>Cliente</th><th>Contato</th><th>Datas</th><th>Origem</th><th>Status</th><?php if (can('avaliacoes.manage')): ?><th>Avaliação</th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($result['items'] as $r): $reviewAction = $reviewActions[(int) $r['id']] ?? null; ?>
        <tr data-href="<?= e(base_url('admin/reservas/' . $r['id'])) ?>">
            <td><a href="<?= e(base_url('admin/reservas/' . $r['id'])) ?>"><?= e($r['codigo']) ?></a></td>
            <td><?= e($r['nome_cliente']) ?></td>
            <td><?= e($r['telefone']) ?><br><small><?= e($r['email']) ?></small></td>
            <td><?= date('d/m/Y', strtotime($r['checkin'])) ?><br><?= date('d/m/Y', strtotime($r['checkout'])) ?></td>
            <td><?= e($r['origem']) ?></td>
            <td><span class="admin-status status-<?= strtolower($r['status']) ?>"><?= e(ReservationStatus::from($r['status'])->label()) ?></span></td>
            <?php if (can('avaliacoes.manage')): ?><td>
                <?php if ($reviewAction && $reviewAction['available']): ?>
                    <form method="post" action="<?= e(base_url('admin/reservas/' . $r['id'] . '/' . $reviewAction['action'])) ?>" data-confirm="Disparar o template de avaliação para este hóspede agora?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="return_to" value="admin/reservas">
                        <button class="<?= $reviewAction['action'] === 'enviar-convite-avaliacao' ? 'admin-primary' : 'admin-secondary' ?>" type="submit"><?= e($reviewAction['label']) ?></button>
                    </form>
                <?php elseif ($reviewAction && $reviewAction['has_review']): ?>
                    <small>Avaliação recebida</small>
                <?php elseif ($reviewAction && $reviewAction['invitation_status']): ?>
                    <small>Convite <?= e(strtolower($reviewAction['invitation_status'])) ?></small>
                <?php else: ?>
                    <small>Indisponível</small>
                <?php endif; ?>
            </td><?php endif; ?>
        </tr>
    <?php endforeach; ?>
    <?php if (!$result['items']): ?><tr><td colspan="<?= can('avaliacoes.manage') ? 7 : 6 ?>">Nenhuma reserva encontrada.</td></tr><?php endif; ?>
    </tbody>
</table></div></section>

<?php $pages = (int) ceil($result['total'] / $result['per_page']); if ($pages > 1): ?>
    <nav class="pagination"><?php for ($i = 1; $i <= $pages; $i++): ?><a class="<?= $i === $page ? 'active' : '' ?>" href="?<?= e(http_build_query(array_merge($filters, ['pagina' => $i]))) ?>"><?= $i ?></a><?php endfor; ?></nav>
<?php endif; ?>
<?php require __DIR__ . '/_bottom.php'; ?>

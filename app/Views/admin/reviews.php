<?php
$title = 'Avaliações';
require __DIR__ . '/_top.php';
?>
<div class="page-heading"><div><p class="eyebrow">Moderação</p><h1>Avaliações verificadas</h1></div></div>
<form class="filter-bar review-filter-bar" method="get" action="<?= e(base_url('admin/avaliacoes')) ?>">
    <input type="search" name="q" placeholder="Hóspede ou código" value="<?= e($filters['q'] ?? '') ?>">
    <select name="status"><option value="">Todos os status</option><?php foreach (\Refugio\Models\ReviewStatus::cases() as $item): ?><option value="<?= e($item->value) ?>" <?= ($filters['status'] ?? '') === $item->value ? 'selected' : '' ?>><?= e($item->label()) ?></option><?php endforeach; ?></select>
    <select name="nota"><option value="">Todas as notas</option><?php for ($note = 5; $note >= 1; $note--): ?><option value="<?= $note ?>" <?= (string) ($filters['nota'] ?? '') === (string) $note ? 'selected' : '' ?>><?= $note ?> estrelas</option><?php endfor; ?></select>
    <select name="origem"><option value="">Todas as origens</option><?php foreach (['SITE_DIRETO','AIRBNB','BOOKING','MANUAL'] as $origin): ?><option value="<?= e($origin) ?>" <?= ($filters['origem'] ?? '') === $origin ? 'selected' : '' ?>><?= e($origin) ?></option><?php endforeach; ?></select>
    <input type="date" name="inicio" value="<?= e($filters['inicio'] ?? '') ?>" aria-label="Data inicial">
    <input type="date" name="fim" value="<?= e($filters['fim'] ?? '') ?>" aria-label="Data final">
    <button class="admin-primary" type="submit">Filtrar</button>
</form>
<section class="admin-panel"><div class="table-wrap"><table>
    <thead><tr><th>Envio / aprovação</th><th>Reserva / estadia</th><th>Publicação</th><th>Nota</th><th>Comentário</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($result['items'] as $review): $status = \Refugio\Models\ReviewStatus::from($review['status']); ?>
        <tr data-href="<?= e(base_url('admin/avaliacoes/' . $review['id'])) ?>">
            <td><?= date('d/m/Y H:i', strtotime($review['enviada_em'])) ?><br><small>Aprovação: <?= $review['aprovada_em'] ? date('d/m/Y H:i', strtotime($review['aprovada_em'])) : '—' ?></small></td>
            <td><a href="<?= e(base_url('admin/avaliacoes/' . $review['id'])) ?>"><?= e($review['codigo']) ?></a><br><small><?= e($review['nome_cliente']) ?> · <?= e($review['origem']) ?><br><?= date('d/m/Y', strtotime($review['checkin'])) ?> a <?= date('d/m/Y', strtotime($review['checkout'])) ?></small></td>
            <td><?= e($review['nome_exibicao']) ?><br><span class="verified-badge">✓ Estadia verificada</span></td>
            <td><span class="review-stars" aria-label="<?= (int) $review['nota_geral'] ?> de 5 estrelas"><?= str_repeat('★', (int) $review['nota_geral']) . str_repeat('☆', 5 - (int) $review['nota_geral']) ?></span></td>
            <td><?= e(mb_substr($review['comentario'], 0, 120)) ?><?= mb_strlen($review['comentario']) > 120 ? '…' : '' ?></td>
            <td><span class="admin-status status-<?= strtolower($status->value) ?>"><?= e($status->label()) ?></span></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$result['items']): ?><tr><td colspan="6">Nenhuma avaliação encontrada.</td></tr><?php endif; ?>
    </tbody>
</table></div></section>
<?php $pages = (int) ceil($result['total'] / $result['per_page']); if ($pages > 1): ?><nav class="pagination" aria-label="Paginação"><?php for ($number = 1; $number <= $pages; $number++): ?><a class="<?= $number === $result['page'] ? 'active' : '' ?>" href="?<?= e(http_build_query(array_merge($filters, ['pagina' => $number]))) ?>"><?= $number ?></a><?php endfor; ?></nav><?php endif; ?>
<?php require __DIR__ . '/_bottom.php'; ?>

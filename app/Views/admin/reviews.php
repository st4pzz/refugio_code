<?php
$title = 'Avaliações';
$originLabels = ['SITE_DIRETO'=>'Site direto','GOOGLE'=>'Google','BOOKING'=>'Booking.com','AIRBNB'=>'Airbnb'];
$googleConfigured = !empty($google['configured']);
require __DIR__ . '/_top.php';
?>
<div class="page-heading"><div><p class="eyebrow">Moderação</p><h1>Avaliações</h1><p>Reúna avaliações diretas e de outras plataformas em um único fluxo de moderação.</p></div></div>

<?php if (can('avaliacoes.manage')): ?>
<section class="admin-panel review-import-panel">
    <div class="panel-heading"><div><p class="eyebrow">Fontes externas</p><h2>Integrações e importação</h2></div></div>
    <div class="review-source-grid">
        <article class="review-source-card <?= $googleConfigured ? 'is-connected' : '' ?>">
            <header><span class="source-mark google" aria-hidden="true">G</span><div><h3>Google Maps</h3><small><?= $googleConfigured ? 'Places API configurada' : 'Configuração pendente' ?></small></div></header>
            <p>Consulta ao vivo as até 5 avaliações selecionadas por relevância pelo Google. Elas também aparecem na vitrine pública sem serem armazenadas no banco.</p>
            <?php if (!$googleConfigured): ?>
                <div class="source-warning">Configure GOOGLE_PLACES_API_KEY e GOOGLE_PLACES_PLACE_ID no ambiente do site.</div>
            <?php else: ?>
                <a class="admin-primary" href="<?= e(base_url('admin/avaliacoes?google=1#google-live-reviews')) ?>">Puxar avaliações</a>
            <?php endif; ?>
        </article>
        <article class="review-source-card">
            <header><span class="source-mark booking" aria-hidden="true">B.</span><div><h3>Booking.com</h3><small>Cadastro manual</small></div></header>
            <p>A API de avaliações do Booking é restrita a parceiros e não permite republicação na página pública. Cadastre somente avaliações com autorização.</p>
            <a class="admin-secondary" href="#cadastro-externo">Cadastrar avaliação</a>
        </article>
        <article class="review-source-card">
            <header><span class="source-mark airbnb" aria-hidden="true">A</span><div><h3>Airbnb</h3><small>Cadastro manual</small></div></header>
            <p>O acesso à API depende do programa de parceiros do Airbnb. O cadastro manual exige URL da origem e autorização para republicar.</p>
            <a class="admin-secondary" href="#cadastro-externo">Cadastrar avaliação</a>
        </article>
    </div>
</section>

<?php if (!empty($google['requested'])): ?>
<section class="admin-panel google-live-panel" id="google-live-reviews">
    <div class="panel-heading"><div><p class="eyebrow">Consulta ao vivo</p><h2>Avaliações do Google Maps</h2></div></div>
    <?php if (!empty($google['error'])): ?>
        <div class="source-error">Não foi possível consultar o Google: <?= e($google['error']) ?></div>
    <?php elseif (!empty($google['live'])): $googleLive=$google['live']; ?>
        <div class="google-live-summary"><strong><?= e($googleLive['place_name']) ?></strong><span><?= $googleLive['rating'] !== null ? e(number_format((float)$googleLive['rating'],1,',','.')).' ★ · ' : '' ?><?= (int)$googleLive['total'] ?> avaliação(ões) no Google</span></div>
        <div class="google-live-grid">
            <?php foreach ($googleLive['items'] as $googleReview): ?>
                <article class="google-live-review">
                    <span class="review-stars" aria-label="<?= (int)$googleReview['nota_geral'] ?> de 5 estrelas"><?= str_repeat('★',(int)$googleReview['nota_geral']).str_repeat('☆',5-(int)$googleReview['nota_geral']) ?></span>
                    <blockquote><?= nl2br(e($googleReview['comentario'])) ?></blockquote>
                    <footer><strong><?= e($googleReview['nome_exibicao']) ?></strong><?php if(!empty($googleReview['avaliacao_em'])): ?><span><?= date('d/m/Y',strtotime($googleReview['avaliacao_em'])) ?></span><?php endif; ?><?php if(!empty($googleReview['external_url'])): ?><a href="<?= e($googleReview['external_url']) ?>" target="_blank" rel="noopener noreferrer">Ver no Google Maps</a><?php endif; ?></footer>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if (!$googleLive['items']): ?><p>Nenhuma avaliação com comentário foi retornada pelo Google.</p><?php endif; ?>
        <p class="privacy-notice">Dados fornecidos pelo Google Maps e consultados neste momento. O sistema não armazena essas avaliações.</p>
    <?php endif; ?>
</section>
<?php endif; ?>

<details class="admin-panel review-manual-panel" id="cadastro-externo">
    <summary><span><strong>Cadastrar avaliação do Booking ou Airbnb</strong><small>A avaliação entrará como pendente antes de aparecer no site.</small></span></summary>
    <form class="admin-form review-manual-form" method="post" action="<?= e(base_url('admin/avaliacoes/importar-manual')) ?>">
        <?= csrf_field() ?>
        <div class="form-grid">
            <label>Plataforma<select name="provider" required><option value="">Selecione</option><option value="BOOKING">Booking.com</option><option value="AIRBNB">Airbnb</option></select></label>
            <label>Nome de exibição<input name="name" maxlength="160" required placeholder="Ex.: Maria S."></label>
            <label>Nota para exibição (1 a 5)<select name="rating" required><option value="">Selecione</option><?php for($note=5;$note>=1;$note--):?><option value="<?= $note ?>"><?= $note ?> estrela<?= $note===1?'':'s' ?></option><?php endfor;?></select><small>No Booking, converta proporcionalmente a nota de 10 para 5.</small></label>
            <label>Data da avaliação<input type="date" name="reviewed_at" max="<?= date('Y-m-d') ?>" required></label>
            <label class="full">URL da avaliação original<input type="url" name="external_url" maxlength="1000" required placeholder="https://..."></label>
            <label class="full">Comentário original<textarea name="comment" rows="5" maxlength="5000" minlength="10" required></textarea></label>
        </div>
        <label class="authorization-check"><input type="checkbox" name="publication_authorized" value="1" required><span>Confirmo que tenho autorização do autor e da plataforma, quando aplicável, para republicar este conteúdo no site.</span></label>
        <button class="admin-primary" type="submit">Salvar para moderação</button>
    </form>
</details>
<?php endif; ?>

<form class="filter-bar review-filter-bar" method="get" action="<?= e(base_url('admin/avaliacoes')) ?>">
    <input type="search" name="q" placeholder="Hóspede, código ou ID externo" value="<?= e($filters['q'] ?? '') ?>">
    <select name="status"><option value="">Todos os status</option><?php foreach (\Refugio\Models\ReviewStatus::cases() as $item): ?><option value="<?= e($item->value) ?>" <?= ($filters['status'] ?? '') === $item->value ? 'selected' : '' ?>><?= e($item->label()) ?></option><?php endforeach; ?></select>
    <select name="nota"><option value="">Todas as notas</option><?php for ($note = 5; $note >= 1; $note--): ?><option value="<?= $note ?>" <?= (string) ($filters['nota'] ?? '') === (string) $note ? 'selected' : '' ?>><?= $note ?> estrelas</option><?php endfor; ?></select>
    <select name="origem"><option value="">Todas as origens</option><?php foreach ($originLabels as $origin=>$label): ?><option value="<?= e($origin) ?>" <?= ($filters['origem'] ?? '') === $origin ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
    <input type="date" name="inicio" value="<?= e($filters['inicio'] ?? '') ?>" aria-label="Data inicial">
    <input type="date" name="fim" value="<?= e($filters['fim'] ?? '') ?>" aria-label="Data final">
    <button class="admin-primary" type="submit">Filtrar</button>
</form>
<section class="admin-panel"><div class="table-wrap"><table>
    <thead><tr><th>Envio / aprovação</th><th>Origem / estadia</th><th>Publicação</th><th>Nota</th><th>Comentário</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($result['items'] as $review): $status = \Refugio\Models\ReviewStatus::from($review['status']); $external=empty($review['reserva_id']); ?>
        <tr data-href="<?= e(base_url('admin/avaliacoes/' . $review['id'])) ?>">
            <td><?= date('d/m/Y H:i', strtotime($review['enviada_em'])) ?><br><small>Aprovação: <?= $review['aprovada_em'] ? date('d/m/Y H:i', strtotime($review['aprovada_em'])) : '—' ?></small></td>
            <td><a href="<?= e(base_url('admin/avaliacoes/' . $review['id'])) ?>"><?= e($originLabels[$review['origem']] ?? $review['origem']) ?></a><br><small><?= e($review['nome_cliente']) ?><?php if(!$external): ?> · <?= e($review['codigo']) ?><br><?= date('d/m/Y', strtotime($review['checkin'])) ?> a <?= date('d/m/Y', strtotime($review['checkout'])) ?><?php else: ?><br>Avaliação externa<?php endif; ?></small></td>
            <td><?= e($review['nome_exibicao']) ?><br><span class="verified-badge"><?= $external ? '↗ Origem identificada' : '✓ Estadia verificada' ?></span></td>
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

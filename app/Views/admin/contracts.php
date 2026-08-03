<?php
$title = 'Contratos e portal';
$portalUrl = flash('portal_url');
require __DIR__ . '/_top.php';
?>

<div class="page-heading">
    <div>
        <p class="eyebrow">Documento e auditoria</p>
        <h1>Contratos e portal</h1>
    </div>
</div>

<?php if ($portalUrl): ?>
    <div class="export-link-card" role="status">
        <strong>Novo link do portal. Copie agora.</strong>
        <p>O link anterior foi revogado e este endereço completo não será exibido novamente.</p>
        <div class="copy-link-row">
            <input id="guest-portal-url" type="text" readonly value="<?= e($portalUrl) ?>" aria-label="Novo link privado do portal do hóspede">
            <button class="admin-secondary" type="button" data-copy-target="guest-portal-url" data-copy-feedback="guest-portal-copy-feedback">Copiar link</button>
            <a class="admin-secondary" href="<?= e($portalUrl) ?>" target="_blank" rel="noopener">Abrir portal</a>
        </div>
        <small id="guest-portal-copy-feedback" class="copy-feedback" aria-live="polite"></small>
    </div>
<?php endif; ?>

<div class="detail-grid">
    <section class="admin-panel">
        <h2>Templates</h2>
        <form method="post" action="<?= e(base_url('admin/operacoes/contract-bootstrap')) ?>">
            <?= csrf_field() ?>
            <button class="admin-secondary">Instalar/validar versões empacotadas</button>
        </form>
        <?php foreach ($versions as $version): ?>
            <div class="block-row">
                <div>
                    <strong>v<?= (int) $version['version_no'] ?> · <?= e($version['title']) ?></strong>
                    <small><?= e($version['status']) ?> · <?= e($version['source_kind']) ?></small>
                </div>
                <?php if ($version['status'] === 'PENDING_APPROVAL' && can('contracts.templates.approve')): ?>
                    <form method="post" action="<?= e(base_url('admin/operacoes/contract-approve')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="version_id" value="<?= (int) $version['id'] ?>">
                        <button class="admin-primary">Aprovar</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="admin-panel">
        <h2>Gerar contrato</h2>
        <p>O sistema impedirá a geração e listará variáveis ausentes. A política de cancelamento deve estar aprovada.</p>
        <form class="admin-form" method="post" action="<?= e(base_url('admin/operacoes/contract-generate')) ?>">
            <?= csrf_field() ?>
            <label>ID interno da reserva<input type="number" name="reservation_id" min="1" required></label>
            <label>Nacionalidade do responsável<input name="guest_nationality" required></label>
            <label>Estado civil<input name="guest_marital_status" required></label>
            <label>Profissão<input name="guest_profession" required></label>
            <label>RG<input name="guest_rg" required></label>
            <label>Endereço completo<input name="guest_address" required></label>
            <label>Vencimento do saldo<input name="balance_due_at" placeholder="dd/mm/aaaa HH:mm" required></label>
            <button class="admin-primary">Gerar snapshot e enfileirar PDF</button>
        </form>
    </section>
</div>

<section class="admin-panel">
    <h2>Documentos gerados</h2>
    <?php if (!$contracts): ?>
        <p class="empty-state">Nenhum contrato gerado.</p>
    <?php endif; ?>
    <?php foreach ($contracts as $contract): ?>
        <div class="block-row">
            <div>
                <strong><?= e($contract['contract_number']) ?> · <?= e($contract['codigo']) ?></strong>
                <small>
                    <?= e($contract['nome_cliente']) ?> · <?= e($contract['status']) ?> ·
                    <?= $contract['pdf_path'] ? 'PDF disponível · ' : 'PDF pendente · ' ?>
                    SHA <?= e(substr($contract['content_hash'], 0, 12)) ?>…
                </small>
            </div>
            <div class="document-actions">
                <?php if ($contract['pdf_path']): ?>
                    <a class="admin-primary" href="<?= e(base_url('admin/contratos/' . $contract['id'] . '/pdf')) ?>" target="_blank" rel="noopener">Abrir PDF</a>
                <?php endif; ?>
                <form method="post" action="<?= e(base_url('admin/operacoes/contract-pdf')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="contract_id" value="<?= (int) $contract['id'] ?>">
                    <button class="admin-secondary"><?= $contract['pdf_path'] ? 'Gerar PDF novamente' : 'Gerar PDF' ?></button>
                </form>
                <form method="post" action="<?= e(base_url('admin/operacoes/portal-regenerate')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="reservation_id" value="<?= (int) $contract['reservation_id'] ?>">
                    <button class="admin-secondary">Novo link portal</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</section>

<?php require __DIR__ . '/_bottom.php'; ?>

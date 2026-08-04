<?php
$title = 'Contratos e portal';
$portalUrl = flash('portal_url');
$statusLabels=[
    'READY'=>'PDF disponível',
    'SENT'=>'Enviado ao hóspede',
    'VIEWED'=>'Visualizado pelo hóspede',
    'SIGNED_BY_GUEST'=>'Assinado pelo hóspede',
    'FULLY_SIGNED'=>'Concluído pelas duas partes',
    'SUPERSEDED'=>'Substituído',
];
require __DIR__ . '/_top.php';
?>

<div class="page-heading"><div><p class="eyebrow">Documento e auditoria</p><h1>Contratos e portal</h1></div></div>

<?php if ($portalUrl): ?>
    <div class="export-link-card" role="status">
        <strong>Novo link do portal. Copie agora.</strong>
        <p>O link anterior foi revogado e este endereço completo não será exibido novamente.</p>
        <div class="copy-link-row"><input id="guest-portal-url" type="text" readonly value="<?= e($portalUrl) ?>" aria-label="Novo link privado do portal do hóspede"><button class="admin-secondary" type="button" data-copy-target="guest-portal-url" data-copy-feedback="guest-portal-copy-feedback">Copiar link</button><a class="admin-secondary" href="<?= e($portalUrl) ?>" target="_blank" rel="noopener">Abrir portal</a></div>
        <small id="guest-portal-copy-feedback" class="copy-feedback" aria-live="polite"></small>
    </div>
<?php endif; ?>

<div class="detail-grid">
    <section class="admin-panel">
        <h2>Templates</h2>
        <form method="post" action="<?= e(base_url('admin/operacoes/contract-bootstrap')) ?>"><?= csrf_field() ?><button class="admin-secondary">Instalar/validar versões empacotadas</button></form>
        <?php foreach ($versions as $version): ?><div class="block-row"><div><strong>v<?= (int) $version['version_no'] ?> · <?= e($version['title']) ?></strong><small><?= e($version['status']) ?> · <?= e($version['source_kind']) ?></small></div><?php if ($version['status'] === 'PENDING_APPROVAL' && can('contracts.templates.approve')): ?><form method="post" action="<?= e(base_url('admin/operacoes/contract-approve')) ?>"><?= csrf_field() ?><input type="hidden" name="version_id" value="<?= (int) $version['id'] ?>"><button class="admin-primary">Aprovar</button></form><?php endif; ?></div><?php endforeach; ?>
    </section>

    <section class="admin-panel">
        <h2>Gerar contrato</h2>
        <p>O sistema impedirá a geração e listará variáveis ausentes. A política de cancelamento deve estar aprovada.</p>
        <form class="admin-form" method="post" action="<?= e(base_url('admin/operacoes/contract-generate')) ?>"><?= csrf_field() ?><label>ID interno da reserva<input type="number" name="reservation_id" min="1" required></label><label>Nacionalidade do responsável<input name="guest_nationality" required></label><label>Estado civil<input name="guest_marital_status" required></label><label>Profissão<input name="guest_profession" required></label><label>RG<input name="guest_rg" required></label><label>Endereço completo<input name="guest_address" required></label><label>Vencimento do saldo<input name="balance_due_at" placeholder="dd/mm/aaaa HH:mm" required></label><button class="admin-primary">Gerar snapshot e enfileirar PDF</button></form>
    </section>
</div>

<section class="admin-panel contract-admin-list">
    <div class="panel-heading"><div><p class="eyebrow">Fluxo Gov.br</p><h2>Documentos gerados</h2></div><small>O sistema guarda todas as revisões e o SHA-256 de cada PDF.</small></div>
    <?php if (!$contracts): ?><p class="empty-state">Nenhum contrato gerado.</p><?php endif; ?>
    <?php foreach ($contracts as $contract):$documents=$signatureDocuments[(int)$contract['id']]??[];$guestDocument=$documents['GUEST_SIGNED']??null;$finalDocument=$documents['FULLY_SIGNED']??null;?>
        <article class="contract-admin-card">
            <header><div><strong><?= e($contract['contract_number']) ?> · <?= e($contract['codigo']) ?></strong><small><?= e($contract['nome_cliente']) ?> · <?= e($statusLabels[$contract['status']]??$contract['status']) ?></small></div><span class="admin-status status-<?= strtolower($contract['status']) ?>"><?= e($statusLabels[$contract['status']]??$contract['status']) ?></span></header>
            <div class="contract-admin-steps">
                <div class="<?= $contract['pdf_path']?'done':'' ?>"><span>1</span><strong>PDF original</strong><small><?= $contract['pdf_path']?'Disponível para o hóspede':'Pendente' ?></small></div>
                <div class="<?= $guestDocument?'done':'' ?>"><span>2</span><strong>Assinatura do hóspede</strong><small><?= $guestDocument?'Recebida em '.date('d/m/Y H:i',strtotime($guestDocument['created_at'])):'Aguardando upload' ?></small></div>
                <div class="<?= $finalDocument?'done':($guestDocument?'current':'') ?>"><span>3</span><strong>Assinatura do proprietário</strong><small><?= $finalDocument?'Versão final registrada':'Aguardando' ?></small></div>
            </div>
            <div class="document-actions">
                <?php if ($contract['pdf_path']): ?><a class="admin-secondary" href="<?= e(base_url('admin/contratos/' . $contract['id'] . '/pdf')) ?>" target="_blank" rel="noopener">Abrir original</a><?php endif; ?>
                <?php if (!$guestDocument && $contract['pdf_path']): ?><form method="post" action="<?= e(base_url('admin/operacoes/contract-pdf')) ?>"><?= csrf_field() ?><input type="hidden" name="contract_id" value="<?= (int) $contract['id'] ?>"><button class="admin-secondary">Gerar PDF novamente</button></form><?php endif; ?>
                <form method="post" action="<?= e(base_url('admin/operacoes/portal-regenerate')) ?>"><?= csrf_field() ?><input type="hidden" name="reservation_id" value="<?= (int) $contract['reservation_id'] ?>"><button class="admin-secondary">Novo link portal</button></form>
                <?php if($guestDocument):?><a class="admin-primary" href="<?= e(base_url('admin/contratos/'.$contract['id'].'/documentos/hospede.pdf')) ?>">Baixar PDF do hóspede</a><?php endif;?>
                <?php if($finalDocument):?><a class="admin-primary" href="<?= e(base_url('admin/contratos/'.$contract['id'].'/documentos/final.pdf')) ?>">Baixar PDF final</a><?php endif;?>
            </div>
            <?php if($guestDocument):?><small class="contract-hash">Hóspede: revisão <?= (int)$guestDocument['revision_no'] ?> · SHA-256 <?= e($guestDocument['sha256']) ?></small><?php endif;?>
            <?php if($finalDocument):?><small class="contract-hash">Final: revisão <?= (int)$finalDocument['revision_no'] ?> · SHA-256 <?= e($finalDocument['sha256']) ?></small><?php endif;?>
            <?php if($guestDocument&&can('contracts.signatures.manage')):?>
                <details class="owner-signature-upload" <?= !$finalDocument?'open':'' ?>><summary><?= $finalDocument?'Substituir versão final':'Registrar assinatura do proprietário' ?></summary>
                    <div class="owner-signature-instructions"><strong>Antes de enviar</strong><ol><li>Baixe o PDF assinado pelo hóspede.</li><li>Assine esse mesmo arquivo no Gov.br.</li><li>Baixe o novo PDF e envie abaixo.</li></ol></div>
                    <form class="admin-form" action="<?= e(base_url('admin/operacoes/contract-owner-upload')) ?>" method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="contract_id" value="<?= (int)$contract['id'] ?>"><label>PDF assinado pelas duas partes<input type="file" name="signed_contract" accept=".pdf,application/pdf" required></label><label class="check-row"><input type="checkbox" name="owner_signed_on_gov" value="1" required> Conferi o documento e o assinei no Gov.br.</label><button class="admin-primary" type="submit"><?= $finalDocument?'Enviar nova revisão final':'Registrar contrato final' ?></button><small>O sistema registra arquivo, revisão, usuário, data, IP e SHA-256; ele não valida criptograficamente a assinatura do Gov.br.</small></form>
                </details>
            <?php endif;?>
        </article>
    <?php endforeach; ?>
</section>

<?php require __DIR__ . '/_bottom.php'; ?>

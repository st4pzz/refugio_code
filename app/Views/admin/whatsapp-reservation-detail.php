<?php
declare(strict_types=1);
$title = 'Pedido ' . $reservation['codigo'];
$status = \Refugio\Models\ReservationStatus::from($reservation['status']);
$phone = \Refugio\Repositories\CustomerRepository::normalizePhone((string) $reservation['telefone']) ?? preg_replace('/\D+/', '', (string) $reservation['telefone']);
$manualProposalText = rawurlencode('Olá, ' . explode(' ', trim((string) $reservation['nome_cliente']))[0] . '! Segue o pedido de reserva ' . $reservation['codigo'] . ' para sua análise. Vou anexar o PDF nesta conversa.');
$manualPaymentText = rawurlencode('Olá, ' . explode(' ', trim((string) $reservation['nome_cliente']))[0] . '! Seguem as instruções de pagamento do pedido ' . $reservation['codigo'] . '. Vou anexar o PDF nesta conversa.');
$defaultDeadline = (new DateTimeImmutable('+24 hours'))->format('Y-m-d\TH:i');
require __DIR__ . '/_top.php';
?>
<div class="page-heading">
    <div>
        <a class="back-link" href="<?= e(base_url('admin/pedidos-whatsapp')) ?>">← Pedidos via WhatsApp</a>
        <p class="eyebrow"><?= e($reservation['codigo']) ?></p>
        <h1><?= e($reservation['nome_cliente']) ?></h1>
        <span class="admin-status status-<?= strtolower($status->value) ?>"><?= e($status->label()) ?></span>
    </div>
    <div class="page-actions">
        <a class="admin-secondary" href="<?= e(base_url('admin/reservas/' . $reservation['id'])) ?>">Abrir reserva completa</a>
        <a class="admin-secondary" target="_blank" rel="noopener" href="https://wa.me/<?= e($phone) ?>">Abrir WhatsApp</a>
    </div>
</div>

<ol class="order-steps" aria-label="Fluxo do pedido">
    <li class="done"><span>1</span><strong>Proposta</strong><small>PDF inicial</small></li>
    <li class="<?= $reservation['status'] === 'AGUARDANDO_APROVACAO' ? 'current' : 'done' ?>"><span>2</span><strong>Aceite</strong><small>Confirmação do cliente</small></li>
    <li class="<?= in_array($reservation['status'], ['AGUARDANDO_PAGAMENTO','COMPROVANTE_ENVIADO'], true) ? 'current' : (in_array($reservation['status'], ['RESERVA_CONFIRMADA','FINALIZADA'], true) ? 'done' : '') ?>"><span>3</span><strong>Pagamento</strong><small>Pix e comprovante</small></li>
    <li class="<?= in_array($reservation['status'], ['RESERVA_CONFIRMADA','FINALIZADA'], true) ? 'done' : '' ?>"><span>4</span><strong>Confirmação</strong><small>Reserva concluída</small></li>
</ol>

<?php if (\Refugio\Services\AvailabilityService::hasConflicts($conflicts)): ?>
    <div class="admin-alert error"><strong>Conflito ativo de datas.</strong> A cobrança não pode ser criada até que o calendário seja liberado.</div>
<?php endif; ?>
<?php if ($pendingConflicts): ?>
    <div class="admin-alert warning"><strong>Há outras solicitações pendentes para o período.</strong> A aprovação fará uma nova conferência transacional.</div>
<?php endif; ?>
<?php if (!$activeConversation): ?>
    <div class="admin-alert warning"><strong>Sem conversa ativa na janela de 24 horas.</strong> O envio automático de documento não está disponível agora; baixe o PDF, abra o WhatsApp e anexe-o manualmente.</div>
<?php endif; ?>

<div class="detail-grid">
    <section class="admin-panel">
        <div class="panel-heading"><h2>Cliente e estadia</h2><span class="admin-status"><?= e($reservation['origem']) ?></span></div>
        <dl class="data-list">
            <div><dt>Nome</dt><dd><?= e($reservation['nome_cliente']) ?></dd></div>
            <div><dt>WhatsApp</dt><dd><?= e($reservation['telefone']) ?></dd></div>
            <div><dt>E-mail</dt><dd><?= e($reservation['email']) ?></dd></div>
            <div><dt>Hóspedes</dt><dd><?= (int) $reservation['adultos'] ?> adulto(s), <?= (int) $reservation['criancas'] ?> criança(s)</dd></div>
            <div><dt>Check-in</dt><dd><?= date('d/m/Y', strtotime($reservation['checkin'])) ?></dd></div>
            <div><dt>Check-out</dt><dd><?= date('d/m/Y', strtotime($reservation['checkout'])) ?></dd></div>
        </dl>
        <?php if ($reservation['observacoes_cliente']): ?><h3>Observações</h3><p><?= nl2br(e($reservation['observacoes_cliente'])) ?></p><?php endif; ?>
    </section>
    <section class="admin-panel">
        <div class="panel-heading"><h2>Valores</h2><strong class="order-highlight"><?= money($reservation['valor_total']) ?></strong></div>
        <dl class="data-list">
            <div><dt>Sinal</dt><dd><?= $reservation['valor_sinal'] !== null ? money($reservation['valor_sinal']) : 'A definir após o aceite' ?></dd></div>
            <div><dt>Saldo</dt><dd><?= $reservation['valor_restante'] !== null ? money($reservation['valor_restante']) : '—' ?></dd></div>
            <div><dt>Prazo de pagamento</dt><dd><?= $reservation['prazo_pagamento'] ? date('d/m/Y H:i', strtotime($reservation['prazo_pagamento'])) : '—' ?></dd></div>
            <div><dt>Aceite de regras</dt><dd><?= $reservation['regras_aceitas'] ? 'Registrado pelo cliente' : 'Ainda não registrado' ?></dd></div>
        </dl>
        <?php if ($reservation['observacoes_cobranca']): ?><h3>Condições comerciais</h3><p><?= nl2br(e($reservation['observacoes_cobranca'])) ?></p><?php endif; ?>
    </section>
</div>

<section class="admin-panel document-stage">
    <div class="panel-heading">
        <div><p class="eyebrow">Etapa 1</p><h2>Proposta inicial</h2></div>
        <?php if ($proposalDocument): ?><span class="admin-status">Versão <?= (int) $proposalDocument['version_no'] ?></span><?php endif; ?>
    </div>
    <?php if ($proposalDocument): ?>
        <div class="document-callout">
            <div>
                <strong>Pedido de reserva em PDF</strong>
                <small>Emitido em <?= date('d/m/Y H:i', strtotime($proposalDocument['created_at'])) ?> · válido até <?= date('d/m/Y H:i', strtotime($proposalDocument['valid_until'])) ?></small>
            </div>
            <div class="document-actions">
                <a class="admin-secondary" target="_blank" href="<?= e(base_url('admin/pedidos-whatsapp/documentos/' . $proposalDocument['id'])) ?>">Visualizar PDF</a>
                <?php if ($activeConversation && can('reservas.manage') && can('conversas.reply')): ?>
                    <form method="post" action="<?= e(base_url('admin/pedidos-whatsapp/' . $reservation['id'] . '/enviar-proposta')) ?>" data-confirm="Enviar esta proposta na conversa ativa do WhatsApp?">
                        <?= csrf_field() ?><input type="hidden" name="document_id" value="<?= (int) $proposalDocument['id'] ?>">
                        <button class="admin-primary">Enviar PDF no WhatsApp</button>
                    </form>
                <?php else: ?>
                    <a class="admin-primary" target="_blank" rel="noopener" href="https://wa.me/<?= e($phone) ?>?text=<?= e($manualProposalText) ?>">Abrir WhatsApp para anexar</a>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!$activeConversation): ?><p class="privacy-note">Abra o PDF, faça o download no navegador e anexe o arquivo na conversa aberta pelo botão acima.</p><?php endif; ?>
    <?php else: ?>
        <p>Nenhuma proposta foi emitida.</p>
    <?php endif; ?>
    <?php if ($reservation['status'] === 'AGUARDANDO_APROVACAO' && can('reservas.manage')): ?>
        <details><summary>Reemitir proposta com nova validade</summary>
            <form class="inline-form" method="post" action="<?= e(base_url('admin/pedidos-whatsapp/' . $reservation['id'] . '/gerar-proposta')) ?>">
                <?= csrf_field() ?><label>Nova validade<input type="datetime-local" name="validade_proposta" value="<?= e((new DateTimeImmutable('+24 hours'))->format('Y-m-d\TH:i')) ?>" required></label>
                <button class="admin-secondary">Gerar nova versão</button>
            </form>
        </details>
    <?php endif; ?>
</section>

<?php if ($reservation['status'] === 'AGUARDANDO_APROVACAO' && can('reservas.manage')): ?>
    <section class="admin-panel action-panel">
        <div class="panel-heading"><div><p class="eyebrow">Etapa 2</p><h2>Cliente aprovou: criar cobrança</h2></div></div>
        <p>Esta ação verifica a disponibilidade dentro de uma transação, bloqueia as datas e cria a cobrança pendente.</p>
        <form class="admin-form form-grid" action="<?= e(base_url('admin/pedidos-whatsapp/' . $reservation['id'] . '/aprovar-cobranca')) ?>" method="post" enctype="multipart/form-data" data-confirm="O cliente aprovou a proposta e você deseja bloquear as datas e criar a cobrança?">
            <?= csrf_field() ?>
            <label>Valor total<input name="valor_total" inputmode="decimal" value="<?= e(number_format((float) $reservation['valor_total'], 2, ',', '.')) ?>" required></label>
            <label>Forma de cobrança<select name="forma_cobranca" required><option value="INTEGRAL">Pagamento integral</option><option value="SINAL">Somente sinal</option><option value="SINAL_SALDO" selected>Sinal e saldo posterior</option></select></label>
            <label>Valor do sinal<input name="valor_sinal" inputmode="decimal" placeholder="0,00" required></label>
            <label>Prazo para o Pix<input type="datetime-local" name="prazo_pagamento" value="<?= e($defaultDeadline) ?>" required></label>
            <label class="full">Chave Pix ou Pix Copia e Cola<textarea name="pix_copia_cola" rows="4" maxlength="5000" placeholder="Cole aqui a chave ou o código completo"></textarea></label>
            <label>Imagem do QR Code Pix<input type="file" name="qr_code" accept=".jpg,.jpeg,.png,image/jpeg,image/png"></label>
            <label class="full">Orientações de pagamento<textarea name="observacoes_cobranca" rows="3" maxlength="3000">Após o pagamento, envie o comprovante nesta conversa para conferência.</textarea></label>
            <label class="full">Política de cancelamento<textarea name="politica_cancelamento" rows="5" maxlength="5000" required><?= e($reservation['politica_cancelamento'] ?: ($settings['CANCELLATION_POLICY'] ?? '')) ?></textarea></label>
            <button class="admin-primary full">Aprovar, criar cobrança e emitir PDF</button>
        </form>
    </section>
<?php endif; ?>

<?php if ($paymentDocument): ?>
    <section class="admin-panel document-stage">
        <div class="panel-heading">
            <div><p class="eyebrow">Etapa 2</p><h2>Instruções de pagamento</h2></div>
            <span class="admin-status">Versão <?= (int) $paymentDocument['version_no'] ?></span>
        </div>
        <div class="document-callout payment">
            <div>
                <strong>PDF com Pix e vencimento</strong>
                <small>Emitido em <?= date('d/m/Y H:i', strtotime($paymentDocument['created_at'])) ?> · vencimento <?= date('d/m/Y H:i', strtotime($paymentDocument['valid_until'])) ?></small>
            </div>
            <div class="document-actions">
                <a class="admin-secondary" target="_blank" href="<?= e(base_url('admin/pedidos-whatsapp/documentos/' . $paymentDocument['id'])) ?>">Visualizar PDF</a>
                <?php if ($activeConversation && can('reservas.manage') && can('conversas.reply')): ?>
                    <form method="post" action="<?= e(base_url('admin/pedidos-whatsapp/' . $reservation['id'] . '/enviar-cobranca')) ?>" data-confirm="Enviar as instruções de pagamento na conversa ativa do WhatsApp?">
                        <?= csrf_field() ?><input type="hidden" name="document_id" value="<?= (int) $paymentDocument['id'] ?>">
                        <button class="admin-primary">Enviar PDF no WhatsApp</button>
                    </form>
                <?php else: ?>
                    <a class="admin-primary" target="_blank" rel="noopener" href="https://wa.me/<?= e($phone) ?>?text=<?= e($manualPaymentText) ?>">Abrir WhatsApp para anexar</a>
                <?php endif; ?>
            </div>
        </div>
        <?php if (in_array($reservation['status'], ['AGUARDANDO_PAGAMENTO','COMPROVANTE_ENVIADO','RESERVA_CONFIRMADA'], true) && can('reservas.manage')): ?>
            <form class="inline-form" method="post" action="<?= e(base_url('admin/pedidos-whatsapp/' . $reservation['id'] . '/gerar-cobranca')) ?>" data-confirm="Gerar uma nova versão com os dados de cobrança atuais?">
                <?= csrf_field() ?><button class="admin-secondary">Reemitir PDF de pagamento</button>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>

<div class="detail-grid">
    <section class="admin-panel">
        <div class="panel-heading"><h2>Cobranças</h2><span class="admin-status"><?= count($payments) ?></span></div>
        <?php if (!$payments): ?><p>Nenhuma cobrança criada.</p><?php endif; ?>
        <?php foreach ($payments as $payment): ?>
            <article class="payment-summary">
                <div><strong><?= e($payment['tipo']) ?> · <?= money($payment['valor']) ?></strong><small>Vence em <?= date('d/m/Y H:i', strtotime($payment['data_vencimento'])) ?></small></div>
                <span class="admin-status status-<?= strtolower($payment['status']) ?>"><?= e($payment['status']) ?></span>
            </article>
        <?php endforeach; ?>
    </section>
    <section class="admin-panel">
        <div class="panel-heading"><h2>Histórico de PDFs</h2><span class="admin-status"><?= count($documents) ?></span></div>
        <?php if (!$documents): ?><p>Nenhum documento.</p><?php endif; ?>
        <?php foreach ($documents as $document): ?>
            <article class="document-history">
                <div><a target="_blank" href="<?= e(base_url('admin/pedidos-whatsapp/documentos/' . $document['id'])) ?>"><strong><?= $document['document_type'] === 'PROPOSAL' ? 'Proposta' : 'Pagamento' ?> v<?= (int) $document['version_no'] ?></strong></a><small><?= date('d/m/Y H:i', strtotime($document['created_at'])) ?> · SHA-256 <?= e(substr($document['sha256'], 0, 12)) ?>…</small></div>
                <span class="admin-status status-<?= strtolower((string) ($document['delivery_status'] ?: 'ready')) ?>"><?= e($document['delivery_status'] ?: 'PRONTO') ?></span>
                <?php if ($document['delivery_error']): ?><p class="error-text"><?= e($document['delivery_error']) ?></p><?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
</div>
<?php require __DIR__ . '/_bottom.php'; ?>

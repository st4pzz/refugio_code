<?php
use Refugio\Models\ReservationStatus;
$title = 'Solicitacao ' . $reservation['codigo'];
$status = ReservationStatus::from($reservation['status']);
$parts = preg_split('/\s+/', trim($reservation['nome_cliente']));
$maskedName = $parts[0] . (count($parts) > 1 ? ' ' . strtoupper(substr(end($parts), 0, 1)) . '.' : '');
require __DIR__ . '/_top.php';
?>
<section class="reservation-card public-status">
    <div class="status-row"><div><div class="eyebrow">Solicitacao <?= e($reservation['codigo']) ?></div><h1><?= e($status->label()) ?></h1></div><span class="status-badge status-<?= strtolower($status->value) ?>"><?= e($status->label()) ?></span></div>
    <?php if ($message = flash('success')): ?><div class="alert alert-success" role="status"><?= e($message) ?></div><?php endif; ?>
    <?php if ($message = flash('error')): ?><div class="alert alert-error" role="alert"><?= e($message) ?></div><?php endif; ?>
    <?php if ($status !== ReservationStatus::RESERVA_CONFIRMADA && $status !== ReservationStatus::FINALIZADA): ?><p class="important-note">A hospedagem somente estara confirmada depois da aprovacao de disponibilidade e da identificacao do pagamento.</p><?php endif; ?>
    <dl class="summary summary-grid"><div><dt>Hospede</dt><dd><?= e($maskedName) ?></dd></div><div><dt>Hospedes</dt><dd><?= (int) $reservation['quantidade_hospedes'] ?></dd></div><div><dt>Check-in</dt><dd><?= date('d/m/Y', strtotime($reservation['checkin'])) ?></dd></div><div><dt>Check-out</dt><dd><?= date('d/m/Y', strtotime($reservation['checkout'])) ?></dd></div><?php if ($reservation['valor_total'] !== null): ?><div><dt>Valor total</dt><dd><?= money($reservation['valor_total']) ?></dd></div><div><dt>Valor restante</dt><dd><?= money($reservation['valor_restante'] ?? 0) ?></dd></div><?php endif; ?></dl>
</section>
<?php foreach ($payments as $payment): ?>
<section class="reservation-card payment-card">
    <div class="status-row"><h2><?= e(ucfirst(strtolower($payment['tipo']))) ?> · <?= money($payment['valor']) ?></h2><span class="status-badge"><?= e($payment['status']) ?></span></div>
    <p><strong>Prazo:</strong> <?= date('d/m/Y \a\s H:i', strtotime($payment['data_vencimento'])) ?></p>
    <?php if ($payment['status'] === 'CONFIRMADO'): ?><div class="alert alert-success">Pagamento identificado em <?= date('d/m/Y H:i', strtotime($payment['data_confirmacao'])) ?>.</div><?php endif; ?>
    <?php if (in_array($payment['status'], ['PENDENTE','COMPROVANTE_ENVIADO'], true)): ?>
        <?php if ($payment['qr_code_path']): ?><img class="qr-code" src="<?= e(base_url('reserva/arquivo.php?token=' . rawurlencode($reservation['token_publico']) . '&pagamento=' . $payment['id'])) ?>" alt="QR Code Pix para pagamento"><?php endif; ?>
        <?php if ($payment['pix_copia_cola']): ?><label class="pix-label">Pix Copia e Cola<textarea readonly rows="5" data-pix-code><?= e($payment['pix_copia_cola']) ?></textarea></label><button type="button" class="secondary-button" data-copy-pix>Copiar codigo Pix</button><span class="copy-feedback" role="status" aria-live="polite"></span><?php endif; ?>
        <p class="form-hint">Confira o valor e o destinatario no aplicativo do banco antes de confirmar.</p>
        <?php if ($payment['status'] !== 'COMPROVANTE_ENVIADO'): ?><form id="comprovante" class="upload-form" action="<?= e(base_url('api/reservas/' . rawurlencode($reservation['token_publico']) . '/comprovante')) ?>" method="post" enctype="multipart/form-data" data-upload-form><?= csrf_field() ?><input type="hidden" name="pagamento_id" value="<?= (int) $payment['id'] ?>"><label>Enviar comprovante <input type="file" name="comprovante" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required></label><progress max="100" value="0" hidden></progress><button class="primary-button" type="submit">Enviar comprovante</button></form><?php else: ?><div class="alert alert-success">Comprovante recebido. A reserva sera confirmada apos a identificacao do pagamento.</div><?php endif; ?>
    <?php endif; ?>
</section>
<?php endforeach; ?>
<?php if ($reservation['politica_cancelamento']): ?><section class="reservation-card"><h2>Politica de cancelamento aplicavel</h2><p><?= nl2br(e($reservation['politica_cancelamento'])) ?></p></section><?php endif; ?>
<section class="reservation-card contact-card"><h2>Precisa de ajuda?</h2><a class="secondary-button inline" href="https://wa.me/<?= e($this->config['contact_whatsapp']) ?>" target="_blank" rel="noopener noreferrer">Falar no WhatsApp</a></section>
<?php require __DIR__ . '/_bottom.php'; ?>

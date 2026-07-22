<?php $title = 'Solicitar reserva direta'; require __DIR__ . '/_top.php'; ?>
<section class="reservation-card form-card">
    <div class="eyebrow">Reserva direta</div>
    <h1>Solicite suas datas</h1>
    <p class="important-note"><strong>Esta e uma solicitacao de reserva</strong> e esta sujeita a confirmacao de disponibilidade. Nenhuma cobranca sera feita agora.</p>
    <?php if ($message = flash('error')): ?><div class="alert alert-error" role="alert"><?= e($message) ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-error" role="alert">Revise os campos destacados antes de continuar.</div><?php endif; ?>
    <form action="<?= e(base_url('api/reservas/solicitar')) ?>" method="post" class="reservation-form" data-submit-form novalidate>
        <?= csrf_field() ?><input type="hidden" name="_idempotency" value="<?= e($_SESSION['_request_idempotency']) ?>">
        <div class="honeypot" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <fieldset><legend>Datas e hospedes</legend><div class="form-grid">
            <label>Check-in <input type="date" name="checkin" min="<?= date('Y-m-d') ?>" required value="<?= e($old['checkin'] ?? '') ?>" aria-describedby="error-checkin"><small id="error-checkin" class="field-error"><?= e($errors['checkin'] ?? '') ?></small></label>
            <label>Check-out <input type="date" name="checkout" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required value="<?= e($old['checkout'] ?? '') ?>" aria-describedby="error-checkout"><small id="error-checkout" class="field-error"><?= e($errors['checkout'] ?? '') ?></small></label>
            <label>Adultos <input type="number" name="adultos" min="1" max="<?= (int) $this->config['max_guests'] ?>" required value="<?= e($old['adultos'] ?? '1') ?>"><small class="field-error"><?= e($errors['adultos'] ?? '') ?></small></label>
            <label>Criancas <input type="number" name="criancas" min="0" max="<?= (int) $this->config['max_guests'] ?>" required value="<?= e($old['criancas'] ?? '0') ?>"><small class="field-error"><?= e($errors['criancas'] ?? $errors['hospedes'] ?? '') ?></small></label>
        </div><p class="form-hint">Limite de <?= (int) $this->config['max_guests'] ?> hospedes.</p></fieldset>
        <div class="quote-preview" data-quote-preview data-quote-endpoint="<?= e(base_url('api/orcamentos/calcular')) ?>"><button type="button" class="secondary-button" data-calculate-quote>Calcular valor estimado</button><div class="quote-result" data-quote-result role="status" aria-live="polite"><p>Escolha as datas e o número de hóspedes.</p></div></div>
        <fieldset><legend>Seus dados</legend><div class="form-grid">
            <label class="full">Nome completo <input type="text" name="nome" maxlength="160" autocomplete="name" required value="<?= e($old['nome'] ?? '') ?>"><small class="field-error"><?= e($errors['nome'] ?? '') ?></small></label>
            <label>CPF <?= $this->config['cpf_required'] ? '' : '(opcional)' ?><input type="text" name="cpf" inputmode="numeric" maxlength="14" autocomplete="off" <?= $this->config['cpf_required'] ? 'required' : '' ?> value="<?= e($old['cpf'] ?? '') ?>"><small class="field-error"><?= e($errors['cpf'] ?? '') ?></small></label>
            <label>E-mail <input type="email" name="email" maxlength="190" autocomplete="email" required value="<?= e($old['email'] ?? '') ?>"><small class="field-error"><?= e($errors['email'] ?? '') ?></small></label>
            <label class="full">WhatsApp com DDD <input type="tel" name="telefone" maxlength="20" autocomplete="tel" placeholder="(16) 99999-9999" required value="<?= e($old['telefone'] ?? '') ?>"><small class="field-error"><?= e($errors['telefone'] ?? '') ?></small></label>
            <label class="full">Observacoes (opcional)<textarea name="observacoes" rows="4" maxlength="3000"><?= e($old['observacoes'] ?? '') ?></textarea></label>
        </div></fieldset>
        <fieldset><legend>Consentimentos</legend><div class="checks">
            <label><input type="checkbox" name="regras_aceitas" value="1" required <?= !empty($old['regras_aceitas']) ? 'checked' : '' ?>> Li e concordo com as <a href="<?= e(base_url('politicas/regras')) ?>" target="_blank">regras da propriedade</a>.</label>
            <small class="field-error"><?= e($errors['regras_aceitas'] ?? '') ?></small>
            <label><input type="checkbox" name="cancelamento_aceito" value="1" required <?= !empty($old['cancelamento_aceito']) ? 'checked' : '' ?>> Li e concordo com a <a href="<?= e(base_url('politicas/cancelamento')) ?>" target="_blank">politica de cancelamento</a>.</label>
            <small class="field-error"><?= e($errors['cancelamento_aceito'] ?? '') ?></small>
            <label><input type="checkbox" name="contato_autorizado" value="1" required <?= !empty($old['contato_autorizado']) ? 'checked' : '' ?>> Autorizo mensagens relacionadas a esta solicitacao por e-mail e WhatsApp.</label>
            <small class="field-error"><?= e($errors['contato_autorizado'] ?? '') ?></small>
        </div></fieldset>
        <button type="submit" class="primary-button"><span class="button-label">Enviar solicitacao</span><span class="button-loading">Enviando...</span></button>
        <p class="privacy-copy">Usaremos seus dados apenas para processar a hospedagem, o pagamento e as comunicacoes relacionadas. Consulte a <a href="<?= e(base_url('politicas/privacidade')) ?>">politica de privacidade</a>.</p>
    </form>
</section>
<?php require __DIR__ . '/_bottom.php'; ?>

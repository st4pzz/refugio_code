<?php $title = 'Reserve suas datas'; require __DIR__ . '/_top.php'; ?>
<section class="reservation-card form-card">
    <div class="eyebrow">Reserva direta</div>
    <h1 data-reservation-title>Quando você quer vir?</h1>
    <p data-reservation-intro>Consulte as datas e o valor da sua estadia antes de preencher seus dados.</p>
    <ol class="reservation-steps" aria-label="Etapas da reserva"><li data-step-label="dates" aria-current="step">1. Datas</li><li data-step-label="details">2. Seus dados</li><li>3. Pix</li></ol>
    <?php if ($message = flash('error')): ?><div class="alert alert-error" role="alert"><?= e($message) ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-error" role="alert">Revise os campos destacados. Seus dados foram mantidos.</div><?php endif; ?>
    <form action="<?= e(base_url('api/reservas/solicitar')) ?>" method="post" class="reservation-form" data-reservation-flow data-quote-endpoint="<?= e(base_url('api/orcamentos/calcular')) ?>" novalidate>
        <?= csrf_field() ?><input type="hidden" name="_idempotency" value="<?= e($_SESSION['_request_idempotency']) ?>">
        <div class="honeypot" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <fieldset data-reservation-dates><legend>Escolha sua estadia</legend><div class="form-grid">
            <label>Check-in<input type="date" name="checkin" min="<?= date('Y-m-d') ?>" required value="<?= e($old['checkin'] ?? '') ?>" aria-describedby="error-checkin"><small id="error-checkin" class="field-error"><?= e($errors['checkin'] ?? '') ?></small></label>
            <label>Check-out<input type="date" name="checkout" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required value="<?= e($old['checkout'] ?? '') ?>" aria-describedby="error-checkout"><small id="error-checkout" class="field-error"><?= e($errors['checkout'] ?? '') ?></small></label>
            <label class="full">Número de hóspedes<input type="number" name="hospedes" min="1" max="<?= (int) $this->config['max_guests'] ?>" required value="<?= e($old['hospedes'] ?? ((int) ($old['adultos'] ?? 2) + (int) ($old['criancas'] ?? 0))) ?>" aria-describedby="error-hospedes"><small>Inclua adultos e crianças. Até <?= (int) $this->config['max_guests'] ?> hóspedes.</small><small id="error-hospedes" class="field-error"><?= e($errors['hospedes'] ?? $errors['adultos'] ?? '') ?></small></label>
        </div><button type="submit" class="primary-button" data-check-availability>Consultar disponibilidade</button></fieldset>
        <div class="availability-feedback" data-availability-feedback role="status" aria-live="polite" aria-atomic="true" tabindex="-1" hidden></div>
        <section class="reservation-alternatives" data-reservation-alternatives aria-labelledby="alternatives-title" hidden>
            <h2 id="alternatives-title">Outras datas para sua estadia</h2>
            <p data-alternatives-intro></p>
            <div class="alternative-periods" data-alternative-periods></div>
            <p class="form-hint">Mesmo número de hóspedes e mesma duração. As datas e o valor serão consultados novamente ao escolher uma opção.</p>
            <a class="text-link" href="https://wa.me/<?= e($this->config['contact_whatsapp']) ?>" target="_blank" rel="noopener noreferrer">Precisa de outras datas? Fale conosco</a>
        </section>
        <div class="stay-preview" data-stay-preview hidden><p data-stay-summary></p><strong data-stay-total></strong><p class="form-hint">A disponibilidade e o valor serão revalidados antes de liberar o Pix.</p><button type="button" class="text-link" data-edit-dates>Alterar datas ou hóspedes</button></div>
        <fieldset data-reservation-details disabled hidden><legend>Datas disponíveis! Complete sua reserva</legend>
            <div class="form-grid">
                <label class="full">Nome completo<input type="text" name="nome" maxlength="160" autocomplete="name" required value="<?= e($old['nome'] ?? '') ?>"><small class="field-error"><?= e($errors['nome'] ?? '') ?></small></label>
                <label>E-mail<input type="email" name="email" maxlength="190" autocomplete="email" required value="<?= e($old['email'] ?? '') ?>"><small class="field-error"><?= e($errors['email'] ?? '') ?></small></label>
                <label>WhatsApp com DDD<input type="tel" name="telefone" maxlength="20" autocomplete="tel" placeholder="(19) 99999-9999" required value="<?= e($old['telefone'] ?? '') ?>"><small class="field-error"><?= e($errors['telefone'] ?? '') ?></small></label>
                <?php if ($this->config['cpf_required']): ?><label>CPF<input type="text" name="cpf" inputmode="numeric" maxlength="14" required value="<?= e($old['cpf'] ?? '') ?>"><small class="field-error"><?= e($errors['cpf'] ?? '') ?></small></label><?php endif; ?>
            </div>
            <details class="stay-extras" <?= !empty($old['criancas']) || !empty($old['pets']) || !empty($old['observacoes']) ? 'open' : '' ?>><summary>Crianças, pets ou alguma observação?</summary><div class="form-grid">
                <label>Crianças incluídas no total<input type="number" name="criancas" min="0" max="<?= max(0, (int) $this->config['max_guests'] - 1) ?>" required value="<?= e($old['criancas'] ?? '0') ?>"><small class="field-error"><?= e($errors['criancas'] ?? '') ?></small></label>
                <label>Pets<input type="number" name="pets" min="0" max="20" required value="<?= e($old['pets'] ?? '0') ?>"><small>O valor será atualizado se houver taxa para pets.</small></label>
                <label class="full">Observações<textarea name="observacoes" rows="3" maxlength="3000"><?= e($old['observacoes'] ?? '') ?></textarea></label>
            </div></details>
            <div class="checks">
                <label><input type="checkbox" name="regras_aceitas" value="1" required <?= !empty($old['regras_aceitas']) ? 'checked' : '' ?>> Concordo com as <a href="<?= e(base_url('politicas/regras')) ?>" target="_blank" rel="noopener">regras da propriedade</a>.</label><small class="field-error"><?= e($errors['regras_aceitas'] ?? '') ?></small>
                <label><input type="checkbox" name="cancelamento_aceito" value="1" required <?= !empty($old['cancelamento_aceito']) ? 'checked' : '' ?>> Concordo com a <a href="<?= e(base_url('politicas/cancelamento')) ?>" target="_blank" rel="noopener">política de cancelamento</a>.</label><small class="field-error"><?= e($errors['cancelamento_aceito'] ?? '') ?></small>
                <label><input type="checkbox" name="contato_autorizado" value="1" required <?= !empty($old['contato_autorizado']) ? 'checked' : '' ?>> Autorizo contato sobre esta reserva por e-mail e WhatsApp.</label><small class="field-error"><?= e($errors['contato_autorizado'] ?? '') ?></small>
            </div>
            <button type="submit" class="primary-button" data-proceed-payment>Continuar para o Pix</button>
            <p class="form-hint">Após continuar, você terá <?= max(5, min(120, (int) ($this->config['pix_payment_minutes'] ?? 30))) ?> minutos para pagar e enviar o comprovante. A confirmação depende da conferência do pagamento.</p>
            <p class="privacy-copy">Seus dados serão usados para esta hospedagem. <a href="<?= e(base_url('politicas/privacidade')) ?>">Política de privacidade</a>.</p>
        </fieldset>
        <noscript><p class="alert alert-error">Ative o JavaScript para consultar a disponibilidade e continuar a reserva, ou <a href="https://wa.me/<?= e($this->config['contact_whatsapp']) ?>">fale conosco pelo WhatsApp</a>.</p></noscript>
    </form>
</section>
<script src="<?= e(base_url('assets/js/reservation-flow.js?v=2')) ?>" defer></script>
<?php require __DIR__ . '/_bottom.php'; ?>

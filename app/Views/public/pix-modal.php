<button type="button" class="primary-button" data-open-pix="pix-<?= (int) $payment['id'] ?>">Pagar com Pix · <?= money($payment['valor']) ?></button>
<dialog id="pix-<?= (int) $payment['id'] ?>" class="pix-dialog" aria-labelledby="pix-title-<?= (int) $payment['id'] ?>" data-pix-dialog data-deadline="<?= e(date(DATE_ATOM, strtotime($payment['data_vencimento']))) ?>" <?= isset($_GET['pagar']) && $payment['status'] === 'PENDENTE' ? 'data-auto-open' : '' ?>>
    <form method="dialog"><button class="pix-close" aria-label="Fechar pagamento">×</button></form>
    <div class="eyebrow">Refúgio do Cuscuzeiro</div>
    <h2 id="pix-title-<?= (int) $payment['id'] ?>">Finalize sua reserva com Pix</h2>
    <p class="pix-amount"><?= money($payment['valor']) ?></p>
    <p data-pix-timer role="status">Pague e envie o comprovante até <?= e(date('d/m/Y H:i', strtotime($payment['data_vencimento']))) ?>.</p>
    <div data-pix-active>
        <ol><li>Copie a chave aleatória abaixo.</li><li>No aplicativo do banco, escolha Pix por chave aleatória.</li><li>Informe o valor e confira o favorecido antes de pagar.</li></ol>
        <dl class="summary"><div><dt>Favorecido</dt><dd>Sergio Ramella</dd></div><div><dt>Banco</dt><dd>Inter</dd></div></dl>
        <label>Chave Pix · Aleatória<input readonly value="6f3673aa-73f2-49ba-a956-e395535607e2" data-pix-key></label>
        <button type="button" class="primary-button" data-copy-pix-key>Copiar chave Pix</button>
        <p data-pix-feedback role="status" aria-live="polite"></p>
        <button type="button" class="secondary-button" data-pix-receipt>Já paguei · enviar comprovante</button>
    </div>
    <p class="form-hint">Pagamento direto ao anfitrião, sem integração bancária. Copiar a chave ou clicar em “Já paguei” não confirma a reserva. Envie o comprovante para conferência.</p>
</dialog>
<noscript><p>Pix por chave aleatória: <strong>6f3673aa-73f2-49ba-a956-e395535607e2</strong>. Sergio Ramella · Banco Inter. Valor: <?= money($payment['valor']) ?>. Envie o comprovante abaixo antes do prazo.</p></noscript>

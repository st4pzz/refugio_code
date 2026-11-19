<?php if (!empty($measureCheckout) && !isset($_COOKIE['refugio_cookie_consent'])): ?>
<aside class="reservation-card" data-checkout-consent aria-label="Consentimento de cookies"<?= !empty($checkoutConsentReload) ? ' data-reload-on-accept' : '' ?>>
    <strong>Cookies e privacidade</strong>
    <p>Com sua autorização, usamos cookies para medir visitas e conversões.</p>
    <button type="button" class="secondary-button" data-consent-choice="rejected">Somente essenciais</button>
    <button type="button" class="primary-button inline" data-consent-choice="accepted">Aceitar cookies</button>
</aside>
<?php endif; ?>
</main>
<footer class="reservation-footer"><p>Refugio do Cuscuzeiro · Analandia/SP</p><p><a href="<?= e(base_url('politicas/privacidade')) ?>">Privacidade</a> · <a href="<?= e(base_url('politicas/termos')) ?>">Termos</a> · <a href="<?= e(base_url('politicas/exclusao-de-dados')) ?>">Exclusão de dados</a> · <a href="<?= e(base_url('politicas/cancelamento')) ?>">Cancelamento</a> · <a href="<?= e(base_url('politicas/regras')) ?>">Regras</a></p></footer>
<script src="<?= e(base_url('assets/js/reservas.js?v=3')) ?>" defer></script>
<script src="<?= e(base_url('assets/js/reviews-form.js?v=1')) ?>" defer></script>
</body></html>

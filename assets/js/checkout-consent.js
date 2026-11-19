(() => {
    const cookieName = 'refugio_cookie_consent';
    const hasConsent = () => document.cookie.split(';').some(value => value.trim() === cookieName + '=accepted');
    const loadTags = () => {
        if (!hasConsent() || document.getElementById('gtm-loader')) return;
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({'gtm.start': Date.now(), event: 'gtm.js'});
        const script = document.createElement('script');
        script.id = 'gtm-loader';
        script.async = true;
        script.src = 'https://www.googletagmanager.com/gtm.js?id=GTM-5D4WKTM7';
        document.head.appendChild(script);
    };

    loadTags();
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-consent-choice]');
        const banner = button?.closest('[data-checkout-consent]');
        if (!banner) return;
        const choice = button.dataset.consentChoice;
        document.cookie = cookieName + '=' + choice + '; Max-Age=31536000; Path=/; SameSite=Lax';
        if (choice === 'accepted') {
            if (banner.hasAttribute('data-reload-on-accept') && hasConsent()) {
                window.location.reload();
                return;
            }
            loadTags();
        }
        banner.remove();
    });
})();

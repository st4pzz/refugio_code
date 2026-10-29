(() => {
  'use strict';
  if (window.location.pathname !== '/') return;
  const popup = document.querySelector('[data-paid-reservation-prompt]');
  if (!popup) return;
  const params = new URLSearchParams(window.location.search);
  const medium = (params.get('utm_medium') || '').trim().toLowerCase();
  const paid = ['cpc', 'ppc', 'paid', 'paid_social', 'paid-social', 'paidsocial', 'paid_search', 'display', 'cpm', 'retargeting'].includes(medium)
    || ['gclid', 'gbraid', 'wbraid', 'ttclid'].some(key => Boolean(params.get(key)?.trim()));
  // fbclid alone also appears on organic Facebook links: require a paid medium.
  const attributionKeys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'gclid', 'gbraid', 'wbraid', 'fbclid', 'ttclid'];
  document.querySelectorAll('a[href="/reserva/solicitar"]').forEach(link => {
    const target = new URL(link.getAttribute('href'), window.location.origin);
    for (const key of attributionKeys) {
      const value = params.get(key);
      if (value) target.searchParams.set(key, value.slice(0, 255));
    }
    link.href = target.pathname + target.search;
  });
  if (!paid) return;
  const storageKey = 'refugio_paid_reservation_prompt_v1';
  try { if (sessionStorage.getItem(storageKey)) return; }
  catch { return; } // Avoid repetitive prompts when storage is unavailable.
  const consented = () => document.cookie.split(';').some(cookie => cookie.trim() === 'refugio_cookie_consent=accepted');
  const track = event => { if (consented()) window.dataLayer?.push({event, placement: 'home_paid_prompt'}); };
  let cancelled = false;
  let attempts = 0;
  let timer;
  const dismiss = (event = 'reservation_prompt_dismiss') => {
    popup.hidden = true;
    document.body.classList.remove('paid-popup-visible');
    cancelled = true;
    clearTimeout(timer);
    track(event);
  };
  popup.querySelectorAll('[data-dismiss-reservation-prompt]').forEach(button => button.addEventListener('click', () => dismiss()));
  popup.querySelector('[data-reservation-prompt-cta]').addEventListener('click', () => dismiss('reservation_prompt_click'));
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && !popup.hidden) dismiss();
  });
  document.addEventListener('click', event => {
    const link = event.target.closest?.('a');
    if (link && !popup.contains(link)) { cancelled = true; clearTimeout(timer); }
  });
  const attempt = () => {
    if (cancelled || attempts++ >= 60) return;
    const banner = document.querySelector('[data-cookie-consent-banner]');
    const bannerVisible = banner && !banner.hidden && getComputedStyle(banner).display !== 'none';
    const occupied = document.hidden || bannerVisible || document.querySelector('dialog[open], #navMenu.active')
      || document.activeElement?.matches('input, textarea, select, [contenteditable="true"]');
    if (occupied) { timer = setTimeout(attempt, 1000); return; }
    try {
      if (sessionStorage.getItem(storageKey)) return;
      sessionStorage.setItem(storageKey, 'shown');
    } catch { return; }
    popup.hidden = false;
    document.body.classList.add('paid-popup-visible');
    track('reservation_prompt_view');
  };
  timer = setTimeout(attempt, 2000);
})();

// Vitrine pública: somente avaliações aprovadas retornadas pela API.
(function () {
    'use strict';

    const section = document.querySelector('#reviews');
    const carousel = section?.querySelector('.review-carousel');
    const slidesContainer = carousel?.querySelector('.review-slides');
    const empty = section?.querySelector('[data-review-empty]');
    const summary = section?.querySelector('[data-review-summary]');
    if (!section || !carousel || !slidesContainer || !empty || !summary) return;

    const source = carousel.dataset.source;
    const prevButton = carousel.querySelector('.review-prev');
    const nextButton = carousel.querySelector('.review-next');
    const dotsContainer = carousel.querySelector('.review-dots');
    const controls = carousel.querySelector('.review-controls');
    let slides = [];
    let current = 0;
    let timer = null;

    function stayLabel(checkout) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(String(checkout || ''))) return '';
        const date = new Date(`${checkout}T12:00:00Z`);
        if (Number.isNaN(date.getTime())) return '';
        return new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(date);
    }

    function element(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (typeof text === 'string') node.textContent = text;
        return node;
    }

    function createSlide(item, index) {
        const note = Math.max(1, Math.min(5, Number.parseInt(item.nota_geral, 10) || 1));
        const slide = element('article', 'review-slide');
        slide.dataset.index = String(index);

        const rating = element('div', 'review-rating', '★'.repeat(note) + '☆'.repeat(5 - note));
        rating.setAttribute('aria-label', `${note} de 5 estrelas`);
        slide.appendChild(rating);
        slide.appendChild(element('p', 'review-text', String(item.comentario || '')));

        const identity = element('div', 'reviewer');
        identity.appendChild(element('span', 'reviewer-name', String(item.nome_exibicao || 'Hóspede')));
        identity.appendChild(element('span', 'review-verified', '✓ Avaliação verificada'));
        slide.appendChild(identity);

        const period = stayLabel(item.checkout);
        if (period) slide.appendChild(element('p', 'review-stay', `Hospedagem em ${period}`));

        if (typeof item.resposta_administrador === 'string' && item.resposta_administrador.trim()) {
            const response = element('aside', 'review-response');
            response.appendChild(element('strong', '', 'Resposta do Refúgio'));
            response.appendChild(element('p', '', item.resposta_administrador.trim()));
            slide.appendChild(response);
        }
        return slide;
    }

    function update() {
        slides.forEach((slide, index) => slide.classList.toggle('active', index === current));
        Array.from(dotsContainer?.children || []).forEach((dot, index) => {
            dot.classList.toggle('active', index === current);
            dot.setAttribute('aria-selected', index === current ? 'true' : 'false');
        });
    }

    function stop() {
        if (timer) window.clearInterval(timer);
        timer = null;
    }

    function start() {
        stop();
        if (slides.length > 1) timer = window.setInterval(() => { current = (current + 1) % slides.length; update(); }, 5500);
    }

    function move(delta) {
        current = (current + delta + slides.length) % slides.length;
        update();
        start();
    }

    function render(data) {
        const items = Array.isArray(data?.items) ? data.items.filter(item => item && item.comentario) : [];
        if (!items.length) return;

        const nodes = items.map(createSlide);
        slidesContainer.replaceChildren(...nodes);
        slidesContainer.classList.remove('preload');
        slides = nodes;

        if (dotsContainer) {
            const dots = slides.map((_, index) => {
                const dot = element('button', 'review-dot');
                dot.type = 'button';
                dot.setAttribute('role', 'tab');
                dot.setAttribute('aria-label', `Ir para avaliação ${index + 1}`);
                dot.addEventListener('click', () => { current = index; update(); start(); });
                return dot;
            });
            dotsContainer.replaceChildren(...dots);
        }

        const count = Number.parseInt(data.count, 10) || items.length;
        const average = Number(data.average);
        if (count > 0 && Number.isFinite(average)) {
            summary.querySelector('[data-review-average]').textContent = average.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
            summary.querySelector('[data-review-count]').textContent = `Baseado em ${count} ${count === 1 ? 'avaliação verificada' : 'avaliações verificadas'}`;
            summary.hidden = false;
        }
        empty.hidden = true;
        carousel.hidden = false;
        if (controls) controls.hidden = slides.length < 2;
        update();
        start();
    }

    prevButton?.addEventListener('click', () => move(-1));
    nextButton?.addEventListener('click', () => move(1));
    carousel.addEventListener('mouseenter', stop);
    carousel.addEventListener('mouseleave', start);
    carousel.addEventListener('focusin', stop);
    carousel.addEventListener('focusout', start);

    fetch(source, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' })
        .then(response => {
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            return response.json();
        })
        .then(render)
        .catch(() => {
            empty.hidden = false;
            carousel.hidden = true;
            summary.hidden = true;
        });
})();

// Carrossel de avaliações - troca automática com pausa no hover
(function(){
    const carousel = document.querySelector('.review-carousel');
    if (!carousel) return;

    const slides = Array.from(carousel.querySelectorAll('.review-slide'));
    const prevBtn = carousel.querySelector('.review-prev');
    const nextBtn = carousel.querySelector('.review-next');
    const dotsContainer = carousel.querySelector('.review-dots');
    let current = 0;
    let interval = null;
    const delay = 4500; // tempo entre swaps

    function createDots() {
        if (!dotsContainer) return;
        dotsContainer.innerHTML = '';
        slides.forEach((s, i) => {
            const dot = document.createElement('button');
            dot.type = 'button';
            dot.className = 'review-dot';
            dot.setAttribute('aria-label', `Ir para avaliação ${i+1}`);
            dot.addEventListener('click', () => { goTo(i); start(); });
            dotsContainer.appendChild(dot);
        });
    }

    function update() {
        slides.forEach((s, i) => {
            s.classList.toggle('active', i === current);
        });
        if (dotsContainer) {
            const dots = Array.from(dotsContainer.children);
            dots.forEach((d, i) => d.classList.toggle('active', i === current));
        }
    }

    function prev() { current = (current - 1 + slides.length) % slides.length; update(); }
    function next() { current = (current + 1) % slides.length; update(); }
    function goTo(i) { current = i % slides.length; update(); }

    function start() {
        stop();
        interval = setInterval(() => { next(); }, delay);
    }

    function stop() {
        if (interval) clearInterval(interval);
        interval = null;
    }

    // Inicializa
    if (slides.length === 0) return;
    // garantir que o primeiro slide fique visível imediatamente (redundância para mobile/FOUC)
    slides[0].classList.add('active');
    createDots();
    update();
    // remover estado de preload (se presente) para que o JS controle a visibilidade corretamente
    const slidesContainer = carousel.querySelector('.review-slides');
    if (slidesContainer && slidesContainer.classList.contains('preload')) {
        slidesContainer.classList.remove('preload');
    }
    start();

    // Eventos de controles (só se existirem)
    if (prevBtn) prevBtn.addEventListener('click', () => { prev(); start(); });
    if (nextBtn) nextBtn.addEventListener('click', () => { next(); start(); });

    // Pausar ao hover/focus para acessibilidade
    carousel.addEventListener('mouseenter', stop);
    carousel.addEventListener('mouseleave', start);
    carousel.addEventListener('focusin', stop);
    carousel.addEventListener('focusout', start);

})();

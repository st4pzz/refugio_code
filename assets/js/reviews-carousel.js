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
        slides.forEach((s, i) => {
            const dot = document.createElement('button');
            dot.className = 'review-dot';
            dot.setAttribute('aria-label', `Ir para avaliação ${i+1}`);
            dot.addEventListener('click', () => goTo(i));
            dotsContainer.appendChild(dot);
        });
    }

    function update() {
        slides.forEach((s, i) => {
            s.classList.toggle('active', i === current);
        });
        const dots = Array.from(dotsContainer.children);
        dots.forEach((d, i) => d.classList.toggle('active', i === current));
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
    createDots();
    update();
    start();

    // Eventos de controles
    prevBtn.addEventListener('click', () => { prev(); start(); });
    nextBtn.addEventListener('click', () => { next(); start(); });

    // Pausar ao hover/focus para acessibilidade
    carousel.addEventListener('mouseenter', stop);
    carousel.addEventListener('mouseleave', start);
    carousel.addEventListener('focusin', stop);
    carousel.addEventListener('focusout', start);

})();

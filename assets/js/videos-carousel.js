(() => {
    document.querySelectorAll('[data-video-carousel]').forEach((carousel) => {
        const track = carousel.querySelector('[data-video-track]');
        const slides = Array.from(carousel.querySelectorAll('[data-video-slide]'));
        const previous = carousel.querySelector('[data-video-prev]');
        const next = carousel.querySelector('[data-video-next]');
        const dots = carousel.querySelector('[data-video-dots]');
        const status = carousel.querySelector('[data-video-status]');
        if (!track || slides.length === 0) return;

        let current = 0;
        const dotButtons = slides.map((slide, index) => {
            slide.setAttribute('aria-label', `${index + 1} de ${slides.length}`);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'video-dot';
            button.setAttribute('aria-label', `Ir para o vídeo ${index + 1}`);
            button.addEventListener('click', () => show(index));
            dots?.appendChild(button);
            return button;
        });

        const show = (wanted) => {
            current = (wanted + slides.length) % slides.length;
            track.style.transform = `translateX(-${current * 100}%)`;
            slides.forEach((slide, index) => {
                const active = index === current;
                slide.setAttribute('aria-hidden', String(!active));
                if (!active) slide.querySelectorAll('video').forEach((video) => video.pause());
            });
            dotButtons.forEach((dot, index) => {
                const active = index === current;
                dot.classList.toggle('active', active);
                dot.setAttribute('aria-current', active ? 'true' : 'false');
            });
            if (status) status.textContent = `${current + 1} / ${slides.length}`;
        };

        const hasMultipleVideos = slides.length > 1;
        if (previous) previous.hidden = !hasMultipleVideos;
        if (next) next.hidden = !hasMultipleVideos;
        previous?.addEventListener('click', () => show(current - 1));
        next?.addEventListener('click', () => show(current + 1));
        carousel.addEventListener('keydown', (event) => {
            if (!hasMultipleVideos || event.target instanceof HTMLVideoElement) return;
            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                show(current - 1);
            } else if (event.key === 'ArrowRight') {
                event.preventDefault();
                show(current + 1);
            }
        });
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) carousel.querySelectorAll('video').forEach((video) => video.pause());
        });
        show(0);
    });
})();

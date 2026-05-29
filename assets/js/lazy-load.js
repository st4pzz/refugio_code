/**
 * Lazy Loading com Intersection Observer
 * Carrega imagens sob demanda para otimizar performance
 * Otimizado para não bloquear a thread principal
 */

(function() {
  'use strict';

  // Usar requestIdleCallback para não bloquear a thread principal
  const scheduleWork = typeof requestIdleCallback !== 'undefined' 
    ? requestIdleCallback 
    : (callback) => setTimeout(callback, 0);

  // Verificar suporte para Intersection Observer
  if (!('IntersectionObserver' in window)) {
    console.warn('IntersectionObserver não suportado. Carregando imagens normalmente.');
    scheduleWork(loadAllImages);
    return;
  }

  // Configuração do Intersection Observer
  const observerOptions = {
    root: null,
    rootMargin: '50px',
    threshold: 0.01
  };

  const imageObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        const img = entry.target;
        
        // Usar requestAnimationFrame para decode
        requestAnimationFrame(() => {
          if (img.dataset.src) {
            loadImage(img);
          }
        });
        
        imageObserver.unobserve(img);
      }
    });
  }, observerOptions);

  /**
   * Carrega a imagem a partir de data-src
   */
  function loadImage(img) {
    const picture = img.closest('picture');
    if (picture) {
      const sources = picture.querySelectorAll('source');
      sources.forEach((source) => {
        if (source.dataset.srcset) {
          source.srcset = source.dataset.srcset;
          source.removeAttribute('data-srcset');
        }
      });
    }
    
    if (img.dataset.src) {
      img.src = img.dataset.src;
      img.removeAttribute('data-src');
    }
    
    img.classList.add('loaded');
  }

  /**
   * Fallback: carrega todas as imagens
   */
  function loadAllImages() {
    const images = document.querySelectorAll('img[data-src]');
    images.forEach((img) => {
      loadImage(img);
    });
  }

  /**
   * Observar todas as imagens com data-src
   */
  function observeImages() {
    const images = document.querySelectorAll('img[data-src]');
    images.forEach((img) => {
      imageObserver.observe(img);
    });
  }

  // Iniciar quando DOM estiver pronto (usando DOMContentLoaded, não document.readyState)
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', observeImages);
  } else {
    // Se DOM já está pronto, agendar para próximo idle
    scheduleWork(observeImages);
  }

  // Exportar funções públicas
  window.lazyLoadImage = function(element) {
    loadImage(element);
    imageObserver.unobserve(element);
  };

  window.reinitLazyLoad = function() {
    observeImages();
  };
})();

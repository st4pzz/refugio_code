/**
 * Lazy Loading com Intersection Observer
 * Carrega imagens sob demanda para otimizar performance
 */

(function() {
  'use strict';

  // Verificar suporte para Intersection Observer
  if (!('IntersectionObserver' in window)) {
    console.warn('IntersectionObserver não suportado. Carregando imagens normalmente.');
    loadAllImages();
    return;
  }

  // Configuração do Intersection Observer
  const observerOptions = {
    root: null,
    rootMargin: '50px', // Começar a carregar 50px antes da imagem aparecer na tela
    threshold: 0.01
  };

  const imageObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        const img = entry.target;
        
        // Se tem data-src, carregar WebP
        if (img.dataset.src) {
          loadImage(img);
        }
        
        // Parar de observar após carregamento
        imageObserver.unobserve(img);
      }
    });
  }, observerOptions);

  /**
   * Carrega a imagem a partir de data-src para data-src
   */
  function loadImage(img) {
    // Se é uma picture, carregar todas as sources
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
    
    // Carregar a imagem principal
    if (img.dataset.src) {
      img.src = img.dataset.src;
      img.removeAttribute('data-src');
    }
    
    // Adicionar classe para animação fade-in
    img.classList.add('loaded');
  }

  /**
   * Fallback: carrega todas as imagens se Intersection Observer falhar
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

  // Iniciar quando DOM estiver pronto
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', observeImages);
  } else {
    observeImages();
  }

  // Exportar função para uso manual se necessário
  window.lazyLoadImage = function(element) {
    loadImage(element);
    imageObserver.unobserve(element);
  };

  // Função para reobservar imagens adicionadas dinamicamente
  window.reinitLazyLoad = function() {
    observeImages();
  };
})();

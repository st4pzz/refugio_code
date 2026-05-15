/**
 * Registra o Service Worker
 */

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/service-worker.js')
      .then(registration => {
        console.log('✅ Service Worker registrado com sucesso:', registration);
      })
      .catch(error => {
        console.warn('⚠️ Erro ao registrar Service Worker:', error);
      });
  });
} else {
  console.warn('Service Workers não são suportados neste navegador');
}

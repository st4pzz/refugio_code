/**
 * Service Worker para Cache Inteligente
 * Cache First para imagens estáticas
 * Network First para HTML
 */

const CACHE_VERSION = 'v1-refugio-20260515';
const CACHE_IMAGES = CACHE_VERSION + '-images';
const CACHE_STATIC = CACHE_VERSION + '-static';
const CACHE_DYNAMIC = CACHE_VERSION + '-dynamic';

const STATIC_ASSETS = [
  '/',
  '/index.php',
  '/style.css',
  '/assets/js/lazy-load.js',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
  'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&family=Playfair+Display:wght@700&display=swap'
];

/**
 * Install event - cache static assets
 */
self.addEventListener('install', event => {
  console.log('[Service Worker] Installing...');
  event.waitUntil(
    caches.open(CACHE_STATIC).then(cache => {
      console.log('[Service Worker] Caching static assets');
      return cache.addAll(STATIC_ASSETS).catch(err => {
        console.warn('[Service Worker] Some static assets could not be cached:', err);
      });
    })
  );
  self.skipWaiting();
});

/**
 * Activate event - clean up old caches
 */
self.addEventListener('activate', event => {
  console.log('[Service Worker] Activating...');
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName.startsWith('v1-refugio') && cacheName !== CACHE_STATIC && 
              cacheName !== CACHE_IMAGES && cacheName !== CACHE_DYNAMIC) {
            console.log('[Service Worker] Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  self.clients.claim();
});

/**
 * Fetch event - implement caching strategy
 */
self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);

  // Ignorar requisições não-GET
  if (request.method !== 'GET') {
    return;
  }

  // Strategy 1: Cache First para imagens (WebP, JPG, PNG)
  if (/\.(webp|jpg|jpeg|png)$/i.test(url.pathname)) {
    event.respondWith(
      caches.open(CACHE_IMAGES).then(cache => {
        return cache.match(request).then(response => {
          if (response) {
            console.log('[Service Worker] Serving from cache (image):', url.pathname);
            return response;
          }

          return fetch(request).then(networkResponse => {
            if (!networkResponse || networkResponse.status !== 200) {
              return networkResponse;
            }

            const responseToCache = networkResponse.clone();
            cache.put(request, responseToCache).catch(err => {
              console.warn('[Service Worker] Failed to cache image:', err);
            });

            console.log('[Service Worker] Cached new image:', url.pathname);
            return networkResponse;
          }).catch(err => {
            console.warn('[Service Worker] Fetch failed for image:', url.pathname, err);
            // Retornar uma imagem placeholder ou do cache se disponível
            return cache.match(request) || new Response('No image cached', { status: 404 });
          });
        });
      })
    );
  }
  
  // Strategy 2: Cache First para CSS e JS
  else if (/\.(css|js)$/i.test(url.pathname) || url.hostname === 'cdnjs.cloudflare.com' || url.hostname === 'fonts.googleapis.com') {
    event.respondWith(
      caches.open(CACHE_STATIC).then(cache => {
        return cache.match(request).then(response => {
          if (response) {
            console.log('[Service Worker] Serving from cache (static):', url.pathname);
            return response;
          }

          return fetch(request).then(networkResponse => {
            if (!networkResponse || networkResponse.status !== 200) {
              return networkResponse;
            }

            const responseToCache = networkResponse.clone();
            cache.put(request, responseToCache);

            return networkResponse;
          }).catch(err => {
            console.warn('[Service Worker] Fetch failed for static:', url.pathname);
            return cache.match(request);
          });
        });
      })
    );
  }
  
  // Strategy 3: Network First para HTML (sempre tentar rede primeiro)
  else if (url.pathname.endsWith('/') || /\.php$/.test(url.pathname) || /\.html$/.test(url.pathname)) {
    event.respondWith(
      fetch(request).then(networkResponse => {
        if (!networkResponse || networkResponse.status !== 200) {
          return networkResponse;
        }

        const responseToCache = networkResponse.clone();
        caches.open(CACHE_DYNAMIC).then(cache => {
          cache.put(request, responseToCache);
        });

        return networkResponse;
      }).catch(err => {
        console.warn('[Service Worker] Network failed, trying cache:', url.pathname);
        return caches.match(request).then(cachedResponse => {
          return cachedResponse || new Response('Offline - page not cached', { status: 503 });
        });
      })
    );
  }
  
  // Default: Network First
  else {
    event.respondWith(
      fetch(request).then(networkResponse => {
        if (!networkResponse || networkResponse.status !== 200) {
          return networkResponse;
        }

        const responseToCache = networkResponse.clone();
        caches.open(CACHE_DYNAMIC).then(cache => {
          cache.put(request, responseToCache);
        });

        return networkResponse;
      }).catch(err => {
        console.warn('[Service Worker] Fetch failed:', url.pathname);
        return caches.match(request);
      })
    );
  }
});

/**
 * Handle messages from clients
 */
self.addEventListener('message', event => {
  if (event.data && event.data.type === 'CLEAR_CACHE') {
    console.log('[Service Worker] Clearing all caches...');
    caches.keys().then(cacheNames => {
      Promise.all(cacheNames.map(cacheName => caches.delete(cacheName)));
    });
  }
});

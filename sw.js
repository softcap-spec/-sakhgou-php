// СахGO service worker — лёгкий оффлайн-кэш для статики
const CACHE = 'sakhgo-v1';
const STATIC_ASSETS = [
  '/',
  '/manifest.json',
  '/favicon.png',
  '/favicon.ico',
  '/includes/style.css'
];

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(CACHE).then((c) => c.addAll(STATIC_ASSETS).catch(() => {}))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

// Network-first for pages, cache-first for static assets
self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url);
  // Skip non-GET, API, and cross-origin
  if (e.request.method !== 'GET') return;
  if (url.pathname.startsWith('/api/')) return;
  if (url.origin !== location.origin) return;

  const isStatic = /\.(css|js|png|jpg|jpeg|svg|ico|woff2?)$/.test(url.pathname);

  if (isStatic) {
    e.respondWith(
      caches.match(e.request).then((cached) => {
        return cached || fetch(e.request).then((resp) => {
          const clone = resp.clone();
          caches.open(CACHE).then((c) => c.put(e.request, clone));
          return resp;
        });
      })
    );
    return;
  }

  // Pages: network-first, fallback to cache
  e.respondWith(
    fetch(e.request)
      .then((resp) => {
        const clone = resp.clone();
        caches.open(CACHE).then((c) => c.put(e.request, clone));
        return resp;
      })
      .catch(() => caches.match(e.request))
  );
});

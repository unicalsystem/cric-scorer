/* Cric-Scorer service worker - caches the app shell so it opens instantly like an app */
const CACHE = 'cric-scorer-v1.34.0';
const SHELL = ['./manifest.json',
  './assets/icons/icon-192.png', './assets/icons/icon-512.png', './assets/icons/icon-180.png'];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(SHELL)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', e => {
  const url = new URL(e.request.url);
  if (e.request.method !== 'GET' || url.pathname.endsWith('api.php') || url.pathname.endsWith('install.php') || /\/m\/\d+\/?$/.test(url.pathname) || url.pathname.includes('/uploads/')) return;   // live match pages and photos always come from the network
  // Network first (always fresh when online), fall back to cache when offline
  e.respondWith(
    fetch(e.request).then(res => {
      if (res.ok && url.origin === location.origin) { const copy = res.clone(); caches.open(CACHE).then(c => c.put(e.request, copy)); }
      return res;
    }).catch(() => caches.match(e.request).then(r => r || caches.match('./index.php')))
  );
});

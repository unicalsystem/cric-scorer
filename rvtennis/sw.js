/* RV Tennis - makes the site installable and lets the app open with a weak signal.
   Pages and assets: fresh copy from the network first, the saved copy if the network fails. Scores (api.php) are never saved. */
const CACHE = 'rvtennis-v1.1.0-b';
const SHELL = ['./', 'assets/app.css', 'assets/app.js', 'assets/logo.png', 'assets/icon-192.png', 'manifest.json'];
self.addEventListener('install', e => e.waitUntil(caches.open(CACHE).then(c => c.addAll(SHELL)).catch(() => {}).then(() => self.skipWaiting())));
self.addEventListener('activate', e => e.waitUntil(caches.keys().then(ks => Promise.all(ks.filter(k => k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim())));
self.addEventListener('fetch', e => {
  const url = new URL(e.request.url);
  if (e.request.method !== 'GET' || url.origin !== location.origin || url.pathname.endsWith('api.php')) return;
  e.respondWith(fetch(e.request).then(res => {
    if (res.ok) { const copy = res.clone(); caches.open(CACHE).then(c => c.put(e.request, copy)); }
    return res;
  }).catch(() => caches.match(e.request, { ignoreSearch: true }).then(hit => hit || (e.request.mode === 'navigate' ? caches.match('./') : Response.error()))));
});

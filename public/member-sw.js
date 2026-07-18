const CACHE_NAME = 'goshen-member-shell-v8';
const APP_SHELL = [
  '/member-manifest.json',
  '/favicon.ico',
  '/favicon.png',
  '/icons/goshen-icon-192.png',
  '/icons/goshen-icon-512.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(APP_SHELL))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(
      keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
    ))
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const request = event.request;

  if (request.method !== 'GET') {
    return;
  }

  const acceptsHtml = request.headers.get('accept')?.includes('text/html');

  if (request.mode === 'navigate' || acceptsHtml) {
    event.respondWith(
      fetch(request, { cache: 'no-store' }).catch(() => new Response(
        '<!doctype html><title>Offline</title><meta name="viewport" content="width=device-width, initial-scale=1"><body style="font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;margin:2rem;color:#0c2230"><h1>You are offline</h1><p>Please reconnect to continue using the Goshen Retreat portal.</p></body>',
        {
          headers: { 'Content-Type': 'text/html; charset=utf-8' },
          status: 503,
          statusText: 'Offline',
        }
      ))
    );
    return;
  }

  event.respondWith(
    caches.match(request).then((cached) => cached || fetch(request))
  );
});

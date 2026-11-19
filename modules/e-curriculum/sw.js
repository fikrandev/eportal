const CACHE_NAME = 'e-curriculum-v2';

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))
        )).then(() => clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;
    const url = new URL(event.request.url);
    if (url.origin !== self.location.origin || url.pathname.includes('/api/') || url.pathname.endsWith('.php')) return;
    if (!['style', 'script', 'font', 'image'].includes(event.request.destination)) return;

    if (url.searchParams.has('v')) {
        event.respondWith(
            caches.match(event.request).then((cached) => {
                if (cached) return cached;
                return fetch(event.request).then((res) => {
                    if (res && res.ok) {
                        const clone = res.clone();
                        caches.open(CACHE_NAME).then((c) => c.put(event.request, clone));
                    }
                    return res;
                });
            })
        );
    }
});

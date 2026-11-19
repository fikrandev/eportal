/**
 * Service Worker for Guru App PWA
 */
const CACHE_NAME = 'guru-app-v7';
const SHELL_ASSETS = [
    './',
    './assets/css/guru.css',
    './assets/js/guru.js'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(SHELL_ASSETS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

async function putDeduped(request, response) {
    const cache = await caches.open(CACHE_NAME);
    const reqUrl = new URL(request.url);
    const keys = await cache.keys();
    for (const k of keys) {
        const kUrl = new URL(k.url);
        if (kUrl.origin === reqUrl.origin && kUrl.pathname === reqUrl.pathname && k.url !== request.url) {
            await cache.delete(k);
        }
    }
    await cache.put(request, response);
}

self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET') return;
    const url = new URL(event.request.url);
    if (url.pathname.includes('/api/') || url.searchParams.has('token')) return;

    // Cache-first for immutable filemtime-versioned static assets
    if (url.origin === self.location.origin && url.searchParams.has('v') && !url.pathname.endsWith('.php')) {
        event.respondWith(
            caches.match(event.request).then(cached => {
                if (cached) return cached;
                return fetch(event.request).then(res => {
                    if (res && res.ok) {
                        const clone = res.clone();
                        putDeduped(event.request, clone);
                    }
                    return res;
                });
            })
        );
        return;
    }

    event.respondWith(
        fetch(event.request)
            .then(response => {
                if (response && response.ok && url.origin === self.location.origin && !url.pathname.endsWith('.php')) {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
                }
                return response;
            })
            .catch(() => caches.match(event.request).then(res => res || caches.match('./')))
    );
});

const CACHE_NAME = 'eportal-v1.2.0';
const ASSETS_TO_CACHE = [
    'assets/css/app.css',
    'assets/css/login.css',
    'assets/css/dashboard.css',
    'assets/js/core-utils.js',
    'assets/js/app.js',
    'assets/js/auth.js',
    'assets/js/dashboard.js',
    'assets/js/modal.js',
    'assets/js/pwa.js',
    'assets/vendor/jquery-3.7.1.min.js'
];

// Install
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(ASSETS_TO_CACHE)).then(() => self.skipWaiting())
    );
});

// Activate — evict legacy caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => Promise.all(
            cacheNames.filter((name) => name !== CACHE_NAME).map((name) => caches.delete(name))
        )).then(() => self.clients.claim())
    );
});

// Evict stale cached copies sharing the same pathname when storing a newly versioned static asset
async function putVersionedAsset(request, response) {
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

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;

    const url = new URL(event.request.url);
    const isSameOrigin = url.origin === self.location.origin;

    // Never cache URLs containing auth tokens
    if (url.searchParams.has('token')) {
        return;
    }

    // API and PHP documents: network first, safe offline fallback
    if (url.pathname.includes('/api/') || event.request.mode === 'navigate' || url.pathname.endsWith('.php')) {
        event.respondWith(
            fetch(event.request).then((response) => {
                if (response && response.status === 200 && event.request.mode === 'navigate' && !url.pathname.includes('/modules/')) {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
                }
                return response;
            }).catch(() => {
                if (url.pathname.includes('/api/')) {
                    return new Response(JSON.stringify({
                        success: false,
                        message: 'Anda sedang offline.'
                    }), {
                        status: 503,
                        headers: { 'Content-Type': 'application/json' }
                    });
                }
                return caches.match(event.request).then((res) => res || caches.match('./'));
            })
        );
        return;
    }

    // Versioned static assets (immutable per filemtime ?v=...): cache first, prune stale versions
    if (isSameOrigin && url.searchParams.has('v')) {
        event.respondWith(
            caches.match(event.request).then((cached) => {
                if (cached) return cached;
                return fetch(event.request).then((fetchRes) => {
                    if (fetchRes && fetchRes.status === 200) {
                        const clone = fetchRes.clone();
                        putVersionedAsset(event.request, clone);
                    }
                    return fetchRes;
                });
            })
        );
        return;
    }

    // Unversioned module or static assets: network first
    if (isSameOrigin && url.pathname.includes('/modules/')) {
        event.respondWith(
            fetch(event.request).then((response) => {
                if (response && response.status === 200) {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
                }
                return response;
            }).catch(() => caches.match(event.request))
        );
        return;
    }

    // Other static assets: cache first
    event.respondWith(
        caches.match(event.request).then((response) => {
            return response || fetch(event.request).then((fetchResponse) => {
                if (fetchResponse && fetchResponse.status === 200 && url.protocol.startsWith('http')) {
                    const clone = fetchResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
                }
                return fetchResponse;
            });
        }).catch(() => {
            if (event.request.destination === 'document') {
                return caches.match('./');
            }
        })
    );
});

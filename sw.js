const CACHE_NAME = 'eportal-v1.1.13';
const ASSETS_TO_CACHE = [
    'assets/css/app.css',
    'assets/css/login.css',
    'assets/css/dashboard.css',
    'assets/css/admin.css',
    'assets/js/app.js',
    'assets/js/auth.js',
    'assets/js/dashboard.js',
    'assets/js/admin.js',
    'assets/js/modal.js',
    'assets/js/pwa.js',
    'assets/vendor/jquery-3.7.1.min.js'
];

// Install
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(ASSETS_TO_CACHE);
        }).then(() => self.skipWaiting())
    );
});

// Activate
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.filter((name) => name !== CACHE_NAME)
                    .map((name) => caches.delete(name))
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch - network first for dynamic app shell/API, cache first for static assets.
self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;

    const url = new URL(event.request.url);
    const isSameOrigin = url.origin === self.location.origin;
    const isModuleAsset = isSameOrigin && url.pathname.includes('/modules/');
    const isVersionedAsset = isSameOrigin && url.searchParams.has('v');

    // API and PHP documents are dynamic, so always try network first.
    if (url.pathname.includes('/api/') || event.request.mode === 'navigate' || url.pathname.endsWith('.php')) {
        event.respondWith(
            fetch(event.request).then((response) => {
                if (response && response.status === 200 && event.request.mode === 'navigate') {
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
                        headers: { 'Content-Type': 'application/json' }
                    });
                }
                return caches.match(event.request) || caches.match('./');
            })
        );
        return;
    }

    // Module assets / versioned assets are also dynamic enough to prefer network first.
    if (isModuleAsset || isVersionedAsset) {
        event.respondWith(
            fetch(event.request).then((response) => {
                if (response && response.status === 200 && isSameOrigin) {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
                }
                return response;
            }).catch(() => caches.match(event.request))
        );
        return;
    }

    // Static assets - cache first
    event.respondWith(
        caches.match(event.request).then((response) => {
            return response || fetch(event.request).then((fetchResponse) => {
                // Cache new resources (only http/https)
                if (fetchResponse.status === 200 && url.protocol.startsWith('http')) {
                    const clone = fetchResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, clone);
                    });
                }
                return fetchResponse;
            });
        }).catch(() => {
            // Offline fallback
            if (event.request.destination === 'document') {
                return caches.match('./');
            }
        })
    );
});

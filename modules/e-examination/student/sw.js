const CACHE_NAME = 'cbt-student-v1';
const ASSETS = [
    './login.php',
    '../assets/css/examination.css'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => cache.addAll(ASSETS)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => Promise.all(
            keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))
        )).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET' || event.request.url.includes('/api/')) return;
    event.respondWith(
        fetch(event.request).then(res => {
            if (res.ok) {
                const clone = res.clone();
                caches.open(CACHE_NAME).then(c => c.put(event.request, clone));
            }
            return res;
        }).catch(() => caches.match(event.request))
    );
});

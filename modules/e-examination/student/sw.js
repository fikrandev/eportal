const CACHE_NAME = 'cbt-student-v3';
const ASSETS = [
    '../assets/css/examination.css',
    '../assets/js/student_exam.js'
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
    if (event.request.method !== 'GET') return;
    const url = new URL(event.request.url);
    // Never cache API calls or authenticated PHP pages
    if (url.pathname.includes('/api/') || url.pathname.endsWith('.php')) return;

    const dest = event.request.destination;
    if (!['style', 'script', 'font', 'image'].includes(dest)) return;

    event.respondWith(
        caches.match(event.request).then(cached => {
            if (cached && url.searchParams.has('v')) return cached;
            return fetch(event.request).then(res => {
                if (res && res.ok && url.origin === self.location.origin) {
                    const clone = res.clone();
                    caches.open(CACHE_NAME).then(c => c.put(event.request, clone));
                }
                return res;
            }).catch(() => cached);
        })
    );
});

/*
 * King Maelick · Pointage — service worker.
 * Keeps the punch page and the kiosk page usable when the network drops (HTTPS only).
 */
const CACHE = 'king-maelick-v1';
const OFFLINE_PAGES = [/^\/mon-pointage$/, /^\/borne\/[^/]+$/];

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/images/') || /\.(png|ico|webmanifest)$/.test(url.pathname)) {
        event.respondWith(
            caches.match(request).then(
                (cached) =>
                    cached ??
                    fetch(request).then((response) => {
                        if (response.ok) {
                            const copy = response.clone();
                            caches.open(CACHE).then((cache) => cache.put(request, copy));
                        }

                        return response;
                    }),
            ),
        );

        return;
    }

    const isOfflinePage = request.mode === 'navigate' && OFFLINE_PAGES.some((pattern) => pattern.test(url.pathname));

    if (!isOfflinePage) {
        return;
    }

    const cacheKey = new Request(url.origin + url.pathname);

    event.respondWith(
        fetch(request)
            .then((response) => {
                if (response.ok && !response.redirected) {
                    const copy = response.clone();
                    caches.open(CACHE).then((cache) => cache.put(cacheKey, copy));
                }

                return response;
            })
            .catch(() => caches.match(cacheKey).then((cached) => cached ?? Response.error())),
    );
});

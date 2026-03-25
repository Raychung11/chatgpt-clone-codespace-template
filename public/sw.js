/**
 * Service Worker for F&B Loyalty PWA
 * /public/sw.js
 */

const CACHE_NAME = 'fnb-pwa-v1';
const STATIC_ASSETS = [
    '/app/',
    '/app/login',
    '/app/dashboard',
    '/public/css/app.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
];

// Install: cache static assets
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => cache.addAll(STATIC_ASSETS.filter(Boolean)))
    );
    self.skipWaiting();
});

// Activate: clean old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
        )
    );
    self.clients.claim();
});

// Fetch: network-first for API, cache-first for static
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // Always network for API calls
    if (url.pathname.startsWith('/api/')) {
        event.respondWith(fetch(event.request));
        return;
    }

    // Cache-first for static assets
    event.respondWith(
        caches.match(event.request).then(cached => {
            if (cached) return cached;
            return fetch(event.request).then(response => {
                if (response.ok && event.request.method === 'GET') {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
                }
                return response;
            });
        }).catch(() => {
            // Offline fallback
            if (event.request.destination === 'document') {
                return caches.match('/app/');
            }
        })
    );
});

// Push notifications
self.addEventListener('push', event => {
    const data = event.data ? event.data.json() : { title: 'New Notification', body: '' };
    event.waitUntil(
        self.registration.showNotification(data.title, {
            body:    data.body    || '',
            icon:    data.icon    || '/public/icons/icon-192.png',
            badge:   data.badge   || '/public/icons/icon-72.png',
            data:    data.data    || {},
        })
    );
});

self.addEventListener('notificationclick', event => {
    event.notification.close();
    event.waitUntil(clients.openWindow(event.notification.data.url || '/app/'));
});

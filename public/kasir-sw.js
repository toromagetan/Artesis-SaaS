// Service worker klien kasir offline Lentera (F5.3b) — hanya
// menyimpan cangkang halaman kasir agar dapat dibuka tanpa
// jaringan. Panggilan API tidak pernah disimpan (data selalu
// segar dari server / snapshot lokal aplikasi).
const CACHE = 'lentera-kasir-v1';
const SHELL = ['/kasir-offline'];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim()),
  );
});

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);
  if (url.pathname.startsWith('/api/')) {
    return; // API: jaringan saja, jangan pernah cache.
  }
  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request)
        .then((response) => {
          const copy = response.clone();
          caches.open(CACHE).then((cache) => cache.put('/kasir-offline', copy));
          return response;
        })
        .catch(() => caches.match('/kasir-offline')),
    );
  }
});

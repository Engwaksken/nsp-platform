// Hive NSP service worker. Deliberately small:
// - pages are always fetched from the network and never stored (they hold private data);
//   if the network fails, the cached /offline page is shown instead;
// - versioned static files (CSS, JS, fonts, icons) are cached after first use.
// Bump VERSION to drop old caches.
var VERSION = 'nsp-v2';
var OFFLINE_URL = '/offline';

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(VERSION).then(function (cache) { return cache.add(new Request(OFFLINE_URL, { cache: 'reload' })); })
      .then(function () { return self.skipWaiting(); })
  );
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.filter(function (key) { return key !== VERSION; }).map(function (key) { return caches.delete(key); }));
    }).then(function () { return self.clients.claim(); })
  );
});

function isStatic(url) {
  return url.origin === self.location.origin && /^\/(css|js|fonts|icons|vendor)\//.test(url.pathname);
}

self.addEventListener('fetch', function (event) {
  var request = event.request;
  if (request.method !== 'GET') return;

  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(function () {
      return caches.match(OFFLINE_URL);
    }));
    return;
  }

  var url = new URL(request.url);
  if (isStatic(url)) {
    event.respondWith(caches.open(VERSION).then(function (cache) {
      return cache.match(request).then(function (hit) {
        return hit || fetch(request).then(function (response) {
          if (response.ok) cache.put(request, response.clone());
          return response;
        });
      });
    }));
  }
});

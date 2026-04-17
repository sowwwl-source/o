const VERSION = "sowwwl-site-v1";
const APP_SHELL = [
	"/",
	"/index.php",
	"/styles.css",
	"/main.js",
	"/manifest.json",
	"/favicon.svg",
	"/icons/icon.svg",
	"/icons/icon-mask.svg",
];

self.addEventListener("install", (event) => {
	event.waitUntil(
		caches.open(VERSION).then((cache) => cache.addAll(APP_SHELL))
	);
	self.skipWaiting();
});

self.addEventListener("activate", (event) => {
	event.waitUntil(
		caches.keys().then((keys) =>
			Promise.all(
				keys
					.filter((key) => key !== VERSION)
					.map((key) => caches.delete(key))
			)
		)
	);
	self.clients.claim();
});

self.addEventListener("fetch", (event) => {
	const { request } = event;

	if (request.method !== "GET") {
		return;
	}

	const url = new URL(request.url);

	if (url.origin !== self.location.origin) {
		return;
	}

	if (request.mode === "navigate") {
		event.respondWith(
			fetch(request)
				.then((response) => {
					if (response.ok) {
						const clone = response.clone();
						caches.open(VERSION).then((cache) => cache.put(request, clone));
					}
					return response;
				})
				.catch(async () => (await caches.match(request)) || caches.match("/"))
		);
		return;
	}

	event.respondWith(
		caches.match(request).then((cached) => {
			if (cached) {
				return cached;
			}

			return fetch(request).then((response) => {
				if (response.ok) {
					const clone = response.clone();
					caches.open(VERSION).then((cache) => cache.put(request, clone));
				}
				return response;
			});
		})
	);
});

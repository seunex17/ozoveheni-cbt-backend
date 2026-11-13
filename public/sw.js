const OFFLINE_CACHE = "ozoveheni-offline-v1";
const OFFLINE_PAGE = "/offline.html";

self.addEventListener("install", (event) => {
    event.waitUntil(caches.open(OFFLINE_CACHE).then((cache) => cache.add(new Request(OFFLINE_PAGE, { cache: "reload" }))));
    self.skipWaiting();
});

self.addEventListener("activate", (event) => {
    event.waitUntil(Promise.all([
        caches.keys().then((names) => Promise.all(names
            .filter((name) => name.startsWith("ozoveheni-offline-") && name !== OFFLINE_CACHE)
            .map((name) => caches.delete(name)))),
        self.clients.claim(),
    ]));
});

self.addEventListener("fetch", (event) => {
    const request = event.request;
    const url = new URL(request.url);
    // Leave PDFs, exports, uploads, API calls and Inertia requests to the browser.
    if (request.method !== "GET" || request.mode !== "navigate" || !["http:", "https:"].includes(url.protocol) || url.origin !== self.location.origin
        || url.pathname.startsWith("/reports/") || url.pathname.startsWith("/student-result-slip/")
        || url.pathname.startsWith("/api/") || url.pathname === "/dashboard/voucher/generate"
        || url.pathname === "/dashboard/exam/download-template"
        || /\.(pdf|csv|xlsx?|docx?|zip)$/i.test(url.pathname)) {
        return;
    }
    event.respondWith(fetch(request).catch(async () => {
        const cache = await caches.open(OFFLINE_CACHE);
        return await cache.match(OFFLINE_PAGE) || Response.error();
    }));
});

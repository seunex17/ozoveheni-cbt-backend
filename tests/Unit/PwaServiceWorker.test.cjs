const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../public/sw.js'), 'utf8');

function worker() {
    const handlers = {}, added = [], removed = [], fetched = [];
    const fallback = new Response('Server unavailable', { headers: { 'Content-Type': 'text/html' } });
    const state = { offline: false, response: new Response('Live response'), fallback, skipped: false, claimed: false };
    const cache = { add: async (request) => added.push(request), match: async () => state.fallback };
    vm.runInNewContext(source, {
        self: { location: { origin: 'http://localhost' }, clients: { claim: async () => { state.claimed = true; } }, skipWaiting: () => { state.skipped = true; }, addEventListener: (name, handler) => handlers[name] = handler },
        caches: { open: async () => cache, keys: async () => ['ozoveheni-offline-v0', 'ozoveheni-offline-v1', 'another-app-cache'], delete: async (name) => removed.push(name) },
        Request: class { constructor(url, options) { this.url = url; this.cache = options.cache; } },
        Response, URL,
        fetch: async (request) => { fetched.push(request); if (state.offline) throw new Error('Server unavailable'); return state.response; },
    });
    async function lifecycle(name) {
        let pending;
        handlers[name]({ waitUntil(promise) { pending = promise; } });
        await pending;
    }
    async function fetchRequest(url, options = {}) {
        let response;
        handlers.fetch({ request: { url, mode: 'navigate', method: 'GET', ...options }, respondWith(promise) { response = promise; } });
        return response ? await response : null;
    }
    return { state, added, removed, fetched, lifecycle, fetchRequest };
}

test('installation caches only the generic offline page', async () => {
    const sw = worker();
    await sw.lifecycle('install');
    assert.equal(sw.added.length, 1);
    assert.equal(sw.added[0].url, '/offline.html');
    assert.equal(sw.added[0].cache, 'reload');
    assert.equal(sw.state.skipped, true);
});

test('activation removes only obsolete caches owned by this app', async () => {
    const sw = worker();
    await sw.lifecycle('activate');
    assert.deepEqual(sw.removed, ['ozoveheni-offline-v0']);
    assert.equal(sw.state.claimed, true);
});

test('dashboard navigations always fetch fresh server responses', async () => {
    const sw = worker();
    const response = await sw.fetchRequest('http://localhost/dashboard');
    assert.equal(response, sw.state.response);
    assert.equal(sw.fetched.length, 1);
    assert.equal(sw.added.length, 0);
});

test('only failed document navigations receive the generic offline page', async () => {
    const sw = worker();
    sw.state.offline = true;
    assert.equal(await sw.fetchRequest('http://localhost/dashboard/student'), sw.state.fallback);
    assert.equal(sw.added.length, 0);
    sw.state.fallback = undefined;
    assert.equal((await sw.fetchRequest('http://localhost/dashboard')).type, 'error');
});

test('HTTP error and redirect responses are preserved instead of replaced by an offline page', async () => {
    const sw = worker();
    sw.state.response = new Response('Unauthorized', { status: 401 });
    assert.equal((await sw.fetchRequest('http://localhost/dashboard')).status, 401);
    sw.state.response = Response.redirect('http://localhost/login');
    assert.equal((await sw.fetchRequest('http://localhost/dashboard')).status, 302);
});

test('PDFs exports API calls blobs and external addresses bypass interception even offline', async () => {
    const sw = worker();
    sw.state.offline = true;
    for (const url of [
        'http://localhost/reports/exam-id', 'http://localhost/student-result-slip/exam/student',
        'http://localhost/dashboard/voucher/generate?qty=5', 'http://localhost/dashboard/exam/download-template',
        'http://localhost/api/practice/papers/example/questions', 'http://localhost/export.xlsx',
        'http://localhost/report.pdf', 'http://localhost/export.csv', 'http://other-server/dashboard',
        'blob:http://localhost/1234',
    ]) assert.equal(await sw.fetchRequest(url), null, url);
    assert.equal(sw.fetched.length, 0);
});

test('POST requests and Inertia or asset fetches never pass through the worker', async () => {
    const sw = worker();
    assert.equal(await sw.fetchRequest('http://localhost/dashboard/exam/edit', { method: 'POST' }), null);
    assert.equal(await sw.fetchRequest('http://localhost/dashboard/student', { mode: 'cors' }), null);
    assert.equal(await sw.fetchRequest('http://localhost/build/app.js', { mode: 'no-cors' }), null);
    assert.equal(sw.fetched.length, 0);
});

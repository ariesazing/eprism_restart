import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

function browser() {
    const handlers = {}, broadcasts = {}, timers = new Map();
    let timerId = 0, requests = 0, replacements = 0;
    const positions = [];
    const fresh = { querySelectorAll: () => [] };
    const root = {
        contains: () => false, querySelectorAll: () => [],
        replaceWith: () => { replacements++; },
    };
    const channel = { notification() {}, listen(name, handler) { broadcasts[name] = handler; return this; } };
    const context = {
        console, Set, URL,
        setTimeout: callback => { timers.set(++timerId, callback); return timerId; },
        clearTimeout: id => timers.delete(id), setInterval() {},
        scrollX: 0, scrollY: 720, scrollTo: value => positions.push(value),
        requestAnimationFrame: callback => callback(),
        location: { href: 'https://app.test/admin/reports?search=study', reload() {} },
        fetch: async (url, options) => {
            requests++;
            assert.match(url, /search=study/);
            assert.equal(options.cache, 'no-store');
            return { ok: true, text: async () => '<html></html>' };
        },
        DOMParser: class { parseFromString() { return { querySelector: () => fresh }; } },
        document: {
            hidden: false, activeElement: {},
            body: { dataset: { userId: '1', userRole: 'admin' }, classList: { contains: () => false } },
            querySelector: selector => selector.includes('admin-page') ? root : null,
            addEventListener: (name, handler) => { handlers[name] = handler; },
        },
        window: { Echo: { private: () => channel } },
    };
    vm.runInNewContext(readFileSync('resources/js/live-refresh.js', 'utf8'), context);
    handlers.DOMContentLoaded();
    return { handlers, broadcasts, positions, requests: () => requests, replacements: () => replacements,
        async flush() { const callbacks = [...timers.values()]; timers.clear(); for (const callback of callbacks) await callback(); } };
}

test('admin events coalesce into one filtered refresh and retain scroll position', async () => {
    const b = browser();
    b.broadcasts['.submission-activity']();
    b.broadcasts['.admin-data-changed']();
    await b.flush();
    assert.equal(b.requests(), 1);
    assert.equal(b.replacements(), 1);
    assert.equal(b.positions[0].top, 720);
});

test('live updates do not replace unsaved form input', async () => {
    const b = browser();
    b.handlers.input({ target: { closest: () => ({}) } });
    b.broadcasts['.admin-data-changed']();
    await b.flush();
    assert.equal(b.requests(), 0);
    assert.equal(b.replacements(), 0);
});

import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

function browser() {
    const handlers = {};
    const storage = new Map();
    const scrolls = [];
    let reloads = 0;
    const context = {
        URL, Date, JSON,
        location: { href: 'https://app.test/admin/reports', origin: 'https://app.test', pathname: '/admin/reports', reload() { reloads++; } },
        scrollX: 0, scrollY: 850,
        scrollTo: position => scrolls.push(position),
        requestAnimationFrame: callback => callback(),
        sessionStorage: { setItem: (k, v) => storage.set(k, v), getItem: k => storage.get(k), removeItem: k => storage.delete(k) },
        document: { body: { dataset: { userId: '1' } }, documentElement: { style: {} }, addEventListener: (event, handler) => { handlers[event] = handler; } },
        window: { addEventListener: (event, handler) => { handlers[event] = handler; } },
    };
    vm.runInNewContext(readFileSync('resources/js/navigation-state.js', 'utf8'), context);
    return { handlers, context, scrolls, reloads: () => reloads };
}

test('filter navigation restores the original scroll position once', () => {
    const b = browser();
    b.handlers.submit({ target: { action: b.context.location.href, target: '' } });
    b.context.location.href += '?search=study';
    b.handlers.pageshow({ persisted: false });
    assert.equal(b.scrolls[0].top, 850);
    b.handlers.pageshow({ persisted: false });
    assert.equal(b.scrolls.length, 1);
});

test('sidebar navigation does not restore another page position', () => {
    const b = browser();
    b.handlers.submit({ target: { action: b.context.location.href, target: '' } });
    b.context.location.pathname = '/admin/users';
    b.handlers.pageshow({ persisted: false });
    assert.equal(b.scrolls.length, 0);
});

test('authenticated back-cache restores recheck authorization without revealing old content', () => {
    const b = browser();
    b.handlers.pagehide();
    assert.equal(b.context.document.documentElement.style.visibility, 'hidden');
    b.handlers.pageshow({ persisted: true });
    assert.equal(b.reloads(), 1);
    assert.equal(b.context.document.documentElement.style.visibility, 'hidden');
});

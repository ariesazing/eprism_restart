// Keep position for same-page filters, pagination and actions, not sidebar navigation.
const scrollKey = 'eprism-action-scroll';

window.addEventListener('pagehide', () => {
    if (document.body.dataset.userId) document.documentElement.style.visibility = 'hidden';
});

function rememberPosition(path) {
    try {
        sessionStorage.setItem(scrollKey, JSON.stringify({ path, x: scrollX, y: scrollY, at: Date.now() }));
    } catch {}
}

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (event.defaultPrevented || (event.submitter?.formTarget || form.target) === '_blank') return;
    const url = new URL(form.action || location.href, location.href);
    if (url.origin === location.origin) rememberPosition(location.pathname);
});

document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href]');
    if (!link || event.defaultPrevented || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || link.target === '_blank') return;
    const url = new URL(link.href, location.href);
    if (url.origin === location.origin && url.pathname === location.pathname && !url.hash) rememberPosition(url.pathname);
});

window.addEventListener('pageshow', (event) => {
    if (event.persisted && document.body.dataset.userId) {
        // Force authorization to run again after browser back/forward restoration.
        location.reload();
        return;
    }
    document.documentElement.style.visibility = '';
    try {
        const saved = JSON.parse(sessionStorage.getItem(scrollKey) || 'null');
        sessionStorage.removeItem(scrollKey);
        if (saved?.path === location.pathname && Date.now() - saved.at < 60000) {
            requestAnimationFrame(() => scrollTo({ left: saved.x, top: saved.y, behavior: 'instant' }));
        }
    } catch {}
});

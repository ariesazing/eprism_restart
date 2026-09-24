// Coalesce broadcasts into one filtered page fetch and replace only live regions.
const pending = new Set();
let timer;
let running = false;
let editing = false;

document.addEventListener('input', (event) => {
    if (event.target.closest('[data-live-region="admin-page"] form')) editing = true;
});

function refreshRegion(name) {
    if (!document.querySelector(`[data-live-region="${name}"]`)) return;
    pending.add(name);
    clearTimeout(timer);
    timer = setTimeout(flush, 250);
}

async function flush() {
    if (running || document.hidden) return;
    const admin = document.querySelector('[data-live-region="admin-page"]');
    if (admin && (editing || admin.contains(document.activeElement) && document.activeElement.matches('input, select, textarea') || document.body.classList.contains('overflow-y-hidden'))) {
        timer = setTimeout(flush, 1500);
        return;
    }
    const names = [...pending];
    pending.clear();
    if (!names.length) return;
    running = true;
    const url = location.href;
    try {
        const response = await fetch(url, { cache: 'no-store', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (response.redirected || response.status === 401 || response.status === 403) {
            location.reload();
            return;
        }
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const html = await response.text();
        if (url !== location.href || editing) return;
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const position = { left: scrollX, top: scrollY, behavior: 'instant' };
        for (const name of names) {
            const current = document.querySelector(`[data-live-region="${name}"]`);
            if (!current || name !== 'admin-page' && current.closest('[data-live-region="admin-page"]') && names.includes('admin-page')) continue;
            const fresh = doc.querySelector(`[data-live-region="${name}"]`);
            if (!fresh) continue;
            // Preserve filter panel state and values; refresh the results around it.
            const filters = [...current.querySelectorAll('.research-filter')];
            fresh.querySelectorAll('.research-filter').forEach((filter, index) => {
                if (filters[index]) filter.replaceWith(filters[index]);
            });
            const openDetails = [...current.querySelectorAll('details')].map(el => el.open);
            fresh.querySelectorAll('details').forEach((el, index) => { el.open = openDetails[index] ?? false; });
            current.replaceWith(fresh);
            // Alpine's mutation observer initializes the inserted DOM.
        }
        requestAnimationFrame(() => scrollTo(position));
    } catch (error) {
        console.error('Live refresh failed', error);
    } finally {
        running = false;
        if (pending.size) timer = setTimeout(flush, 250);
    }
}

function init() {
    const userId = document.body.dataset.userId;
    if (!userId) return;
    const admin = document.body.dataset.userRole === 'admin';
    if (window.Echo) {
        const personal = window.Echo.private(`App.Models.User.${userId}`);
        personal.notification(() => refreshRegion('notifications'));
        personal.listen('.submission-activity', () => {
            ['assignment-tracking', 'reviewer-submissions', 'researcher-tracking'].forEach(refreshRegion);
        });
        if (admin) {
            const refreshAdmin = () => refreshRegion('admin-page');
            window.Echo.private('admin.dashboard')
                .listen('.submission-activity', refreshAdmin)
                .listen('.admin-data-changed', refreshAdmin);
            window.Echo.connector?.pusher?.connection?.bind('connected', refreshAdmin);
        }
    }
    if (admin) {
        // Reconcile missed events after reconnects; hidden tabs do not poll.
        setInterval(() => { if (!document.hidden) refreshRegion('admin-page'); }, 30000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshRegion('admin-page'); });
    }
}

document.addEventListener('DOMContentLoaded', init);

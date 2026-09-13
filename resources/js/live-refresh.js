// Generic "live region" refresh: rather than duplicating server-rendered markup in JS (e.g.
// rebuilding the notification dropdown's HTML by hand), any part of a page that should
// update without a reload is wrapped in data-live-region="<name>". On a relevant broadcast,
// refreshRegion() re-fetches the CURRENT page (already correctly scoped/filtered/paginated
// server-side) and morphs in just that one region from the response.
async function refreshRegion(name) {
    const current = document.querySelector(`[data-live-region="${name}"]`);

    if (! current) {
        return;
    }

    try {
        const response = await fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const html = await response.text();
        const fresh = new DOMParser().parseFromString(html, 'text/html').querySelector(`[data-live-region="${name}"]`);

        if (fresh) {
            current.replaceWith(fresh);
            // Alpine only auto-initializes the DOM present at page load — a freshly
            // inserted region (e.g. the Alpine-driven notification dropdown) needs its
            // x-data/x-show/x-transition directives explicitly initialized, or they're
            // just inert markup.
            window.Alpine?.initTree?.(fresh);
        }
    } catch (error) {
        console.error(`Failed to live-refresh region "${name}"`, error);
    }
}

function init() {
    if (! window.Echo) {
        return;
    }

    const userId = document.body.dataset.userId;
    const userRole = document.body.dataset.userRole;

    if (! userId) {
        return;
    }

    const personalChannel = window.Echo.private(`App.Models.User.${userId}`);

    // Laravel's own notification-broadcast event — fires for any notification whose via()
    // includes 'broadcast' (see SubmissionDecisionNotification), on this same channel.
    personalChannel.notification(() => refreshRegion('notifications'));

    personalChannel.listen('.submission-activity', () => {
        refreshRegion('assignment-tracking');
        refreshRegion('reviewer-submissions');
        refreshRegion('researcher-tracking');
    });

    if (userRole === 'admin') {
        window.Echo.private('admin.dashboard').listen('.submission-activity', () => {
            refreshRegion('admin-submissions');
            refreshRegion('admin-oversight');
        });
    }
}

document.addEventListener('DOMContentLoaded', init);

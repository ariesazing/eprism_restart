/**
 * Stages a guest's "start a research submission" fields in localStorage — nothing here
 * ever reaches the server. The only place this data becomes a real ResearchSubmission is
 * the authenticated submissions.store endpoint, claimed automatically from the dashboard
 * once the guest has registered (see the inline script on dashboard.blade.php). Drafts
 * older than STORAGE_EXPIRY_MS are treated as abandoned and silently dropped, so nothing
 * needs a server-side cleanup job for guest drafts that never get claimed.
 */
const STORAGE_KEY = 'eprism_guest_draft';
const STORAGE_EXPIRY_MS = 24 * 60 * 60 * 1000;

document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('guest-draft-form');
    if (! form) {
        return;
    }

    const titleEl = document.getElementById('guest-title');
    const typeEl = document.getElementById('guest-research-type');
    const orgUnitEl = form.querySelector('[data-org-unit]');
    const schoolIdEl = form.querySelector('[data-school-id-input]');
    const lastNameEl = document.getElementById('guest-last-name');
    const firstNameEl = document.getElementById('guest-first-name');
    const middleInitialEl = document.getElementById('guest-middle-initial');
    const positionEl = document.getElementById('guest-position');
    const restoreNotice = document.getElementById('guest-draft-restore-notice');

    function loadDraft() {
        let raw;
        try {
            raw = localStorage.getItem(STORAGE_KEY);
        } catch (e) {
            return null;
        }
        if (! raw) {
            return null;
        }

        let draft;
        try {
            draft = JSON.parse(raw);
        } catch (e) {
            localStorage.removeItem(STORAGE_KEY);
            return null;
        }

        if (! draft.savedAt || Date.now() - draft.savedAt > STORAGE_EXPIRY_MS) {
            localStorage.removeItem(STORAGE_KEY);
            return null;
        }

        return draft;
    }

    const existing = loadDraft();
    if (existing) {
        titleEl.value = existing.title || '';
        typeEl.value = existing.research_type || 'basic';

        if (existing.organizational_unit && orgUnitEl) {
            orgUnitEl.value = existing.organizational_unit;
            // Triggers submission-form-script.blade.php's own change handler, which
            // repopulates the position <select>'s options for this unit's type — the
            // desired position (set below) can only be applied once those options exist.
            orgUnitEl.dispatchEvent(new Event('change'));
        }

        lastNameEl.value = existing.proponent?.last_name || '';
        firstNameEl.value = existing.proponent?.first_name || '';
        middleInitialEl.value = existing.proponent?.middle_initial || '';

        if (existing.proponent?.position && positionEl) {
            positionEl.value = existing.proponent.position;
        }

        if (restoreNotice) {
            restoreNotice.classList.remove('hidden');
        }
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (! form.reportValidity()) {
            return;
        }

        const draft = {
            title: titleEl.value.trim(),
            research_type: typeEl.value,
            classification: 'proposal',
            organizational_unit: orgUnitEl ? orgUnitEl.value : '',
            school_id: schoolIdEl ? schoolIdEl.value : '',
            proponent: {
                last_name: lastNameEl.value.trim(),
                first_name: firstNameEl.value.trim(),
                middle_initial: middleInitialEl.value.trim(),
                position: positionEl ? positionEl.value : '',
            },
            savedAt: Date.now(),
        };

        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(draft));
        } catch (e) {
            // Storage unavailable (private browsing, full quota) — still let them
            // continue to registration; they'll just need to re-enter the draft after.
        }

        window.location.href = form.dataset.registerUrl;
    });
});

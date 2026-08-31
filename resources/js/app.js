import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

// --- Global top progress bar + action spinners ---

const progressBar = document.createElement('div');
progressBar.id = 'page-progress-bar';
document.body.appendChild(progressBar);

let activeRequests = 0;

// --- Page skeleton loader ---
// Shown on the outgoing page the instant a real navigation starts (same click/submit
// triggers as the progress bar below), and faded out on the incoming page once it has
// actually finished loading — not tied to axios/fetch, which stay on the thin progress
// bar only: a full-page skeleton during a background autosave would trap the user.

const pageSkeleton = document.getElementById('page-skeleton');
let skeletonHideTimer = null;

function hideSkeleton() {
    clearTimeout(skeletonHideTimer);
    skeletonHideTimer = null;
    pageSkeleton?.classList.add('is-hidden');
}

function showSkeleton() {
    if (! pageSkeleton) {
        return;
    }

    pageSkeleton.classList.remove('is-hidden');

    // No short fixed-duration hide here on purpose: the skeleton must stay up for
    // however long the real navigation actually takes, not a guessed duration — a
    // slow page load hiding it early would reveal a half-loaded page underneath.
    // 'load'/'pageshow' below do the real hiding. This is only a last-resort ceiling
    // for a request that hangs entirely, and the click/submit call sites already
    // skip calling showSkeleton() for cases that don't really navigate away
    // (target="_blank", formtarget="_blank", data-no-progress).
    clearTimeout(skeletonHideTimer);
    skeletonHideTimer = setTimeout(hideSkeleton, 15000);
}

window.addEventListener('load', hideSkeleton);

function startProgress() {
    activeRequests += 1;
    progressBar.classList.add('is-active');
    progressBar.style.width = '15%';
    requestAnimationFrame(() => {
        progressBar.style.width = '85%';
    });
}

function stopProgress() {
    activeRequests = Math.max(0, activeRequests - 1);

    if (activeRequests > 0) {
        return;
    }

    progressBar.style.width = '100%';
    setTimeout(() => {
        progressBar.classList.remove('is-active');
        setTimeout(() => {
            progressBar.style.width = '0%';
        }, 300);
    }, 150);
}

export function disableWithSpinner(button) {
    if (! button || button.dataset.spinnerApplied) {
        return;
    }

    button.dataset.spinnerApplied = '1';
    button.disabled = true;
    button.innerHTML = `<span class="btn-spinner"></span>${button.textContent.trim()}`;
}

window.disableWithSpinner = disableWithSpinner;

document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href]');

    if (! link || link.hasAttribute('data-no-progress') || link.target === '_blank') {
        return;
    }

    const url = new URL(link.href, window.location.href);
    const sameDocument = url.pathname === window.location.pathname && url.search === window.location.search;

    if (url.origin !== window.location.origin || (sameDocument && url.hash)) {
        return;
    }

    startProgress();
    showSkeleton();
});

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (form.hasAttribute('data-no-progress')) {
        return;
    }

    startProgress();

    // A formtarget="_blank" submitter (e.g. a "Preview" button) opens a new tab —
    // the current page never navigates away, so it shouldn't show the skeleton.
    const opensNewTab = (event.submitter?.getAttribute('formtarget') || form.target) === '_blank';
    if (! opensNewTab) {
        showSkeleton();
    }

    if (event.submitter) {
        disableWithSpinner(event.submitter);
    }
});

window.addEventListener('pageshow', () => {
    activeRequests = 0;
    progressBar.classList.remove('is-active');
    progressBar.style.width = '0%';

    // A back/forward-cache restore fires 'pageshow' without re-running 'load', so the
    // skeleton needs its own reset here too or a bfcache page could come back stuck
    // mid-fade from whatever state it was in when the user navigated away.
    hideSkeleton();
});

if (window.axios) {
    // Callers with their own inline progress feedback (e.g. the discussion chat's
    // per-message spinner) opt out with `{ skipProgress: true }` in the request config,
    // the axios equivalent of a form's data-no-progress — the global bar would otherwise
    // fire for every request regardless of whether the caller already shows its own state.
    window.axios.interceptors.request.use((config) => {
        if (! config.skipProgress) {
            startProgress();
        }

        return config;
    }, (error) => {
        if (! error.config?.skipProgress) {
            stopProgress();
        }

        return Promise.reject(error);
    });

    window.axios.interceptors.response.use((response) => {
        if (! response.config.skipProgress) {
            stopProgress();
        }

        return response;
    }, (error) => {
        if (! error.config?.skipProgress) {
            stopProgress();
        }

        return Promise.reject(error);
    });
}

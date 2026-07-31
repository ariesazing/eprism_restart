import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

// --- Global top progress bar + action spinners ---

const progressBar = document.createElement('div');
progressBar.id = 'page-progress-bar';
document.body.appendChild(progressBar);

let activeRequests = 0;

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
});

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (form.hasAttribute('data-no-progress')) {
        return;
    }

    startProgress();

    if (event.submitter) {
        disableWithSpinner(event.submitter);
    }
});

window.addEventListener('pageshow', () => {
    activeRequests = 0;
    progressBar.classList.remove('is-active');
    progressBar.style.width = '0%';
});

if (window.axios) {
    window.axios.interceptors.request.use((config) => {
        startProgress();

        return config;
    }, (error) => {
        stopProgress();

        return Promise.reject(error);
    });

    window.axios.interceptors.response.use((response) => {
        stopProgress();

        return response;
    }, (error) => {
        stopProgress();

        return Promise.reject(error);
    });
}

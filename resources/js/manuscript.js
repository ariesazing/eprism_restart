const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

async function status(url) {
    const response = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
    if (!response.ok) throw new Error('Could not check the saved manuscript. Please try again.');
    return response.json();
}

async function loadOffice(base) {
    if (window.DocsAPI) return;
    await new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = base.replace(/\/$/, '') + '/web-apps/apps/api/documents/api.js';
        script.onload = resolve;
        script.onerror = () => reject(new Error('The manuscript editor is unavailable. Please try again later.'));
        document.head.append(script);
    });
}

const MIN_MOUNT_HEIGHT_PX = 600;
const BOTTOM_BUFFER_PX = 32;

// DocsAPI.DocEditor sets its own inline height/width on the target element from the config
// it's given — a percentage value like '80vh' needs every ancestor up the tree to already
// have a definite height to resolve against, which none of them do here (they all size to
// content), so it silently collapses to 0 instead of actually filling the page. Same fix as
// document-editor/onlyoffice.js's own computeMountHeight(): hand DocsAPI an already-resolved
// pixel number instead.
function computeMountHeight(mount) {
    const top = mount.getBoundingClientRect().top;

    return Math.max(MIN_MOUNT_HEIGHT_PX, window.innerHeight - top - BOTTOM_BUFFER_PX);
}

async function mount(root) {
    const message = root.querySelector('[data-manuscript-message]');
    const mountEl = document.getElementById('complete-manuscript-editor');
    let editor;
    let dirty = false;
    let closing = false;
    let destroyed = false;
    let stopped = false;
    const close = async () => {
        if (closing) return;
        closing = true;
        message.textContent = 'Saving your latest changes…';
        try {
            // False means the editor has sent its changes to Document Server. Laravel
            // must still wait for the separate final-save callback after the editor closes.
            for (let i = 0; dirty && i < 120; i++) await delay(500);
            if (dirty) throw new Error('Changes have not reached the server. Keep this tab open and check your connection.');
            editor.destroyEditor();
            destroyed = true;
            for (let i = 0; i < 120; i++) {
                const current = await status(root.dataset.statusUrl);
                if (!current.session_open) {
                    stopped = true;
                    window.location.assign(root.dataset.returnUrl);
                    return;
                }
                await delay(1000);
            }
            throw new Error('The final save is taking longer than expected. Your submission will wait for confirmation. Return to the submission page to check its status.');
        } catch (error) {
            message.textContent = error.message;
            closing = false;
        }
    };
    document.querySelector('[data-manuscript-close]')?.addEventListener('click', (event) => {
        if (!editor) return;
        event.preventDefault();
        if (destroyed) window.location.assign(root.dataset.returnUrl);
        else close();
    });
    window.addEventListener('beforeunload', (event) => {
        if (dirty && !destroyed) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
    try {
        await loadOffice(root.dataset.officeUrl);
        const response = await fetch(root.dataset.configUrl, { headers: { Accept: 'application/json' } });
        const config = await response.json();
        if (!response.ok) throw new Error(config.message || 'The manuscript could not be opened.');
        const heightPx = computeMountHeight(mountEl);
        mountEl.style.height = `${heightPx}px`;
        window.addEventListener('resize', () => {
            mountEl.style.height = `${computeMountHeight(mountEl)}px`;
        });
        editor = new window.DocsAPI.DocEditor('complete-manuscript-editor', {
            ...config, height: `${heightPx}px`, width: '100%',
            events: {
                onDocumentReady: () => { message.textContent = 'Changes save automatically. Use Save and return when you finish editing.'; },
                onDocumentStateChange: (event) => {
                    dirty = Boolean(event.data);
                    message.textContent = dirty ? 'Sending changes to the editor service…' : 'Changes received by the editor service. Close the editor to finish saving.';
                },
                onError: () => { message.textContent = 'The editor reported an error. Keep this tab open until your changes are saved.'; },
            },
        });
        while (!stopped) {
            await delay(3000);
            try {
                const current = await status(root.dataset.statusUrl);
                if (current.state === 'waiting_for_save' && !closing && !destroyed) await close();
            } catch (error) {
                if (!closing) message.textContent = error.message;
            }
        }
    } catch (error) {
        message.textContent = error.message;
    }
}

document.querySelectorAll('[data-manuscript-editor]').forEach(mount);
document.querySelectorAll('[data-manuscript-summary][data-processing="1"]').forEach(async (root) => {
    const message = root.querySelector('[data-manuscript-message]');
    for (;;) {
        await delay(3000);
        try {
            const current = await status(root.dataset.statusUrl);
            if (!['waiting_for_save', 'processing'].includes(current.state)) {
                window.location.reload();
                return;
            }
            message.textContent = current.state === 'waiting_for_save'
                ? 'Waiting for the final save. Use “Save and return” in every open manuscript editor tab.'
                : 'Checking the saved document and preparing its review PDF. You can return later.';
        } catch (error) {
            message.textContent = error.message;
        }
    }
});

// Reloads once the queued preview conversion (see ManuscriptPreviewService/
// ComposeManuscriptPreview) finishes — the reload re-enters reviewManuscript() server-side,
// which now serves the finished PDF instead of rendering this pending page again.
document.querySelectorAll('[data-manuscript-preview-pending]').forEach(async (root) => {
    const message = root.querySelector('[data-manuscript-message]');
    for (;;) {
        await delay(3000);
        try {
            const current = await status(root.dataset.statusUrl);
            if (current.preview?.state === 'ready') {
                window.location.reload();
                return;
            }
            if (current.preview?.state === 'failed') {
                message.textContent = current.preview.error || 'The preview could not be generated.';
                return;
            }
        } catch (error) {
            message.textContent = error.message;
        }
    }
});

// Shared ONLYOFFICE DocsAPI mounting helpers — used wherever a page embeds a real
// Document Server editor (a per-chapter researcher panel, the complete-manuscript editor).
// Deliberately tiny and framework-free: each caller owns its own lifecycle (destroy,
// dirty-tracking, status messaging), this only knows how to load the DocsAPI script once and
// size/mount one editor instance into a target element.

const DEFAULT_MIN_MOUNT_HEIGHT_PX = 400;
const BOTTOM_BUFFER_PX = 32;

let officeScriptPromise = null;

export function loadOffice(officeUrl) {
    if (window.DocsAPI) {
        return Promise.resolve();
    }

    if (officeScriptPromise) {
        return officeScriptPromise;
    }

    officeScriptPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = officeUrl.replace(/\/$/, '') + '/web-apps/apps/api/documents/api.js';
        script.onload = resolve;
        script.onerror = () => {
            officeScriptPromise = null;
            reject(new Error('The document editor is unavailable. Please try again later.'));
        };
        document.head.append(script);
    });

    return officeScriptPromise;
}

// DocsAPI.DocEditor sets its own inline height/width on the target element from the config
// it's given — a percentage value like '80vh' needs every ancestor up the tree to already
// have a definite height to resolve against, which none of them do here (they all size to
// content), so it silently collapses to 0 instead of actually filling the page. Handing
// DocsAPI an already-resolved pixel number instead avoids that entirely.
export function computeMountHeight(mount, minHeight = DEFAULT_MIN_MOUNT_HEIGHT_PX) {
    const top = mount.getBoundingClientRect().top;

    return Math.max(minHeight, window.innerHeight - top - BOTTOM_BUFFER_PX);
}

/**
 * Loads DocsAPI (once per page), fetches this editor's config from `configUrl`, and mounts a
 * DocEditor instance into the element with id `mountId`. Returns the DocEditor instance so the
 * caller can wire its own `destroyEditor()`/dirty-tracking on top.
 */
export async function mountOnlyOfficeEditor({ officeUrl, configUrl, mountId, minHeight, events }) {
    await loadOffice(officeUrl);

    const response = await fetch(configUrl, { headers: { Accept: 'application/json' } });
    const config = await response.json();

    if (!response.ok) {
        throw new Error(config.message || 'The document could not be opened.');
    }

    const mountEl = document.getElementById(mountId);
    const heightPx = computeMountHeight(mountEl, minHeight);
    mountEl.style.height = `${heightPx}px`;

    const host = mountEl.parentElement;
    const editor = new window.DocsAPI.DocEditor(mountId, { ...config, height: `${heightPx}px`, width: '100%', events });
    // DocsAPI replaces the mount with an iframe. Resize that frame without
    // recreating the editor or interrupting an active document session.
    const resize = () => {
        const frame = host.querySelector('iframe');
        if (!frame) return;
        const height = computeMountHeight(frame, minHeight);
        frame.style.height = `${height}px`;
        frame.style.width = '100%';
    };
    const observer = new ResizeObserver(resize);
    observer.observe(host);
    window.addEventListener('resize', resize);
    const destroy = editor.destroyEditor.bind(editor);
    editor.destroyEditor = () => {
        observer.disconnect();
        window.removeEventListener('resize', resize);
        destroy();
    };
    resize();
    return editor;
}

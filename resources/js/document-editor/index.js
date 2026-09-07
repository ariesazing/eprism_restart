import Editor, { PageMode } from '@hufe921/canvas-editor';
import { buildToolbar } from './toolbar';

// A clipboard paste can be a full-resolution photo (phone screenshots routinely run
// several thousand pixels per side / several MB once re-encoded as base64) — canvas-editor
// inserts it into the document at that full size and weight, uncompressed, with no cap.
// That base64 string then goes straight through SubmissionSectionService::sanitizeRichText()
// into the chapter's stored HTML, and back out again into SubmissionPdfComposer/dompdf when
// the submission is composed into a PDF (on every save-and-view, and again on submit) —
// dompdf has to decode and lay out that image in memory on every single render, and a
// large-enough one exhausts PHP's memory_limit, which is a fatal error, not a caught
// exception (see submission-editor.js's submit handler, which can't do anything about a
// crash that already happened server-side). Downscaling and re-compressing at the moment
// of paste, before it ever reaches canvas-editor's own element list, keeps the document
// (and everything downstream of it) working with an image sized for how it'll actually be
// viewed, not however large the source screenshot happened to be.
const PASTED_IMAGE_MAX_DIMENSION = 1600;
const PASTED_IMAGE_QUALITY = 0.82;

/**
 * Registers canvas-editor's `override.pasteImage` hook (see node_modules/@hufe921/
 * canvas-editor's Draw.getOverride() usage in its paste handler) so a clipboard image paste
 * routes through here instead of canvas-editor's own default (which inserts the original
 * file, verbatim, as base64 — see PASTED_IMAGE_MAX_DIMENSION's comment for why that's a
 * problem). Returning anything from this function other than `{ preventDefault: false }`
 * tells canvas-editor to skip its own default handling entirely, so the resize below fully
 * replaces it rather than running alongside it.
 *
 * @param {'image/webp'|'image/jpeg'} format  research submission chapters use WebP (product
 *   decision: smaller than JPEG at the same visual quality, and this app has no reason to
 *   keep a heavier format around for a research-document illustration); the admin template
 *   editor keeps JPEG, matching its existing output.
 */
function capPastedImageSize(editor, format = 'image/jpeg') {
    editor.override.pasteImage = (file) => {
        const reader = new FileReader();

        reader.onload = () => {
            const image = new Image();

            image.onload = () => {
                const scale = Math.min(1, PASTED_IMAGE_MAX_DIMENSION / Math.max(image.width, image.height));
                const width = Math.max(1, Math.round(image.width * scale));
                const height = Math.max(1, Math.round(image.height * scale));

                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                canvas.getContext('2d').drawImage(image, 0, 0, width, height);

                // PNG/GIF sources may carry real transparency worth keeping; anything else
                // (typically a photo or a screenshot) re-encodes far smaller at `format`,
                // which matters more here than pixel-perfect fidelity — this is a research
                // document illustration, not the original asset. A browser without WebP
                // encoding support (none left in real-world use, but toDataURL degrades
                // gracefully per spec) just falls back to PNG.
                const keepsAlpha = file.type === 'image/png' || file.type === 'image/gif';
                const dataUri = canvas.toDataURL(keepsAlpha ? 'image/png' : format, keepsAlpha ? undefined : PASTED_IMAGE_QUALITY);

                editor.command.executeImage({ value: dataUri, width, height });
                editor.command.executeFocus();
            };

            image.src = reader.result;
        };

        reader.readAsDataURL(file);

        return { preventDefault: true };
    };
}

/**
 * Creates the toolbar-mode canvas-editor instance (admin template editor) and its
 * Google-Docs-style toolbar. Editor-vs-application responsibilities stay separate: this
 * module only ever formats/edits the document and reports state — it never decides how
 * or where the result gets saved. The caller (submission-editor.js) owns persistence via
 * the surrounding <form>; this module just exposes what that form-sync needs to read.
 *
 * @param {HTMLElement} wrapper  the [data-canvas-editor="toolbar"] container
 * @param {{ main: [], header: [], footer: [] }} seedData
 * @param {object|null} savedPageOptions  previously-saved page_options, or null
 * @param {{ imageUploadUrl?: string, onSave?: () => void }} options
 */
export function initToolbarEditor(wrapper, seedData, savedPageOptions, { imageUploadUrl, onSave } = {}) {
    const mount = wrapper.querySelector('[data-canvas-mount]');
    const toolbarEl = wrapper.querySelector('[data-canvas-toolbar]');

    const editor = new Editor(mount, {
        header: seedData.header || [],
        main: seedData.main || [],
        footer: seedData.footer || [],
    }, {
        pageMode: PageMode.PAGING,
        // canvas-editor defaults to its built-in Chinese strings (locale "zhCN") — it also
        // ships an "en" lang map out of the box, which covers the right-click context menu
        // (Copy/Paste/Image wrap/etc.) that isn't part of our own custom toolbar.
        locale: 'en',
        ...(savedPageOptions || {}),
    });

    capPastedImageSize(editor);

    let toolbar = null;

    // canvas-editor renders the page at its configured pixel size regardless of how wide
    // its mount actually is — there's no built-in fit-to-container, so without this the
    // page can be wider than the space available (e.g. next to the placeholders sidebar)
    // and force horizontal scrolling. Re-fit on mount resize (sidebar/breakpoint changes)
    // and whenever Page Setup changes the page's own width.
    function fitPageToContainer() {
        const width = mount.clientWidth;
        if (toolbar && width > 0) {
            toolbar.fitToWidth(width);
        }
    }

    if (toolbarEl) {
        toolbar = buildToolbar(editor, toolbarEl, { imageUploadUrl, onPageOptionsApplied: fitPageToContainer, savedPageOptions });
        fitPageToContainer();

        if (typeof ResizeObserver !== 'undefined') {
            new ResizeObserver(fitPageToContainer).observe(mount);
        }
    }

    // canvas-editor already binds Ctrl/Cmd+S internally and fires this hook — we don't
    // rebind the shortcut ourselves, we just decide what "save" means at the app level.
    if (onSave) {
        editor.listener.saved = () => onSave();
    }

    return {
        editor,
        // Delegates to the toolbar's own tracked Page Setup state rather than reading
        // command.getOptions()/getPaperMargin() directly — verified live that those
        // getters don't reflect executePaperSize/executePaperDirection/
        // executeSetPaperMargin, only their initial construction-time values.
        getPageOptions: () => toolbar?.getPageOptions() ?? null,
    };
}

/**
 * A toolbar-enabled editor for a research submission's chapter content — the same
 * formatting toolset as the admin template editor, minus the controls that only apply to
 * a full standalone document (page size/margins, header/footer zone switching; see
 * buildToolbar's includeTemplateTools). Unlike initToolbarEditor this keeps header/footer
 * disabled and doesn't force PageMode.PAGING, matching the plain chapter editor's layout
 * (a scrollable content box, not a paginated page) since a chapter is a fragment that gets
 * composed into the final manuscript later, not a document in its own right.
 */
export function initInlineToolbarEditor(wrapper, seedData, { imageUploadUrl } = {}) {
    const mount = wrapper.querySelector('[data-canvas-mount]');
    const toolbarEl = wrapper.querySelector('[data-canvas-toolbar]');

    const editor = new Editor(mount, { main: seedData.main || [] }, {
        header: { disabled: true },
        footer: { disabled: true },
        locale: 'en',
    });

    capPastedImageSize(editor, 'image/webp');

    if (toolbarEl) {
        buildToolbar(editor, toolbarEl, { imageUploadUrl, includeTemplateTools: false });
    }

    return { editor };
}

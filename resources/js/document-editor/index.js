import Editor, { PageMode } from '@hufe921/canvas-editor';
import { buildToolbar } from './toolbar';

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
        toolbar = buildToolbar(editor, toolbarEl, { imageUploadUrl, onPageOptionsApplied: fitPageToContainer });
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

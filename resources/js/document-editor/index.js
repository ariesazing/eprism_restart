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
        ...(savedPageOptions || {}),
    });

    let toolbar = null;
    if (toolbarEl) {
        toolbar = buildToolbar(editor, toolbarEl, { imageUploadUrl });
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

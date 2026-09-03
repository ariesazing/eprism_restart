import Editor from '@hufe921/canvas-editor';
import { initToolbarEditor, initInlineToolbarEditor } from './document-editor/index';

// Every mounted canvas-editor instance, so a form's submit handler can pull
// current content out of each one right before the browser submits the form
// (canvas-editor has no server callback — content only leaves the browser
// when the surrounding form is actually submitted).
const canvasEditors = [];

function parseSeedData(wrapper) {
    const script = wrapper.nextElementSibling;

    if (!script || script.dataset.canvasEditorData === undefined) {
        return {};
    }

    try {
        return JSON.parse(script.textContent) || {};
    } catch (error) {
        console.error('Invalid canvas-editor seed data', error);
        return {};
    }
}

function inputFor(wrapper, attr) {
    const id = wrapper.dataset[attr];
    return id ? document.getElementById(id) : null;
}

// Autosave target for the current submission form — set once from the form's own dataset
// (see wireAutosave), left null on a read-only/locked view where no autosave URL is rendered.
let autosaveUrl = null;
let autosaveStatusEl = null;

function debounce(fn, waitMs) {
    let timeout;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => fn(...args), waitMs);
    };
}

function setAutosaveStatus(state) {
    if (! autosaveStatusEl) {
        return;
    }

    autosaveStatusEl.textContent = {
        saving: 'Saving…',
        saved: 'All changes saved',
        error: 'Autosave failed — check your connection',
    }[state] ?? '';
}

async function autosaveSection(sectionKey, value) {
    if (! autosaveUrl) {
        return;
    }

    setAutosaveStatus('saving');

    try {
        await window.axios.patch(autosaveUrl, { section: sectionKey, value });
        setAutosaveStatus('saved');
    } catch (error) {
        console.error('Autosave failed', error);
        setAutosaveStatus('error');
    }
}

function wireSectionAutosave(editor, sectionKey) {
    if (! sectionKey || ! autosaveUrl) {
        return;
    }

    editor.listener.contentChange = debounce(async () => {
        const { data } = editor.command.getValue();
        const html = await editor.command.getHTML();

        autosaveSection(sectionKey, { content: JSON.stringify(data), html: html.main });
    }, 2000);
}

function initPlainCanvasEditor(wrapper) {
    if (wrapper.dataset.canvasEditorInitialized) {
        return;
    }
    wrapper.dataset.canvasEditorInitialized = '1';

    const mount = wrapper.querySelector('[data-canvas-mount]');
    const seed = parseSeedData(wrapper);

    const editor = new Editor(mount, { main: seed.main || [] }, {
        header: { disabled: true },
        footer: { disabled: true },
        // canvas-editor defaults its right-click context menu to Chinese (locale "zhCN");
        // "en" is its other built-in lang map.
        locale: 'en',
    });

    wireSectionAutosave(editor, wrapper.dataset.sectionKey);

    canvasEditors.push({
        editor,
        contentInput: inputFor(wrapper, 'contentInput'),
        htmlInput: inputFor(wrapper, 'htmlInput'),
    });
}

// Same as initPlainCanvasEditor but with the formatting toolbar a researcher needs to
// actually write a chapter (bold/italic/lists/tables/images/links…) — the plain editor
// had none at all. Scoped down from the admin template editor: no page setup or
// header/footer controls, since a chapter is a content fragment, not a standalone document
// (see buildToolbar's includeTemplateTools / initInlineToolbarEditor).
function initInlineToolbarCanvasEditor(wrapper) {
    if (wrapper.dataset.canvasEditorInitialized) {
        return;
    }
    wrapper.dataset.canvasEditorInitialized = '1';

    const seed = parseSeedData(wrapper);
    const { editor } = initInlineToolbarEditor(wrapper, seed, {
        imageUploadUrl: wrapper.dataset.imageUploadUrl,
    });

    wireSectionAutosave(editor, wrapper.dataset.sectionKey);

    canvasEditors.push({
        editor,
        contentInput: inputFor(wrapper, 'contentInput'),
        htmlInput: inputFor(wrapper, 'htmlInput'),
    });
}

function initSectionCanvasEditor(wrapper) {
    if (wrapper.dataset.canvasEditor === 'toolbar-inline') {
        initInlineToolbarCanvasEditor(wrapper);
    } else {
        initPlainCanvasEditor(wrapper);
    }
}

function initToolbarCanvasEditor(wrapper) {
    if (wrapper.dataset.canvasEditorInitialized) {
        return;
    }
    wrapper.dataset.canvasEditorInitialized = '1';

    const seed = parseSeedData(wrapper);
    const form = wrapper.closest('form');

    const { editor, getPageOptions } = initToolbarEditor(wrapper, seed, seed.pageOptions || null, {
        imageUploadUrl: wrapper.dataset.imageUploadUrl,
        // canvas-editor's own Ctrl/Cmd+S hook — save here means "submit the same form the
        // visible Save button submits", not a separate persistence path of its own.
        onSave: () => form?.requestSubmit ? form.requestSubmit() : form?.submit(),
    });

    canvasEditors.push({
        editor,
        getPageOptions,
        contentInput: inputFor(wrapper, 'contentInput'),
        pageOptionsInput: inputFor(wrapper, 'pageOptionsInput'),
        bodyInput: inputFor(wrapper, 'bodyInput'),
        headerInput: inputFor(wrapper, 'headerInput'),
        footerInput: inputFor(wrapper, 'footerInput'),
    });
}

// canvas-editor's getHTML() exports every Tab character as a fixed, hardcoded
// <span>&nbsp;&nbsp;</span> (two non-breaking spaces), completely discarding the
// editor's own tab width (defaultTabWidth: 32px) — so the generated document only ever
// shows a couple of character-spaces instead of a real tab stop. A bare, attribute-less
// <span> like this is otherwise never emitted by the editor (every real text run always
// carries a style attribute), so this exact shape reliably identifies a Tab marker
// rather than incidental double-non-breaking-space text.
const TAB_MARKER_PATTERN = /<span>(?:&nbsp;| ){2}<\/span>/g;

function fixTabSpacing(html) {
    return html.replace(TAB_MARKER_PATTERN, '<span style="display:inline-block;width:32px;">&nbsp;</span>');
}

async function syncCanvasEditor(entry) {
    const { editor } = entry;
    const { data } = editor.command.getValue();
    const html = await editor.command.getHTML();

    if (entry.contentInput) {
        entry.contentInput.value = JSON.stringify(data);
    }
    if (entry.pageOptionsInput && entry.getPageOptions) {
        entry.pageOptionsInput.value = JSON.stringify(entry.getPageOptions());
    }
    if (entry.htmlInput) {
        entry.htmlInput.value = fixTabSpacing(html.main);
    }
    if (entry.bodyInput) {
        entry.bodyInput.value = fixTabSpacing(html.main);
    }
    if (entry.headerInput) {
        entry.headerInput.value = fixTabSpacing(html.header);
    }
    if (entry.footerInput) {
        entry.footerInput.value = fixTabSpacing(html.footer);
    }
}

function attachFormSync(form) {
    if (form.dataset.canvasEditorSyncAttached) {
        return;
    }
    form.dataset.canvasEditorSyncAttached = '1';

    form.addEventListener('submit', (event) => {
        event.preventDefault();

        // form.submit() ignores whichever button triggered the submit, so a
        // formaction/formtarget override (e.g. the "Preview" button opening
        // in a new tab) has to be applied manually here. Read the raw HTML
        // attributes, not the .formAction/.formTarget IDL properties — those
        // default to the current page URL and "" respectively when the
        // attribute isn't set, so checking them directly would wrongly
        // redirect a plain "Save" button's submit too.
        const submitter = event.submitter;
        const formAction = submitter?.getAttribute('formaction');
        const formTarget = submitter?.getAttribute('formtarget');
        const originalAction = form.action;
        const originalTarget = form.target;

        Promise.all(canvasEditors.map(syncCanvasEditor)).then(() => {
            if (formAction) {
                form.action = formAction;
            }
            if (formTarget) {
                form.target = formTarget;
            }

            form.submit();

            form.action = originalAction;
            form.target = originalTarget;
        });
    });
}

function collectTableRows(rowsContainer) {
    return Array.from(rowsContainer.querySelectorAll('[data-table-row]')).map((row) => {
        const values = {};

        row.querySelectorAll('input[name]').forEach((input) => {
            const match = input.name.match(/^sections\[[^\]]+\]\[[^\]]+\]\[([^\]]+)\]$/);

            if (match) {
                values[match[1]] = input.value;
            }
        });

        return values;
    });
}

function initTableSections(root) {
    root.querySelectorAll('[data-table-section]').forEach((section) => {
        const rowsContainer = section.querySelector('[data-table-rows]');
        const template = section.querySelector('[data-row-template]');
        const addButton = section.querySelector('[data-add-row]');
        const sectionKey = section.dataset.sectionKey;
        let nextIndex = parseInt(rowsContainer.dataset.nextIndex || '0', 10);

        const triggerAutosave = sectionKey && autosaveUrl
            ? debounce(() => autosaveSection(sectionKey, collectTableRows(rowsContainer)), 2000)
            : () => {};

        function renumber() {
            rowsContainer.querySelectorAll('[data-table-row]').forEach((row, i) => {
                const label = row.querySelector('[data-row-number]');
                if (label) {
                    label.textContent = String(i + 1);
                }
            });
        }

        if (addButton && template) {
            addButton.addEventListener('click', () => {
                const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex));
                const wrapper = document.createElement('tbody');
                wrapper.innerHTML = html.trim();
                rowsContainer.appendChild(wrapper.firstElementChild);
                nextIndex += 1;
                renumber();
                triggerAutosave();
            });
        }

        rowsContainer.addEventListener('click', (event) => {
            if (event.target.matches('[data-remove-row]')) {
                event.target.closest('[data-table-row]').remove();
                renumber();
                triggerAutosave();
            }
        });

        rowsContainer.addEventListener('input', triggerAutosave);
    });
}

// Chapter panels all render at once in the DOM and are toggled with
// `.hidden`; each canvas-editor instance has real setup cost, so render()
// below only initializes the currently visible panel's editor, lazily
// initializing the rest as their tab is opened.
function initChapterWizard(root) {
    const chapters = root.querySelector('[data-chapters]');
    const controls = root.querySelector('[data-wizard-controls]');

    if (! chapters || ! controls) {
        return;
    }

    const panels = Array.from(chapters.querySelectorAll('[data-chapter-panel]'));
    const chapterButtons = Array.from(controls.querySelectorAll('[data-wizard-chapter]'));

    if (! panels.length || ! chapterButtons.length) {
        return;
    }

    let currentIndex = 0;

    function render() {
        panels.forEach((panel, index) => panel.classList.toggle('hidden', index !== currentIndex));
        chapterButtons.forEach((button, index) => {
            const active = index === currentIndex;
            button.classList.toggle('bg-cherry-700', active);
            button.classList.toggle('border-cherry-700', active);
            button.classList.toggle('text-white', active);
            button.classList.toggle('border-slate-300', ! active);
            button.classList.toggle('text-slate-700', ! active);
        });

        panels[currentIndex].querySelectorAll('[data-canvas-editor]').forEach(initSectionCanvasEditor);
    }

    chapterButtons.forEach((button, index) => {
        button.addEventListener('click', () => {
            currentIndex = index;
            render();
            panels[currentIndex].scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    render();
}

// Triggered from the always-visible readiness summary banner and the submit-blocked
// modal (researcher/submissions/show.blade.php) — both live outside the section editor's
// own form, so this is scoped to the document rather than that form. Reuses the wizard's
// own tab button rather than duplicating its panel-switch/scroll/lazy-init logic.
document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-jump-to-section]');

    if (! trigger) {
        return;
    }

    const key = trigger.dataset.jumpToSection;
    const tab = document.querySelector(`[data-wizard-chapter][data-section-key="${key}"]`);

    tab?.click();
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-canvas-editor="toolbar"]').forEach(initToolbarCanvasEditor);

    const form = document.querySelector('[data-section-editor-form]');

    if (form) {
        autosaveUrl = form.dataset.autosaveUrl || null;
        autosaveStatusEl = form.querySelector('[data-autosave-status]');

        initTableSections(form);
        initChapterWizard(form);
    }

    document.querySelectorAll('form').forEach((candidate) => {
        if (candidate.querySelector('[data-canvas-editor]')) {
            attachFormSync(candidate);
        }
    });
});

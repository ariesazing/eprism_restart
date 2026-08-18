import Editor from '@hufe921/canvas-editor';
import { initToolbarEditor } from './document-editor/index';

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

    canvasEditors.push({
        editor,
        contentInput: inputFor(wrapper, 'contentInput'),
        htmlInput: inputFor(wrapper, 'htmlInput'),
    });
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
        entry.htmlInput.value = html.main;
    }
    if (entry.bodyInput) {
        entry.bodyInput.value = html.main;
    }
    if (entry.headerInput) {
        entry.headerInput.value = html.header;
    }
    if (entry.footerInput) {
        entry.footerInput.value = html.footer;
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

function initTableSections(root) {
    root.querySelectorAll('[data-table-section]').forEach((section) => {
        const rowsContainer = section.querySelector('[data-table-rows]');
        const template = section.querySelector('[data-row-template]');
        const addButton = section.querySelector('[data-add-row]');
        let nextIndex = parseInt(rowsContainer.dataset.nextIndex || '0', 10);

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
            });
        }

        rowsContainer.addEventListener('click', (event) => {
            if (event.target.matches('[data-remove-row]')) {
                event.target.closest('[data-table-row]').remove();
                renumber();
            }
        });
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
            button.classList.toggle('bg-red-700', active);
            button.classList.toggle('border-red-700', active);
            button.classList.toggle('text-white', active);
            button.classList.toggle('border-slate-300', ! active);
            button.classList.toggle('text-slate-700', ! active);
        });

        panels[currentIndex].querySelectorAll('[data-canvas-editor]').forEach(initPlainCanvasEditor);
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

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-canvas-editor="toolbar"]').forEach(initToolbarCanvasEditor);

    const form = document.querySelector('[data-section-editor-form]');

    if (form) {
        initTableSections(form);
        initChapterWizard(form);
    }

    document.querySelectorAll('form').forEach((candidate) => {
        if (candidate.querySelector('[data-canvas-editor]')) {
            attachFormSync(candidate);
        }
    });
});

import Editor, { RowFlex, ListType, PageMode } from '@hufe921/canvas-editor';

const WORD_FONTS = [
    'Calibri', 'Cambria', 'Times New Roman', 'Arial', 'Georgia', 'Verdana',
    'Courier New', 'Comic Sans MS', 'Impact', 'Trebuchet MS', 'Consolas',
    'Tahoma', 'Segoe UI', 'Garamond', 'Book Antiqua', 'Trajan Pro', 'Old English Text MT',
];

const FONT_SIZES = [8, 9, 10, 10.5, 11, 12, 14, 16, 18, 20, 24, 28, 32, 36, 48, 72];

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

    const mount = wrapper.querySelector('[data-canvas-mount]');
    const toolbar = wrapper.querySelector('[data-canvas-toolbar]');
    const seed = parseSeedData(wrapper);

    const editor = new Editor(mount, {
        header: seed.header || [],
        main: seed.main || [],
        footer: seed.footer || [],
    }, {
        // Render each page in full and let the mount container's own
        // scrollbar move between them (per-page viewing), rather than
        // squeezing the whole document into one continuous canvas.
        pageMode: PageMode.PAGING,
    });

    if (toolbar) {
        buildToolbar(editor, toolbar);
    }

    canvasEditors.push({
        editor,
        contentInput: inputFor(wrapper, 'contentInput'),
        bodyInput: inputFor(wrapper, 'bodyInput'),
        headerInput: inputFor(wrapper, 'headerInput'),
        footerInput: inputFor(wrapper, 'footerInput'),
    });
}

function toolbarButton(label, title, onClick) {
    const button = document.createElement('button');
    button.type = 'button';
    button.title = title;
    button.textContent = label;
    button.className = 'rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100';
    button.addEventListener('click', onClick);
    return button;
}

function toolbarSelect(options, title, onChange) {
    const select = document.createElement('select');
    select.title = title;
    select.className = 'rounded-md border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-700';

    options.forEach((option) => {
        const el = document.createElement('option');
        el.value = String(option.value);
        el.textContent = option.label;
        select.appendChild(el);
    });

    select.addEventListener('change', () => onChange(select.value));
    return select;
}

function buildToolbar(editor, toolbar) {
    toolbar.className = 'mb-3 flex flex-wrap items-center gap-1.5 rounded-xl bg-slate-50 p-2 ring-1 ring-slate-200';
    const { command } = editor;

    toolbar.appendChild(toolbarSelect(
        WORD_FONTS.map((font) => ({ value: font, label: font })),
        'Font family',
        (value) => command.executeFont(value),
    ));

    toolbar.appendChild(toolbarSelect(
        FONT_SIZES.map((size) => ({ value: size, label: String(size) })),
        'Font size',
        (value) => command.executeSize(Number(value)),
    ));

    toolbar.appendChild(toolbarButton('B', 'Bold', () => command.executeBold()));
    toolbar.appendChild(toolbarButton('I', 'Italic', () => command.executeItalic()));
    toolbar.appendChild(toolbarButton('U', 'Underline', () => command.executeUnderline()));
    toolbar.appendChild(toolbarButton('S', 'Strikethrough', () => command.executeStrikeout()));

    const color = document.createElement('input');
    color.type = 'color';
    color.title = 'Text color';
    color.className = 'h-8 w-8 rounded-md border border-slate-300 bg-white p-1';
    color.addEventListener('input', () => command.executeColor(color.value));
    toolbar.appendChild(color);

    const highlight = document.createElement('input');
    highlight.type = 'color';
    highlight.title = 'Highlight';
    highlight.className = 'h-8 w-8 rounded-md border border-slate-300 bg-white p-1';
    highlight.addEventListener('input', () => command.executeHighlight(highlight.value));
    toolbar.appendChild(highlight);

    toolbar.appendChild(toolbarButton('⟵', 'Align left', () => command.executeRowFlex(RowFlex.LEFT)));
    toolbar.appendChild(toolbarButton('↔', 'Align center', () => command.executeRowFlex(RowFlex.CENTER)));
    toolbar.appendChild(toolbarButton('⟶', 'Align right', () => command.executeRowFlex(RowFlex.RIGHT)));
    toolbar.appendChild(toolbarButton('☰', 'Justify', () => command.executeRowFlex(RowFlex.JUSTIFY)));

    toolbar.appendChild(toolbarButton('•—', 'Bulleted list', () => command.executeList(ListType.UL)));
    toolbar.appendChild(toolbarButton('1.', 'Numbered list', () => command.executeList(ListType.OL)));

    toolbar.appendChild(toolbarButton('⊞', 'Insert table', () => {
        const rows = parseInt(window.prompt('Rows?', '3') || '0', 10);
        const cols = parseInt(window.prompt('Columns?', '3') || '0', 10);
        if (rows > 0 && cols > 0) {
            command.executeInsertTable(rows, cols);
        }
    }));

    toolbar.appendChild(toolbarButton('🔗', 'Insert hyperlink', () => {
        const text = window.prompt('Link text?');
        const url = text ? window.prompt('URL?') : null;
        if (text && url) {
            command.executeHyperlink({ valueList: [{ value: text }], url });
        }
    }));

    const imageInput = document.createElement('input');
    imageInput.type = 'file';
    imageInput.accept = 'image/*';
    imageInput.className = 'hidden';
    imageInput.addEventListener('change', () => {
        const file = imageInput.files?.[0];
        if (!file) {
            return;
        }
        const reader = new FileReader();
        reader.onload = () => {
            const image = new Image();
            image.onload = () => {
                command.executeImage({ value: reader.result, width: image.width, height: image.height });
            };
            image.src = reader.result;
        };
        reader.readAsDataURL(file);
        imageInput.value = '';
    });
    toolbar.appendChild(imageInput);
    toolbar.appendChild(toolbarButton('🖼', 'Insert image', () => imageInput.click()));

    toolbar.appendChild(toolbarButton('↺', 'Undo', () => command.executeUndo()));
    toolbar.appendChild(toolbarButton('↻', 'Redo', () => command.executeRedo()));
}

async function syncCanvasEditor(entry) {
    const { editor } = entry;
    const { data } = editor.command.getValue();
    const html = await editor.command.getHTML();

    if (entry.contentInput) {
        entry.contentInput.value = JSON.stringify(data);
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

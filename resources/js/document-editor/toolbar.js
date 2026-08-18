import { RowFlex, ListType, ListStyle, TitleLevel, EditorZone, PaperDirection } from '@hufe921/canvas-editor';
import { iconMarkup, letterGlyph } from './icons';
import {
    createPopover, menuItem, menuPanel, colorPickerPanel, tableGridPickerPanel, openModal, labeledField, numberInput,
} from './components';
import { createToolbarState } from './state';
import { registerAdditionalShortcuts, dispatchListIndent } from './shortcuts';
import { CAPABILITIES } from './capabilities';

const WORD_FONTS = [
    'Calibri', 'Cambria', 'Times New Roman', 'Arial', 'Georgia', 'Verdana',
    'Courier New', 'Comic Sans MS', 'Impact', 'Trebuchet MS', 'Consolas',
    'Tahoma', 'Segoe UI', 'Garamond', 'Book Antiqua', 'Trajan Pro', 'Old English Text MT',
];

const FONT_SIZES = [8, 9, 10, 10.5, 11, 12, 14, 16, 18, 20, 24, 28, 32, 36, 48, 72];
const LINE_SPACINGS = [1, 1.15, 1.5, 2, 2.5, 3];

const PAGE_SIZE_PRESETS = {
    A4: { width: 794, height: 1123 },
    Letter: { width: 816, height: 1056 },
    Legal: { width: 816, height: 1344 },
};

function button({ iconHtml, title, onClick, pressed = false }) {
    const el = document.createElement('button');
    el.type = 'button';
    el.title = title;
    el.setAttribute('aria-label', title);
    el.className = 'toolbar-btn';
    el.innerHTML = iconHtml;
    if (pressed) {
        el.setAttribute('aria-pressed', 'true');
    }
    el.addEventListener('mousedown', (event) => event.preventDefault());
    el.addEventListener('click', onClick);
    return el;
}

function setPressed(el, isPressed) {
    el.classList.toggle('toolbar-btn--active', isPressed);
    el.setAttribute('aria-pressed', isPressed ? 'true' : 'false');
}

function separator() {
    const el = document.createElement('div');
    el.className = 'mx-1 h-6 w-px shrink-0 bg-slate-200';
    el.setAttribute('role', 'separator');
    return el;
}

function group(...children) {
    const el = document.createElement('div');
    el.className = 'flex shrink-0 items-center gap-0.5';
    children.forEach((child) => el.appendChild(child));
    return el;
}

/* ---------------------------------------------------------------- History ---- */

function buildHistoryGroup(command) {
    const undo = button({ iconHtml: iconMarkup('undo'), title: 'Undo (Ctrl+Z)', onClick: () => command.executeUndo() });
    const redo = button({ iconHtml: iconMarkup('redo'), title: 'Redo (Ctrl+Y)', onClick: () => command.executeRedo() });

    return {
        element: group(undo, redo),
        update: (style) => {
            undo.disabled = !style.undo;
            redo.disabled = !style.redo;
            undo.classList.toggle('opacity-40', !style.undo);
            redo.classList.toggle('opacity-40', !style.redo);
        },
    };
}

/* -------------------------------------------------------------- Clipboard ---- */

function buildClipboardGroup(command) {
    const cut = button({ iconHtml: iconMarkup('cut'), title: 'Cut (Ctrl+X)', onClick: () => command.executeCut() });
    const copy = button({ iconHtml: iconMarkup('copy'), title: 'Copy (Ctrl+C)', onClick: () => command.executeCopy() });
    const paste = button({ iconHtml: iconMarkup('paste'), title: 'Paste (Ctrl+V)', onClick: () => command.executePaste() });
    const pastePlain = button({
        iconHtml: iconMarkup('pastePlain'),
        title: 'Paste without formatting (Ctrl+Shift+V)',
        onClick: () => command.executePaste({ isPlainText: true }),
    });
    const painter = button({
        iconHtml: iconMarkup('painter'),
        title: 'Format painter (click: apply once, double-click: keep applying)',
        onClick: () => command.executePainter({ isDblclick: false }),
    });
    painter.addEventListener('dblclick', () => command.executePainter({ isDblclick: true }));

    return {
        element: group(cut, copy, paste, pastePlain, painter),
        update: (style) => setPressed(painter, style.painter),
    };
}

/* -------------------------------------------------------------------- Font ---- */

function select(options, title, onChange) {
    const el = document.createElement('select');
    el.title = title;
    el.setAttribute('aria-label', title);
    el.className = 'toolbar-select';
    options.forEach(({ value, label }) => {
        const opt = document.createElement('option');
        opt.value = String(value);
        opt.textContent = label;
        el.appendChild(opt);
    });
    el.addEventListener('mousedown', (event) => event.stopPropagation());
    el.addEventListener('change', () => {
        onChange(el.value);
        // <select> must take focus to open; hand focus back to the editor immediately so
        // typing (and the next shortcut) resumes without an extra click.
        command_focus();
    });
    let command_focus = () => {};
    el.__setFocusBack = (fn) => { command_focus = fn; };
    return el;
}

function buildFontGroup(editor) {
    const { command } = editor;

    const family = select(WORD_FONTS.map((font) => ({ value: font, label: font })), 'Font family', (value) => command.executeFont(value));
    family.__setFocusBack(() => command.executeFocus());
    family.style.minWidth = '140px';

    const size = select(FONT_SIZES.map((value) => ({ value, label: String(value) })), 'Font size', (value) => command.executeSize(Number(value)));
    size.__setFocusBack(() => command.executeFocus());
    size.style.width = '64px';

    const sizeDown = button({ iconHtml: '&minus;', title: 'Decrease font size (Ctrl+})', onClick: () => command.executeSizeMinus() });
    const sizeUp = button({ iconHtml: '+', title: 'Increase font size (Ctrl+{)', onClick: () => command.executeSizeAdd() });

    return {
        element: group(family, sizeDown, size, sizeUp),
        update: (style) => {
            if (WORD_FONTS.includes(style.font) && family.value !== style.font) {
                family.value = style.font;
            }
            if (style.size && String(style.size) !== size.value && FONT_SIZES.includes(style.size)) {
                size.value = String(style.size);
            }
        },
    };
}

/* ---------------------------------------------------------- Text formatting ---- */

function buildTextFormattingGroup(editor) {
    const { command } = editor;

    const bold = button({ iconHtml: letterGlyph('B', 'font-weight:700'), title: 'Bold (Ctrl+B)', onClick: () => command.executeBold() });
    const italic = button({ iconHtml: letterGlyph('I', 'font-style:italic'), title: 'Italic (Ctrl+I)', onClick: () => command.executeItalic() });
    const underline = button({ iconHtml: letterGlyph('U', 'text-decoration:underline'), title: 'Underline (Ctrl+U)', onClick: () => command.executeUnderline() });
    const strikeout = button({ iconHtml: letterGlyph('S', 'text-decoration:line-through'), title: 'Strikethrough (Ctrl+Shift+X)', onClick: () => command.executeStrikeout() });
    const superscript = button({ iconHtml: letterGlyph('x²', ''), title: 'Superscript (Ctrl+Shift+.)', onClick: () => command.executeSuperscript() });
    const subscript = button({ iconHtml: letterGlyph('x₂', ''), title: 'Subscript (Ctrl+Shift+,)', onClick: () => command.executeSubscript() });

    const colorTrigger = button({ iconHtml: iconMarkup('color'), title: 'Text colour' });
    createPopover({
        trigger: colorTrigger,
        panelBuilder: (close) => colorPickerPanel({
            clearLabel: 'Automatic',
            onPick: (hex) => { command.executeColor(hex); command.executeFocus(); close(); },
            onClear: () => { command.executeColor(null); command.executeFocus(); close(); },
        }),
    });

    const highlightTrigger = button({ iconHtml: iconMarkup('highlight'), title: 'Highlight colour' });
    createPopover({
        trigger: highlightTrigger,
        panelBuilder: (close) => colorPickerPanel({
            clearLabel: 'None',
            onPick: (hex) => { command.executeHighlight(hex); command.executeFocus(); close(); },
            onClear: () => { command.executeHighlight(null); command.executeFocus(); close(); },
        }),
    });

    const clear = button({ iconHtml: iconMarkup('clearFormat'), title: 'Clear formatting', onClick: () => command.executeFormat() });

    return {
        element: group(bold, italic, underline, strikeout, superscript, subscript, colorTrigger, highlightTrigger, clear),
        update: (style) => {
            setPressed(bold, style.bold);
            setPressed(italic, style.italic);
            setPressed(underline, !!style.underline);
            setPressed(strikeout, style.strikeout);
        },
    };
}

/* ---------------------------------------------------------------- Paragraph ---- */

const HEADINGS = [
    { value: '', label: 'Normal text' },
    { value: TitleLevel.FIRST, label: 'Heading 1' },
    { value: TitleLevel.SECOND, label: 'Heading 2' },
    { value: TitleLevel.THIRD, label: 'Heading 3' },
    { value: TitleLevel.FOURTH, label: 'Heading 4' },
    { value: TitleLevel.FIFTH, label: 'Heading 5' },
    { value: TitleLevel.SIXTH, label: 'Heading 6' },
];

const ALIGNMENTS = [
    { value: RowFlex.LEFT, icon: 'alignLeft', label: 'Align left (Ctrl+L)' },
    { value: RowFlex.CENTER, icon: 'alignCenter', label: 'Align centre (Ctrl+E)' },
    { value: RowFlex.RIGHT, icon: 'alignRight', label: 'Align right (Ctrl+R)' },
    { value: RowFlex.JUSTIFY, icon: 'justify', label: 'Justify (Ctrl+J)' },
];

function buildParagraphGroup(editor) {
    const { command } = editor;

    const headingSelect = select(HEADINGS, 'Paragraph style', (value) => {
        command.executeTitle(value === '' ? null : value);
    });
    headingSelect.__setFocusBack ? headingSelect.__setFocusBack(() => command.executeFocus()) : null;
    headingSelect.style.minWidth = '130px';

    let currentAlignIcon = 'alignLeft';
    const alignTrigger = button({ iconHtml: iconMarkup(currentAlignIcon), title: 'Alignment' });
    createPopover({
        trigger: alignTrigger,
        panelBuilder: (close) => menuPanel(ALIGNMENTS.map(({ value, icon, label }) => menuItem({
            label,
            iconHtml: iconMarkup(icon),
            onSelect: () => { command.executeRowFlex(value); command.executeFocus(); close(); },
        }))),
    });

    const lineSpacingTrigger = button({ iconHtml: iconMarkup('lineSpacing'), title: 'Line spacing' });
    createPopover({
        trigger: lineSpacingTrigger,
        panelBuilder: (close) => menuPanel(LINE_SPACINGS.map((value) => menuItem({
            label: `${value}`,
            onSelect: () => { command.executeRowMargin(value); command.executeFocus(); close(); },
        }))),
    });

    const indentDecrease = button({
        iconHtml: iconMarkup('indentDecrease'),
        title: 'Decrease list indent (Shift+Tab, list only — see note)',
        onClick: () => dispatchListIndent(editor, true),
    });
    const indentIncrease = button({
        iconHtml: iconMarkup('indentIncrease'),
        title: 'Increase list indent (Tab, list only — see note)',
        onClick: () => dispatchListIndent(editor, false),
    });

    return {
        element: group(headingSelect, alignTrigger, lineSpacingTrigger, indentDecrease, indentIncrease),
        update: (style) => {
            const match = HEADINGS.find((heading) => heading.value === (style.level ?? ''));
            if (match) {
                headingSelect.value = match.value;
            }
            const align = ALIGNMENTS.find((entry) => entry.value === style.rowFlex) ?? ALIGNMENTS[0];
            if (align.icon !== currentAlignIcon) {
                currentAlignIcon = align.icon;
                alignTrigger.innerHTML = iconMarkup(currentAlignIcon);
            }
            const inList = style.listType !== null;
            indentDecrease.disabled = !inList;
            indentIncrease.disabled = !inList;
            indentDecrease.classList.toggle('opacity-40', !inList);
            indentIncrease.classList.toggle('opacity-40', !inList);
        },
    };
}

/* -------------------------------------------------------------------- Lists ---- */

function buildListsGroup(command) {
    function apply(type, style) {
        command.executeList(type, style);
        command.executeFocus();
    }

    const bullet = button({
        iconHtml: iconMarkup('bulletList'),
        title: 'Bulleted list (Ctrl+Shift+I)',
        onClick: () => apply(ListType.UL, ListStyle.DISC),
    });
    const numbered = button({
        iconHtml: iconMarkup('numberedList'),
        title: 'Numbered list (Ctrl+Shift+U)',
        onClick: () => apply(ListType.OL, ListStyle.DECIMAL),
    });

    const stylesTrigger = button({ iconHtml: iconMarkup('chevronDown'), title: 'List style' });
    createPopover({
        trigger: stylesTrigger,
        panelBuilder: (close) => menuPanel([
            menuItem({ label: 'Bulleted •', onSelect: () => { apply(ListType.UL, ListStyle.DISC); close(); } }),
            menuItem({ label: 'Bulleted ○', onSelect: () => { apply(ListType.UL, ListStyle.CIRCLE); close(); } }),
            menuItem({ label: 'Bulleted ■', onSelect: () => { apply(ListType.UL, ListStyle.SQUARE); close(); } }),
            menuItem({ label: 'Numbered 1, 2, 3…', onSelect: () => { apply(ListType.OL, ListStyle.DECIMAL); close(); } }),
            menuItem({ label: 'Checklist', onSelect: () => { apply(ListType.UL, ListStyle.CHECKBOX); close(); } }),
            menuItem({ label: 'None', onSelect: () => { apply(null); close(); } }),
        ]),
    });

    return {
        element: group(bullet, numbered, stylesTrigger),
        update: (style) => {
            setPressed(bullet, style.listType === ListType.UL && style.listStyle !== ListStyle.CHECKBOX);
            setPressed(numbered, style.listType === ListType.OL);
        },
    };
}

/* ------------------------------------------------------------------- Insert ---- */

function buildInsertGroup(editor, { imageUploadUrl }) {
    const { command } = editor;
    const trigger = button({ iconHtml: iconMarkup('more'), title: 'Insert' });
    trigger.classList.add('toolbar-btn--labeled');
    trigger.innerHTML = `${iconMarkup('table')}<span>Insert</span>${iconMarkup('chevronDown')}`;

    const tableTrigger = document.createElement('button');
    let closeInsertMenu = () => {};

    createPopover({
        trigger,
        panelBuilder: (close) => {
            closeInsertMenu = close;
            return menuPanel([
                menuItem({
                    label: 'Table',
                    iconHtml: iconMarkup('table'),
                    onSelect: () => {
                        close();
                        openTableGridPicker();
                    },
                }),
                menuItem({
                    label: 'Image',
                    iconHtml: iconMarkup('image'),
                    onSelect: () => { close(); triggerImageUpload(); },
                    disabled: !imageUploadUrl,
                }),
                menuItem({
                    label: 'Link',
                    iconHtml: iconMarkup('link'),
                    shortcut: 'Ctrl+K',
                    onSelect: () => { close(); insertLink(); },
                }),
                menuItem({
                    label: 'Horizontal line',
                    iconHtml: iconMarkup('hr'),
                    onSelect: () => { close(); command.executeSeparator([0]); },
                }),
                menuItem({
                    label: 'Page break',
                    iconHtml: iconMarkup('pageBreak'),
                    onSelect: () => { close(); command.executePageBreak(); },
                }),
                menuItem({
                    label: 'Special character',
                    iconHtml: iconMarkup('specialChar'),
                    onSelect: () => { close(); openSpecialCharacters(); },
                }),
                menuItem({
                    label: 'Header / footer',
                    iconHtml: iconMarkup('headerFooter'),
                    onSelect: () => { close(); openHeaderFooterMenu(); },
                }),
            ]);
        },
    });

    const tableGridPopover = createPopover({
        trigger: tableTrigger,
        panelBuilder: () => tableGridPickerPanel({
            onSelect: (rows, cols) => {
                command.executeInsertTable(rows, cols);
                command.executeFocus();
                tableGridPopover.close();
            },
        }),
    });
    function openTableGridPicker() {
        const rect = trigger.getBoundingClientRect();
        tableTrigger.getBoundingClientRect = () => rect;
        tableGridPopover.open();
    }

    const imageInput = document.createElement('input');
    imageInput.type = 'file';
    imageInput.accept = 'image/*';
    imageInput.className = 'hidden';
    imageInput.addEventListener('change', () => uploadSelectedImage(imageInput, command, imageUploadUrl));
    document.body.appendChild(imageInput);
    function triggerImageUpload() {
        imageInput.click();
    }

    function insertLink() {
        const text = window.prompt('Link text?');
        if (!text) {
            return;
        }
        const url = window.prompt('URL?');
        if (!url) {
            return;
        }
        command.executeHyperlink({ valueList: [{ value: text }], url });
        command.executeFocus();
    }

    const SPECIAL_CHARACTERS = ['©', '®', '™', '€', '£', '¥', '§', '¶', '†', '‡', '•', '…', '«', '»', '“', '”', '‘', '’', '–', '—', '±', '×', '÷', '≠', '≤', '≥', '∞', 'α', 'β', 'π'];
    const specialCharTrigger = document.createElement('button');
    const specialCharPopover = createPopover({
        trigger: specialCharTrigger,
        panelBuilder: () => {
            const grid = document.createElement('div');
            grid.className = 'grid w-64 grid-cols-8 gap-1';
            SPECIAL_CHARACTERS.forEach((char) => {
                const cell = document.createElement('button');
                cell.type = 'button';
                cell.className = 'rounded-md p-1.5 text-base hover:bg-slate-100';
                cell.textContent = char;
                cell.addEventListener('mousedown', (event) => event.preventDefault());
                cell.addEventListener('click', () => {
                    command.executeInsertElementList([{ value: char }]);
                    command.executeFocus();
                    specialCharPopover.close();
                });
                grid.appendChild(cell);
            });
            return grid;
        },
    });
    function openSpecialCharacters() {
        const rect = trigger.getBoundingClientRect();
        specialCharTrigger.getBoundingClientRect = () => rect;
        specialCharPopover.open();
    }

    const headerFooterTrigger = document.createElement('button');
    const headerFooterPopover = createPopover({
        trigger: headerFooterTrigger,
        panelBuilder: (close) => menuPanel([
            menuItem({ label: 'Edit header', onSelect: () => { command.executeSetZone(EditorZone.HEADER); close(); } }),
            menuItem({ label: 'Edit footer', onSelect: () => { command.executeSetZone(EditorZone.FOOTER); close(); } }),
            menuItem({ label: 'Back to body', onSelect: () => { command.executeSetZone(EditorZone.MAIN); close(); } }),
        ]),
    });
    function openHeaderFooterMenu() {
        const rect = trigger.getBoundingClientRect();
        headerFooterTrigger.getBoundingClientRect = () => rect;
        headerFooterPopover.open();
    }

    return { element: group(trigger), update: () => {} };
}

function uploadSelectedImage(input, command, imageUploadUrl) {
    const file = input.files?.[0];
    input.value = '';

    if (!file || !imageUploadUrl) {
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const formData = new FormData();
    formData.append('image', file);

    fetch(imageUploadUrl, {
        method: 'POST',
        headers: csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {},
        body: formData,
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error(`Upload failed with status ${response.status}`);
            }
            return response.json();
        })
        .then(({ url, width, height }) => {
            command.executeImage({ value: url, width, height });
            command.executeFocus();
        })
        .catch((error) => {
            console.error('Image upload failed', error);
            window.alert('Could not upload the image. Please try again.');
        });
}

/* ------------------------------------------------------------- Page layout ---- */

/**
 * canvas-editor's own getOptions()/getPaperMargin() were verified (via a live save +
 * reload round-trip, not assumed) to NOT reflect changes made through
 * executePaperSize/executePaperDirection/executeSetPaperMargin — those mutate the
 * live-rendered page but don't feed back into the getters. So Page Setup's applied
 * values are tracked here instead of read back from the editor, and this tracked copy
 * (not a fresh getOptions() call) is what gets persisted.
 */
function readInitialPageOptions(command) {
    const options = command.getOptions();

    return {
        width: options.width,
        height: options.height,
        margins: command.getPaperMargin(),
        paperDirection: options.paperDirection,
        background: options.background,
        pageNumber: options.pageNumber,
        columns: options.columns,
    };
}

function buildPageLayoutGroup(editor, pageOptions) {
    const { command } = editor;
    const trigger = button({ iconHtml: iconMarkup('pageSetup'), title: 'Page setup' });

    trigger.addEventListener('click', () => openPageSetupDialog(command, pageOptions));

    return { element: group(trigger), update: () => {} };
}

function openPageSetupDialog(command, pageOptions) {
    const options = pageOptions;
    const margins = pageOptions.margins;

    openModal({
        title: 'Page setup',
        bodyBuilder: (close) => {
            const form = document.createElement('div');
            form.className = 'flex flex-col gap-4';

            const note = document.createElement('p');
            note.className = 'rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700';
            note.textContent = CAPABILITIES.page_layout_affects_pdf.reason;
            form.appendChild(note);

            const sizeSelect = select(
                Object.keys(PAGE_SIZE_PRESETS).map((label) => ({ value: label, label })),
                'Page size',
                () => {},
            );
            // Landscape stores width/height swapped relative to the preset's portrait
            // dimensions (see the Apply handler below), so match against both orientations.
            const currentSize = Object.entries(PAGE_SIZE_PRESETS).find(([, dims]) => (
                (dims.width === options.width && dims.height === options.height)
                || (dims.width === options.height && dims.height === options.width)
            ));
            sizeSelect.value = currentSize ? currentSize[0] : 'A4';

            const orientation = document.createElement('select');
            orientation.className = 'toolbar-select';
            [['vertical', 'Portrait'], ['horizontal', 'Landscape']].forEach(([value, label]) => {
                const opt = document.createElement('option');
                opt.value = value;
                opt.textContent = label;
                orientation.appendChild(opt);
            });
            orientation.value = options.paperDirection === PaperDirection.HORIZONTAL ? 'horizontal' : 'vertical';

            const row1 = document.createElement('div');
            row1.className = 'grid grid-cols-2 gap-3';
            row1.append(labeledField('Page size', sizeSelect), labeledField('Orientation', orientation));
            form.appendChild(row1);

            const [mTop, mRight, mBottom, mLeft] = margins;
            const marginTop = numberInput(mTop, { min: 0 });
            const marginRight = numberInput(mRight, { min: 0 });
            const marginBottom = numberInput(mBottom, { min: 0 });
            const marginLeft = numberInput(mLeft, { min: 0 });
            const marginsRow = document.createElement('div');
            marginsRow.className = 'grid grid-cols-4 gap-2';
            marginsRow.append(
                labeledField('Top', marginTop), labeledField('Right', marginRight),
                labeledField('Bottom', marginBottom), labeledField('Left', marginLeft),
            );
            form.appendChild(labeledField('Margins (px)', marginsRow));

            const background = document.createElement('input');
            background.type = 'color';
            background.className = 'h-8 w-14 rounded border border-slate-300';
            background.value = options.background?.color || '#ffffff';

            const columns = numberInput(options.columns?.count ?? 1, { min: 1, max: 3 });

            const row2 = document.createElement('div');
            row2.className = 'grid grid-cols-2 gap-3';
            row2.append(labeledField('Page background', background), labeledField('Columns', columns));
            form.appendChild(row2);

            const pageNumberEnabled = document.createElement('input');
            pageNumberEnabled.type = 'checkbox';
            pageNumberEnabled.checked = !options.pageNumber?.disabled;
            const pageNumberLabel = document.createElement('label');
            pageNumberLabel.className = 'flex items-center gap-2 text-sm text-slate-700';
            pageNumberLabel.append(pageNumberEnabled, document.createTextNode('Show page numbers'));
            form.appendChild(pageNumberLabel);

            const actions = document.createElement('div');
            actions.className = 'mt-2 flex justify-end gap-2 border-t border-slate-100 pt-4';
            const cancel = document.createElement('button');
            cancel.type = 'button';
            cancel.className = 'rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50';
            cancel.textContent = 'Cancel';
            cancel.addEventListener('click', close);

            const apply = document.createElement('button');
            apply.type = 'button';
            apply.className = 'rounded-lg bg-red-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-800';
            apply.textContent = 'Apply';
            apply.addEventListener('click', () => {
                const preset = PAGE_SIZE_PRESETS[sizeSelect.value];
                const isLandscape = orientation.value === 'horizontal';
                const width = isLandscape ? Math.max(preset.width, preset.height) : Math.min(preset.width, preset.height);
                const height = isLandscape ? Math.min(preset.width, preset.height) : Math.max(preset.width, preset.height);
                const paperDirection = isLandscape ? PaperDirection.HORIZONTAL : PaperDirection.VERTICAL;
                const margin = [
                    Number(marginTop.value), Number(marginRight.value), Number(marginBottom.value), Number(marginLeft.value),
                ];
                const updatedBackground = { ...pageOptions.background, color: background.value };
                const updatedPageNumber = { ...pageOptions.pageNumber, disabled: !pageNumberEnabled.checked };
                const updatedColumns = Number(columns.value) > 1 ? { count: Number(columns.value), gap: 30 } : null;

                command.executePaperSize(width, height);
                command.executePaperDirection(paperDirection);
                command.executeSetPaperMargin(margin);
                command.executeUpdateOptions({ background: updatedBackground, pageNumber: updatedPageNumber });
                command.executeSetColumns(updatedColumns);
                command.executeFocus();

                // Record what was actually applied — see readInitialPageOptions() for why
                // this isn't read back from the editor afterward.
                Object.assign(pageOptions, {
                    width, height, margins: margin, paperDirection, background: updatedBackground, pageNumber: updatedPageNumber, columns: updatedColumns,
                });

                close();
            });

            actions.append(cancel, apply);
            form.appendChild(actions);

            return form;
        },
    });
}

/* ------------------------------------------------------------------- Tools ---- */

function buildToolsGroup(editor) {
    const { command } = editor;

    const findTrigger = button({ iconHtml: iconMarkup('find'), title: 'Find and replace (Ctrl+F)', onClick: () => openFindReplace(editor, findTrigger) });

    const wordCount = document.createElement('span');
    wordCount.className = 'px-2 text-xs text-slate-500';
    wordCount.textContent = '0 words';
    async function refreshWordCount() {
        try {
            const count = await command.getWordCount();
            wordCount.textContent = `${count} word${count === 1 ? '' : 's'}`;
        } catch {
            // getWordCount can reject mid-edit; the next contentChange retries.
        }
    }
    refreshWordCount();

    let zoom = 100;
    const zoomOut = button({ iconHtml: '&minus;', title: 'Zoom out', onClick: () => { command.executePageScaleMinus(); zoom = Math.max(50, zoom - 10); zoomLabel.textContent = `${zoom}%`; } });
    const zoomLabel = document.createElement('span');
    zoomLabel.className = 'w-12 text-center text-xs text-slate-600';
    zoomLabel.textContent = '100%';
    const zoomIn = button({ iconHtml: '+', title: 'Zoom in', onClick: () => { command.executePageScaleAdd(); zoom = Math.min(200, zoom + 10); zoomLabel.textContent = `${zoom}%`; } });
    zoomLabel.addEventListener('dblclick', () => { command.executePageScaleRecovery(); zoom = 100; zoomLabel.textContent = '100%'; });

    const fullscreen = button({
        iconHtml: iconMarkup('fullscreen'),
        title: 'Fullscreen (application-level — not a canvas-editor feature)',
        onClick: () => toggleFullscreen(command.getContainer().closest('[data-canvas-editor]') ?? command.getContainer()),
    });

    return {
        element: group(findTrigger, zoomOut, zoomLabel, zoomIn, fullscreen, wordCount),
        update: () => {},
        onContentChange: refreshWordCount,
    };
}

function toggleFullscreen(el) {
    if (document.fullscreenElement) {
        document.exitFullscreen();
    } else {
        el.requestFullscreen?.();
    }
}

function openFindReplace(editor, anchor) {
    const { command } = editor;
    const popover = createPopover({
        trigger: anchor,
        className: 'w-80',
        panelBuilder: (close) => {
            const wrap = document.createElement('div');
            wrap.className = 'flex flex-col gap-2';

            const searchInput = document.createElement('input');
            searchInput.type = 'text';
            searchInput.placeholder = 'Find';
            searchInput.className = 'rounded-md border border-slate-300 px-2 py-1.5 text-sm';
            searchInput.addEventListener('input', () => command.executeSearch(searchInput.value || null));

            const replaceInput = document.createElement('input');
            replaceInput.type = 'text';
            replaceInput.placeholder = 'Replace with';
            replaceInput.className = 'rounded-md border border-slate-300 px-2 py-1.5 text-sm';

            const actions = document.createElement('div');
            actions.className = 'flex flex-wrap gap-1.5';
            const prev = menuItem({ label: 'Previous', iconHtml: iconMarkup('find'), onSelect: () => command.executeSearchNavigatePre() });
            const next = menuItem({ label: 'Next', iconHtml: iconMarkup('find'), onSelect: () => command.executeSearchNavigateNext() });
            const replaceOne = menuItem({ label: 'Replace', iconHtml: iconMarkup('replace'), onSelect: () => command.executeReplace(replaceInput.value) });
            [prev, next, replaceOne].forEach((el) => { el.className += ' w-auto flex-none border border-slate-200'; });
            actions.append(prev, next, replaceOne);

            const closeBtn = menuItem({ label: 'Close', onSelect: () => { command.executeSearch(null); close(); command.executeFocus(); } });

            wrap.append(searchInput, replaceInput, actions, closeBtn);
            setTimeout(() => searchInput.focus(), 0);
            return wrap;
        },
    });
    popover.open();
}

/* ---------------------------------------------------------------------------- */

export function buildToolbar(editor, toolbarEl, { imageUploadUrl } = {}) {
    const { command } = editor;
    const state = createToolbarState(editor);
    const pageOptions = readInitialPageOptions(command);

    const groups = [
        buildHistoryGroup(command),
        buildClipboardGroup(command),
        buildFontGroup(editor),
        buildTextFormattingGroup(editor),
        buildParagraphGroup(editor),
        buildListsGroup(command),
        buildInsertGroup(editor, { imageUploadUrl }),
        buildPageLayoutGroup(editor, pageOptions),
        buildToolsGroup(editor),
    ];

    toolbarEl.setAttribute('role', 'toolbar');
    toolbarEl.setAttribute('aria-label', 'Document formatting');
    groups.forEach((groupDef, index) => {
        toolbarEl.appendChild(groupDef.element);
        if (index < groups.length - 1) {
            toolbarEl.appendChild(separator());
        }
    });

    state.onRangeStyleChange((style) => groups.forEach((groupDef) => groupDef.update(style)));
    state.onContentChange(() => groups.forEach((groupDef) => groupDef.onContentChange?.()));

    registerAdditionalShortcuts(editor, {
        onFind: () => {
            const findTrigger = toolbarEl.querySelector('[title^="Find and replace"]');
            findTrigger?.click();
        },
        onInsertLink: () => {
            const text = window.prompt('Link text?');
            if (!text) return;
            const url = window.prompt('URL?');
            if (!url) return;
            command.executeHyperlink({ valueList: [{ value: text }], url });
            command.executeFocus();
        },
    });

    return { getPageOptions: () => ({ ...pageOptions }) };
}

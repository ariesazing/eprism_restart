import {
    RowFlex, ListType, ListStyle, TitleLevel, EditorZone, PaperDirection, ElementType, ImageDisplay,
} from '@hufe921/canvas-editor';
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

// Matches SubmissionPdfComposer::INNER_GAP (PHP) — the breathing room a fresh template
// gets between the header/footer's own content box and the body if the admin never opens
// Page Setup, so an unconfigured template renders the same as before this field existed.
const DEFAULT_HEADER_FOOTER_GAP = 8;

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
        panelBuilder: (close) => menuPanel([
            menuItem({
                label: 'Remove spacing',
                onSelect: () => { command.executeRowMargin(1); command.executeFocus(); close(); },
            }),
            ...LINE_SPACINGS.map((value) => menuItem({
                label: `${value}`,
                onSelect: () => { command.executeRowMargin(value); command.executeFocus(); close(); },
            })),
        ]),
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

/**
 * A canvas-editor document is WYSIWYG only — there's no way to type literal markup like
 * `<img src="${proponent_photo}">`, so `${proponent_photo}` typed as plain text (the same
 * way `${proponent_name}` is used) can only ever render as its own raw value: the photo's
 * full base64 data URI, as visible text. "Insert > Proponent photo placeholder" below
 * inserts a *real* image element instead, so SubmissionHtmlTemplateRenderer::
 * substitutePhotoPlaceholder() (PHP — keep this exact string identical on both sides) can
 * find it inside each proponent's row and swap in their actual photo at render time, at
 * whatever size/position the admin left it. A gray person-silhouette PNG, not SVG: the
 * sanitizer's HTMLPurifier config strips `data:image/svg+xml` URIs outright (SVG can carry
 * a <script>, so its data-URI allowlist only trusts raster formats) — verified by round-
 * tripping an SVG version through SubmissionSectionService::sanitizeRichText(), which
 * silently deleted the whole <img>.
 */
const PROPONENT_PHOTO_PLACEHOLDER_SRC = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAHgAAACWCAYAAAAVKkwgAAAACXBIWXMAAA7EAAAOxAGVKw4bAAADZUlEQVR4nO3dQW4TQRCF4Q7iUFyALYdlywVyjqxYcgNYtXBGtmfGU9311+v3r5Fc875YCUg4bx+///xtTrYv2Qe4sRlYPAOLZ2DxDCyegcUzsHgGFs/A4hlYPAOLZ2DxDCyegcUzsHgGFs/A4hlYPAOLZ2DxDCyegcUzsHgGFs/A4hlYPAOL9zX7gNH9/PW++2d+fP824ZKc3tT+89kR0L2UwGWAI2C3KUCXBx4Bu60ydFngGbDbKkKX/Ck6Azfzda9UDjh75OzXP1spYMq4lDuOVAaYNirtnkeVAKaOSb3rNjwwfUT6fWhg+ng98p1YYPJo96LeiwV2MSGBqe+GvYh3I4FdXDhg4rvgTLT7ccAuNhQw7av/1UjPgQJ28RlYPAOLhwEmfd+KiPI8GGA3JgOLZ2DxDCyegcUzsHgGFs/A4mGAK/63kGdRngcD7MZkYPEMLB4KmPJ962qk50ABu/hwwKSv/lei3Y8DdrEhgWnvgqMR70YCu7iwwMR3w7Oo92KBW+OOto18Jxq4NfZ4rfHvwwO3xh2RetdtJYBb441Ju+dRZYBb44xKueNIpYBbyx83+/XPVg64tbyRq+G2VvjDSHv+tNnnlQfu+fOi7ycD3PMnvn9ODnibf2eDOPDqlfwp2h3PwOIZWDwDi2dg8QwsnoHFM7B4BhbPwOIZWDwDi7ccMOUzJGe1FHDHXQl5GeAt6irISwA/wlwBWR54D1EdWRr4KJ4ysjTwmVSRZYFVwc4mCfwqruIXhRzwVSQ1ZCngKBwlZBngaBQVZAngURgKyOWBRyNURy4NPGv8yshlgWePXhW5JHDW2BWRywFnj5z9+mcrB0yoEnIp4ErDUioDTMOl3fOoEsDUMal33YYHpo9Ivw8NTB+vR74TC0we7V7Ue5HA1LH2It6NAyaOdCba/Shg2jivRnoODDBplIgoz4MApowRHeG5EMDKZSOnA2cPoF4q8Cq4mc+ZBrwKbi/reVOAV8PtZTz3dOBVcXuzn38q8Oq4vZk7TAM27udm7TEF2Lj3m7HLcGDjPm/0PkOBjXuskTsNAzbuuUbtlf5Ple5/I5CHAPvdyykc2LjXit4vFNi4MUXuGAZs3Nii9gwBNu6YIna9DGzcsV3d1799VDz/PVg8A4tnYPEMLJ6BxTOweP8AIoRRk26vajEAAAAASUVORK5CYII=';

// canvas-editor has no shape/vector element type (its ElementType enum only covers text,
// images, tables, and a handful of other block kinds — no rectangle/line/arrow primitive,
// and its Graffiti feature is a freehand pen, not a shape tool). A shape is instead drawn
// onto an offscreen <canvas> and inserted as a rasterized PNG through the same
// command.executeImage() path "Insert > Image" already uses — a PNG rather than SVG for
// the same sanitizer reason as PROPONENT_PHOTO_PLACEHOLDER_SRC above (HTMLPurifier strips
// data:image/svg+xml). The tradeoff: once inserted, a shape is a flattened image — resizable
// like any image, but its colors/stroke can't be edited in place, only re-inserted.
const SHAPE_RASTER_SCALE = 3;

function renderShapeToDataUri(shape) {
    const { kind, width, height, strokeColor, strokeWidth, fillColor, filled } = shape;

    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(width * SHAPE_RASTER_SCALE));
    canvas.height = Math.max(1, Math.round(height * SHAPE_RASTER_SCALE));

    const ctx = canvas.getContext('2d');
    ctx.scale(SHAPE_RASTER_SCALE, SHAPE_RASTER_SCALE);
    ctx.lineWidth = strokeWidth;
    ctx.strokeStyle = strokeColor;
    ctx.fillStyle = fillColor;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    const inset = strokeWidth / 2;

    if (kind === 'rectangle') {
        const rectWidth = Math.max(0, width - strokeWidth);
        const rectHeight = Math.max(0, height - strokeWidth);
        if (filled) {
            ctx.fillRect(inset, inset, rectWidth, rectHeight);
        }
        ctx.strokeRect(inset, inset, rectWidth, rectHeight);
    } else {
        const y = height / 2;
        ctx.beginPath();
        ctx.moveTo(inset, y);
        ctx.lineTo(width - inset, y);
        ctx.stroke();

        if (kind === 'arrow') {
            const headLength = Math.max(8, strokeWidth * 4);
            ctx.beginPath();
            ctx.moveTo(width - inset, y);
            ctx.lineTo(width - inset - headLength, y - headLength / 1.6);
            ctx.moveTo(width - inset, y);
            ctx.lineTo(width - inset - headLength, y + headLength / 1.6);
            ctx.stroke();
        }
    }

    return canvas.toDataURL('image/png');
}

function openShapeModal(command) {
    let kind = 'rectangle';

    const widthInput = numberInput(160, { min: 10, max: 760 });
    const heightInput = numberInput(90, { min: 10, max: 760 });
    const strokeWidthInput = numberInput(2, { min: 1, max: 20 });
    const strokeColorInput = document.createElement('input');
    strokeColorInput.type = 'color';
    strokeColorInput.value = '#1f2937';
    strokeColorInput.className = 'h-8 w-14 rounded border border-slate-300';
    const fillColorInput = document.createElement('input');
    fillColorInput.type = 'color';
    fillColorInput.value = '#fde68a';
    fillColorInput.className = 'h-8 w-14 rounded border border-slate-300';
    const fillToggle = document.createElement('input');
    fillToggle.type = 'checkbox';

    const heightField = labeledField('Height (px)', heightInput);
    const fillColorField = labeledField('Fill colour', fillColorInput);
    const fillToggleLabel = document.createElement('label');
    fillToggleLabel.className = 'flex items-center gap-2 text-xs font-medium text-slate-600';
    fillToggleLabel.append(fillToggle, document.createTextNode('Filled'));

    function syncFieldsToKind() {
        const isLine = kind !== 'rectangle';
        heightField.classList.toggle('hidden', isLine);
        fillColorField.classList.toggle('hidden', isLine);
        fillToggleLabel.classList.toggle('hidden', isLine);
    }

    const { close } = openModal({
        title: 'Insert shape',
        bodyBuilder: () => {
            const body = document.createElement('div');
            body.className = 'grid gap-4';

            const kindRow = document.createElement('div');
            kindRow.className = 'flex gap-2';
            [
                { value: 'rectangle', label: 'Rectangle', icon: 'shapeRectangle' },
                { value: 'line', label: 'Line', icon: 'shapeLine' },
                { value: 'arrow', label: 'Arrow', icon: 'shapeArrow' },
            ].forEach(({ value, label, icon }) => {
                const optionButton = document.createElement('button');
                optionButton.type = 'button';
                optionButton.className = 'toolbar-btn toolbar-btn--labeled';
                optionButton.innerHTML = `${iconMarkup(icon)}<span>${label}</span>`;
                optionButton.classList.toggle('toolbar-btn--active', kind === value);
                optionButton.addEventListener('click', () => {
                    kind = value;
                    kindRow.querySelectorAll('button').forEach((btn, index) => {
                        btn.classList.toggle('toolbar-btn--active', ['rectangle', 'line', 'arrow'][index] === kind);
                    });
                    syncFieldsToKind();
                });
                kindRow.appendChild(optionButton);
            });
            body.appendChild(kindRow);

            const dimensionsRow = document.createElement('div');
            dimensionsRow.className = 'flex gap-3';
            dimensionsRow.append(labeledField('Width (px)', widthInput), heightField);
            body.appendChild(dimensionsRow);

            const styleRow = document.createElement('div');
            styleRow.className = 'flex flex-wrap items-end gap-3';
            styleRow.append(
                labeledField('Stroke colour', strokeColorInput),
                labeledField('Stroke width', strokeWidthInput),
                fillColorField,
                fillToggleLabel,
            );
            body.appendChild(styleRow);

            const actions = document.createElement('div');
            actions.className = 'mt-1 flex justify-end gap-2';
            const cancelButton = document.createElement('button');
            cancelButton.type = 'button';
            cancelButton.className = 'rounded-lg px-3 py-1.5 text-xs font-medium text-slate-500';
            cancelButton.textContent = 'Cancel';
            cancelButton.addEventListener('click', () => close());
            const insertButton = document.createElement('button');
            insertButton.type = 'button';
            insertButton.className = 'rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white';
            insertButton.textContent = 'Insert';
            insertButton.addEventListener('click', () => {
                const width = Number(widthInput.value) || 160;
                const height = kind === 'rectangle' ? (Number(heightInput.value) || 90) : Math.max(20, Number(strokeWidthInput.value) * 6);

                const dataUri = renderShapeToDataUri({
                    kind,
                    width,
                    height,
                    strokeColor: strokeColorInput.value,
                    strokeWidth: Number(strokeWidthInput.value) || 2,
                    fillColor: fillColorInput.value,
                    filled: fillToggle.checked,
                });

                command.executeImage({ value: dataUri, width, height });
                command.executeFocus();
                close();
            });
            actions.append(cancelButton, insertButton);
            body.appendChild(actions);

            syncFieldsToKind();

            return body;
        },
    });
}

function buildInsertGroup(editor, { imageUploadUrl, includeTemplateTools = true }) {
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
                    label: 'Shape',
                    iconHtml: iconMarkup('shapeRectangle'),
                    onSelect: () => { close(); openShapeModal(command); },
                }),
                ...(includeTemplateTools ? [menuItem({
                    label: 'Proponent photo placeholder',
                    iconHtml: iconMarkup('image'),
                    onSelect: () => {
                        close();
                        command.executeImage({ value: PROPONENT_PHOTO_PLACEHOLDER_SRC, width: 120, height: 150 });
                        command.executeFocus();
                    },
                })] : []),
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
                ...(includeTemplateTools ? [menuItem({
                    label: 'Header / footer',
                    iconHtml: iconMarkup('headerFooter'),
                    onSelect: () => { close(); openHeaderFooterMenu(); },
                })] : []),
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

/* ----------------------------------------------------------- Image layout ---- */

// canvas-editor's ImageDisplay enum drives both wrapping and stacking: SURROUND wraps
// text around the image, BLOCK gives it its own line, and FLOAT_TOP/FLOAT_BOTTOM float it
// freely (draggable) either in front of or behind the text — verified against the
// renderer's own draw order, where the FLOAT_BOTTOM pass runs before drawRow() and the
// FLOAT_TOP/SURROUND pass runs after it.
const IMAGE_LAYOUTS = [
    { value: ImageDisplay.INLINE, label: 'In line with text' },
    { value: ImageDisplay.SURROUND, label: 'Square (wrap text)' },
    { value: ImageDisplay.BLOCK, label: 'Top and bottom' },
    { value: ImageDisplay.FLOAT_TOP, label: 'In front of text' },
    { value: ImageDisplay.FLOAT_BOTTOM, label: 'Behind text' },
];
const FLOATING_IMAGE_DISPLAYS = [ImageDisplay.SURROUND, ImageDisplay.FLOAT_TOP, ImageDisplay.FLOAT_BOTTOM];

function buildImageLayoutGroup(editor) {
    const { command } = editor;
    const trigger = button({ iconHtml: iconMarkup('imageWrap'), title: 'Image text wrapping (select an image first)' });
    trigger.classList.add('opacity-40');

    // The trigger itself is never `disabled` — canvas-editor broadcasts a "recovered"
    // (blurred/neutral) rangeStyleChange the instant this button's own mousedown bubbles
    // to the document, which would flip a disabled attribute true before the click could
    // land, making the popover unopenable by mouse. getRangeContext() isn't affected by
    // that broadcast, so it's read fresh — right here, at open time — instead.
    createPopover({
        trigger,
        panelBuilder: (close) => {
            // getRangeContext()'s startElement/selectionElementList are always fresh
            // clones (verified: mutating one never affects the live document), so
            // executeChangeImageDisplay(clone, …) would be a silent no-op. Only
            // startElement carries `id` through that cloning (selectionElementList
            // doesn't), and every image gets an id from executeImage() at insert time —
            // so executeUpdateElementById({ id, properties }) is the one documented,
            // live-acting way to apply this from outside canvas-editor's own internal
            // context-menu code (which is the only other place that changes imgDisplay,
            // and only because it holds a genuinely live element reference internally).
            const context = command.getRangeContext();
            const isImage = context?.startElement?.type === ElementType.IMAGE;

            if (!isImage) {
                return menuPanel([menuItem({ label: 'Select an image first', disabled: true, onSelect: () => {} })]);
            }

            const imageId = context.startElement.id;

            return menuPanel(IMAGE_LAYOUTS.map(({ value, label }) => menuItem({
                label,
                onSelect: () => {
                    const properties = { imgDisplay: value };

                    if (FLOATING_IMAGE_DISPLAYS.includes(value)) {
                        // Floating displays need a starting imgFloatPosition (the image
                        // is draggable afterward) — mirrors canvas-editor's own internal
                        // formula: the coordinate of the position right before the image,
                        // on the page it currently sits on.
                        const { startIndex } = command.getRange();
                        command.executeSetRange(startIndex, startIndex);
                        const cursorPosition = command.getCursorPosition();
                        if (cursorPosition) {
                            properties.imgFloatPosition = {
                                pageNo: cursorPosition.pageNo,
                                x: cursorPosition.coordinate.leftTop[0],
                                y: cursorPosition.coordinate.leftTop[1],
                            };
                        }
                    }

                    command.executeUpdateElementById({ id: imageId, properties });
                    command.executeFocus();
                    close();
                },
            })));
        },
    });

    return {
        element: group(trigger),
        // Cosmetic only (dims the icon between images) — never gates the click, since the
        // same broadcast that would drive it here is the one that goes stale on mousedown.
        update: (style) => {
            trigger.classList.toggle('opacity-40', style.type !== ElementType.IMAGE);
        },
    };
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
function readInitialPageOptions(command, savedPageOptions) {
    const options = command.getOptions();

    return {
        width: options.width,
        height: options.height,
        margins: command.getPaperMargin(),
        paperDirection: options.paperDirection,
        background: options.background,
        pageNumber: options.pageNumber,
        columns: options.columns,
        // header.top / footer.bottom are the offset from the page edge to where
        // header/footer content starts (not a height) — combined with margins[0]/[2]
        // above, the PDF composer (SubmissionPdfComposer::resolveGeometry) derives the
        // same reserved band canvas-editor itself lays out, so the editor and the
        // generated PDF agree instead of drifting apart.
        header: options.header,
        footer: options.footer,
        // headerGap/footerGap have no canvas-editor equivalent at all — they're purely
        // how much blank breathing room dompdf leaves between the header/footer's own
        // content box and where the body starts (see SubmissionPdfComposer::
        // resolveGeometry). Since canvas-editor doesn't model this, it can't be read
        // off command.getOptions() the way header/footer above are — it's tracked here
        // from whatever was last saved instead, same as header/footer already are.
        headerGap: savedPageOptions?.headerGap ?? DEFAULT_HEADER_FOOTER_GAP,
        footerGap: savedPageOptions?.footerGap ?? DEFAULT_HEADER_FOOTER_GAP,
    };
}

function buildPageLayoutGroup(editor, pageOptions, { onApplied } = {}) {
    const { command } = editor;
    const trigger = button({ iconHtml: iconMarkup('pageSetup'), title: 'Page setup' });

    trigger.addEventListener('click', () => openPageSetupDialog(command, pageOptions, onApplied));

    return { element: group(trigger), update: () => {} };
}

function openPageSetupDialog(command, pageOptions, onApplied) {
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

            // header.top / footer.bottom (see readInitialPageOptions) are offsets from the
            // physical page edge to where header/footer content itself starts — not a
            // height. The actual reserved band is the page's top/bottom Margin above: a
            // *larger* offset here leaves *less* room before that margin, in the editor and
            // in the generated PDF alike (mirrors canvas-editor's own header.top/
            // footer.bottom semantics — see SubmissionPdfComposer::resolveGeometry).
            const headerHeight = numberInput(options.header?.top ?? 30, { min: 0 });
            const footerHeight = numberInput(options.footer?.bottom ?? 30, { min: 0 });
            const headerFooterRow = document.createElement('div');
            headerFooterRow.className = 'grid grid-cols-2 gap-3';
            headerFooterRow.append(
                labeledField('Header offset from top edge (px)', headerHeight),
                labeledField('Footer offset from bottom edge (px)', footerHeight),
            );
            form.appendChild(headerFooterRow);
            const headerFooterHint = document.createElement('p');
            headerFooterHint.className = 'text-xs text-slate-500';
            headerFooterHint.textContent = 'How far the header/footer text starts from the page edge — keep this smaller than the Margins above, which is where the header/footer content is reserved room to end.';
            form.appendChild(headerFooterHint);

            // The boundary between "header offset + its own content" and "where body text
            // starts" isn't flush against the Margin above — there's deliberately a little
            // blank space left so a header's last line of text and the body's first line
            // don't crowd each other. That space is this field, independent of the offset
            // above it.
            const headerGap = numberInput(options.headerGap ?? DEFAULT_HEADER_FOOTER_GAP, { min: 0 });
            const footerGap = numberInput(options.footerGap ?? DEFAULT_HEADER_FOOTER_GAP, { min: 0 });
            const headerFooterGapRow = document.createElement('div');
            headerFooterGapRow.className = 'grid grid-cols-2 gap-3';
            headerFooterGapRow.append(
                labeledField('Space between header and body (px)', headerGap),
                labeledField('Space between body and footer (px)', footerGap),
            );
            form.appendChild(headerFooterGapRow);

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
            apply.className = 'rounded-lg bg-cherry-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-cherry-800';
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
                // executeUpdateOptions replaces the whole `header`/`footer` key rather than
                // merging into it (verified against its implementation), so every other
                // header/footer property (maxHeightRadio, disabled, …) has to be carried
                // forward explicitly here or Apply would silently reset it.
                const updatedHeader = { ...pageOptions.header, top: Number(headerHeight.value) };
                const updatedFooter = { ...pageOptions.footer, bottom: Number(footerHeight.value) };
                const updatedHeaderGap = Number(headerGap.value);
                const updatedFooterGap = Number(footerGap.value);

                command.executePaperSize(width, height);
                command.executePaperDirection(paperDirection);
                command.executeSetPaperMargin(margin);
                command.executeUpdateOptions({
                    background: updatedBackground, pageNumber: updatedPageNumber, header: updatedHeader, footer: updatedFooter,
                });
                command.executeSetColumns(updatedColumns);
                command.executeFocus();

                // Record what was actually applied — see readInitialPageOptions() for why
                // this isn't read back from the editor afterward. headerGap/footerGap have
                // no executeUpdateOptions() equivalent at all (see readInitialPageOptions) —
                // they only ever live here and in what gets persisted.
                Object.assign(pageOptions, {
                    width, height, margins: margin, paperDirection, background: updatedBackground, pageNumber: updatedPageNumber, columns: updatedColumns,
                    header: updatedHeader, footer: updatedFooter, headerGap: updatedHeaderGap, footerGap: updatedFooterGap,
                });
                // Page size/orientation changed, so the width the toolbar fit itself to on
                // load is now stale — let the caller re-fit against the new page width.
                onApplied?.();

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
    const zoomLabel = document.createElement('span');
    zoomLabel.className = 'w-12 text-center text-xs text-slate-600';
    zoomLabel.textContent = '100%';
    // Shared by the +/- buttons, the dblclick-to-reset, and the initial fit-to-container
    // pass (see buildToolbar's fitToWidth) — canvas-editor's own scale getter doesn't
    // reflect executePageScale changes (same staleness as getOptions(), see
    // readInitialPageOptions), so this local value is the only reliable record of it.
    function applyZoom(nextPercent) {
        zoom = Math.max(50, Math.min(200, Math.round(nextPercent)));
        zoomLabel.textContent = `${zoom}%`;
        command.executePageScale(zoom / 100);
    }
    const zoomOut = button({ iconHtml: '&minus;', title: 'Zoom out', onClick: () => applyZoom(zoom - 10) });
    const zoomIn = button({ iconHtml: '+', title: 'Zoom in', onClick: () => applyZoom(zoom + 10) });
    zoomLabel.addEventListener('dblclick', () => applyZoom(100));

    const fullscreen = button({
        iconHtml: iconMarkup('fullscreen'),
        title: 'Fullscreen (application-level — not a canvas-editor feature)',
        onClick: () => toggleFullscreen(command.getContainer().closest('[data-canvas-editor]') ?? command.getContainer()),
    });

    return {
        element: group(findTrigger, zoomOut, zoomLabel, zoomIn, fullscreen, wordCount),
        update: () => {},
        onContentChange: refreshWordCount,
        setZoomPercent: applyZoom,
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

/**
 * `includeTemplateTools` gates the handful of controls that only make sense for a
 * full standalone document (admin template management): page size/margins/orientation
 * and header/footer zone switching. A researcher's chapter is a content fragment that
 * gets composed into the final manuscript by SubmissionHtmlTemplateRenderer — it has no
 * page geometry or header/footer of its own to set, so those controls would just be
 * dead UI. Every other group is the same formatting toolset a research submission
 * actually uses, so it stays fully enabled rather than guessing at what to gray out.
 */
export function buildToolbar(editor, toolbarEl, { imageUploadUrl, onPageOptionsApplied, savedPageOptions, includeTemplateTools = true } = {}) {
    const { command } = editor;
    const state = createToolbarState(editor);
    const pageOptions = readInitialPageOptions(command, savedPageOptions);
    const toolsGroup = buildToolsGroup(editor);

    const groups = [
        buildHistoryGroup(command),
        buildClipboardGroup(command),
        buildFontGroup(editor),
        buildTextFormattingGroup(editor),
        buildParagraphGroup(editor),
        buildListsGroup(command),
        buildInsertGroup(editor, { imageUploadUrl, includeTemplateTools }),
        buildImageLayoutGroup(editor),
        ...(includeTemplateTools ? [buildPageLayoutGroup(editor, pageOptions, {
            onApplied: () => onPageOptionsApplied?.(),
        })] : []),
        toolsGroup,
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

    return {
        getPageOptions: () => ({ ...pageOptions }),
        // Shrinks the page to fit inside `containerWidth` (never grows past 100%), so the
        // editor opens without forcing horizontal scroll/clipping on narrower viewports.
        // Pixel-width-based, not viewport-based, so it stays correct regardless of what's
        // around the editor (sidebar, padding, etc.) — the caller just measures its mount.
        fitToWidth: (containerWidth) => {
            if (!containerWidth || !pageOptions.width) {
                return;
            }
            const fitPercent = Math.floor(((containerWidth - 4) / pageOptions.width) * 100);
            toolsGroup.setZoomPercent(Math.min(100, fitPercent));
        },
    };
}

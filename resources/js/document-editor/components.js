// Reusable, dependency-free UI primitives shared by every toolbar group: a positioned
// popover (menus, color picker, table grid picker, find & replace), and a centered modal
// (page setup). Both are dismissible (outside click + Escape) and restore focus to their
// trigger on close so keyboard users never lose their place.

let openPopover = null;

function closeOpenPopover() {
    if (openPopover) {
        openPopover.close();
    }
}

/**
 * A trigger button that opens a positioned panel. `panelBuilder(close)` is called fresh
 * every time it opens and must return the panel's content element.
 */
export function createPopover({ trigger, panelBuilder, align = 'start', className = '' }) {
    trigger.setAttribute('aria-haspopup', 'true');
    trigger.setAttribute('aria-expanded', 'false');

    let panel = null;

    function position() {
        // The panel is `position: fixed`, so its coordinates are already viewport-relative
        // — adding window.scrollX/Y here would double-count scroll and push it off-screen.
        const rect = trigger.getBoundingClientRect();
        const top = Math.min(rect.bottom + 4, window.innerHeight - panel.offsetHeight - 8);
        const desiredLeft = align === 'end' ? rect.right - panel.offsetWidth : rect.left;
        const left = Math.max(8, Math.min(desiredLeft, window.innerWidth - panel.offsetWidth - 8));

        panel.style.top = `${Math.max(8, top)}px`;
        panel.style.left = `${left}px`;
    }

    function close() {
        if (!panel) {
            return;
        }
        panel.remove();
        panel = null;
        trigger.setAttribute('aria-expanded', 'false');
        document.removeEventListener('mousedown', onOutsideClick, true);
        document.removeEventListener('keydown', onKeydown, true);
        if (openPopover === api) {
            openPopover = null;
        }
    }

    function onOutsideClick(event) {
        if (panel && !panel.contains(event.target) && event.target !== trigger && !trigger.contains(event.target)) {
            close();
        }
    }

    function onKeydown(event) {
        if (event.key === 'Escape') {
            close();
            trigger.focus();
        }
    }

    function open() {
        closeOpenPopover();

        panel = document.createElement('div');
        panel.className = `fixed z-50 rounded-xl bg-white p-2 shadow-lg ring-1 ring-slate-200 ${className}`;
        panel.appendChild(panelBuilder(close));
        document.body.appendChild(panel);
        position();

        trigger.setAttribute('aria-expanded', 'true');
        document.addEventListener('mousedown', onOutsideClick, true);
        document.addEventListener('keydown', onKeydown, true);
    }

    function toggle() {
        if (panel) {
            close();
        } else {
            open();
        }
    }

    const api = { open, close, toggle, isOpen: () => panel !== null };

    trigger.addEventListener('mousedown', (event) => event.preventDefault());
    trigger.addEventListener('click', () => {
        toggle();
        if (panel) {
            openPopover = api;
        }
    });

    return api;
}

export function menuItem({ label, iconHtml = '', shortcut = '', onSelect, disabled = false }) {
    const button = document.createElement('button');
    button.type = 'button';
    button.role = 'menuitem';
    button.disabled = disabled;
    button.className = 'flex w-full items-center gap-2 rounded-lg px-3 py-1.5 text-left text-sm text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40';
    button.innerHTML = `<span class="flex h-4 w-4 items-center justify-center text-slate-500">${iconHtml}</span><span class="flex-1">${label}</span>${shortcut ? `<span class="text-xs text-slate-400">${shortcut}</span>` : ''}`;

    button.addEventListener('mousedown', (event) => event.preventDefault());
    button.addEventListener('click', () => onSelect());

    return button;
}

export function menuPanel(items) {
    const wrapper = document.createElement('div');
    wrapper.role = 'menu';
    wrapper.className = 'flex min-w-[180px] flex-col';
    items.forEach((item) => wrapper.appendChild(item));
    return wrapper;
}

const PALETTE = [
    '#000000', '#434343', '#666666', '#999999', '#b7b7b7', '#d9d9d9', '#efefef', '#ffffff',
    '#980000', '#ff0000', '#ff9900', '#ffff00', '#00ff00', '#00ffff', '#4a86e8', '#0000ff', '#9900ff', '#ff00ff',
    '#e6b8af', '#f4cccc', '#fce5cd', '#fff2cc', '#d9ead3', '#d0e0e3', '#c9daf8', '#cfe2f3', '#d9d2e9', '#ead1dc',
];

/**
 * Text-color/highlight style picker: a swatch grid plus a native <input type=color> for
 * anything not in the palette, and a "reset" entry that clears the style
 * (command.executeColor/executeHighlight both accept null to clear).
 */
export function colorPickerPanel({ onPick, onClear, clearLabel }) {
    const wrapper = document.createElement('div');
    wrapper.className = 'w-56';

    const clearButton = menuItem({ label: clearLabel, onSelect: onClear });
    wrapper.appendChild(clearButton);

    const grid = document.createElement('div');
    grid.className = 'mt-1 grid grid-cols-8 gap-1 px-1';
    PALETTE.forEach((hex) => {
        const swatch = document.createElement('button');
        swatch.type = 'button';
        swatch.setAttribute('aria-label', hex);
        swatch.className = 'h-5 w-5 rounded ring-1 ring-inset ring-black/10';
        swatch.style.backgroundColor = hex;
        swatch.addEventListener('mousedown', (event) => event.preventDefault());
        swatch.addEventListener('click', () => onPick(hex));
        grid.appendChild(swatch);
    });
    wrapper.appendChild(grid);

    const customRow = document.createElement('label');
    customRow.className = 'mt-2 flex items-center gap-2 px-2 text-xs text-slate-600';
    const customInput = document.createElement('input');
    customInput.type = 'color';
    customInput.className = 'h-6 w-6 rounded border border-slate-300';
    customInput.addEventListener('mousedown', (event) => event.stopPropagation());
    customInput.addEventListener('input', () => onPick(customInput.value));
    customRow.appendChild(customInput);
    customRow.appendChild(document.createTextNode('Custom colour'));
    wrapper.appendChild(customRow);

    return wrapper;
}

/**
 * Google-Docs-style hover grid for inserting a table: highlights the row/col rectangle
 * under the pointer and labels it "R x C"; click inserts at that size.
 */
export function tableGridPickerPanel({ maxRows = 8, maxCols = 10, onSelect }) {
    const wrapper = document.createElement('div');
    wrapper.className = 'w-max';

    const label = document.createElement('div');
    label.className = 'mb-1 text-center text-xs text-slate-500';
    label.textContent = 'Insert table';
    wrapper.appendChild(label);

    const grid = document.createElement('div');
    grid.style.display = 'grid';
    grid.style.gridTemplateColumns = `repeat(${maxCols}, 18px)`;
    grid.style.gridTemplateRows = `repeat(${maxRows}, 18px)`;
    grid.style.gap = '2px';

    const cells = [];
    for (let r = 1; r <= maxRows; r += 1) {
        for (let c = 1; c <= maxCols; c += 1) {
            const cell = document.createElement('div');
            cell.className = 'rounded-sm border border-slate-300 bg-white';
            cell.dataset.row = String(r);
            cell.dataset.col = String(c);
            cell.addEventListener('mouseenter', () => highlight(r, c));
            cell.addEventListener('mousedown', (event) => event.preventDefault());
            cell.addEventListener('click', () => onSelect(r, c));
            grid.appendChild(cell);
            cells.push(cell);
        }
    }

    function highlight(rows, cols) {
        cells.forEach((cell) => {
            const active = Number(cell.dataset.row) <= rows && Number(cell.dataset.col) <= cols;
            cell.classList.toggle('bg-red-600', active);
            cell.classList.toggle('border-red-700', active);
            cell.classList.toggle('bg-white', !active);
        });
        label.textContent = `${rows} x ${cols}`;
    }

    wrapper.appendChild(grid);
    return wrapper;
}

/**
 * Centered modal with a backdrop, used for the Page Setup dialog. `bodyBuilder(close)`
 * builds the content; the caller is responsible for its own form controls/save button.
 */
export function openModal({ title, bodyBuilder }) {
    closeOpenPopover();

    const backdrop = document.createElement('div');
    backdrop.className = 'fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4';

    const dialog = document.createElement('div');
    dialog.role = 'dialog';
    dialog.setAttribute('aria-modal', 'true');
    dialog.setAttribute('aria-label', title);
    dialog.className = 'max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-5 shadow-xl';

    function close() {
        backdrop.remove();
        document.removeEventListener('keydown', onKeydown, true);
    }

    function onKeydown(event) {
        if (event.key === 'Escape') {
            close();
        }
    }

    const header = document.createElement('div');
    header.className = 'mb-4 flex items-center justify-between';
    const heading = document.createElement('h3');
    heading.className = 'text-sm font-semibold text-slate-900';
    heading.textContent = title;
    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.setAttribute('aria-label', 'Close');
    closeButton.className = 'rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600';
    closeButton.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg>';
    closeButton.addEventListener('click', close);
    header.append(heading, closeButton);
    dialog.appendChild(header);
    dialog.appendChild(bodyBuilder(close));

    backdrop.addEventListener('mousedown', (event) => {
        if (event.target === backdrop) {
            close();
        }
    });
    document.addEventListener('keydown', onKeydown, true);

    backdrop.appendChild(dialog);
    document.body.appendChild(backdrop);

    return { close };
}

export function labeledField(labelText, inputEl) {
    const wrapper = document.createElement('label');
    wrapper.className = 'flex flex-col gap-1 text-xs font-medium text-slate-600';
    const span = document.createElement('span');
    span.textContent = labelText;
    wrapper.append(span, inputEl);
    return wrapper;
}

export function numberInput(value, { min, max, step = 1 } = {}) {
    const input = document.createElement('input');
    input.type = 'number';
    input.value = String(value ?? '');
    if (min !== undefined) input.min = String(min);
    if (max !== undefined) input.max = String(max);
    input.step = String(step);
    input.className = 'rounded-md border border-slate-300 px-2 py-1 text-sm';
    return input;
}

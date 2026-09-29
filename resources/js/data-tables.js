let sequence = 0;
const arrow = '<svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>';
const sortIcon = '<svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 4v16m-4-4 4 4 4-4M16 20V4m-4 4 4-4 4 4"/></svg>';

function relocateFilters() {
    document.querySelectorAll('form[data-table-search]').forEach(form => {
        const region = form.closest('.research-filter');
        const table = [...(region?.parentElement.querySelectorAll('table') ?? [])]
            .find(t => region.compareDocumentPosition(t) & Node.DOCUMENT_POSITION_FOLLOWING);
        if (!table?.tHead || table.dataset.filtersReady) return;
        table.dataset.filtersReady = "true";
        form.id ||= `table-search-${++sequence}`;
        const headers = [...table.tHead.rows[0].cells];
        const aliases = {research_type: 'type', classification: 'type', reviewer: 'reviewer', action: 'activity', date: 'date', sort: 'date', type: 'type'};
        form.querySelectorAll('select, input[type="date"]').forEach(source => {
            const input = source.cloneNode(true);
            input.value = source.value;
            input.hidden = false;
            const name = source.name;
            const key = aliases[name] || name.replaceAll('_', ' ');
            const header = (name === 'sort' ? headers.find(h => /submitted|created|date|updated|deleted/i.test(h.dataset.columnLabel || h.textContent)) : headers.find(h => h.textContent.toLowerCase().includes(key))) || headers[0];
            const wrapper = source.parentElement;
            source.hidden = true;
            if (name === 'sort' && header.querySelector('a[href*="sort="]')) {
                if (wrapper !== form) wrapper.hidden = true;
                return;
            }
            input.removeAttribute('name');
            input.setAttribute('aria-label', `Filter ${name.replaceAll('_', ' ')}`);
            input.className = 'table-filter-input';
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'table-header-control';
            button.innerHTML = arrow;
            button.title = `${name === 'sort' ? 'Sort' : 'Filter'} ${name.replaceAll('_', ' ')}`;
            button.setAttribute('aria-label', button.title);
            button.setAttribute('aria-expanded', 'false');
            if (input.value && input.value !== 'desc' && input.value !== 'newest') button.dataset.active = 'true';
            const panel = document.createElement('div');
            panel.id = `table-filter-${++sequence}`;
            panel.className = 'table-filter-popover';
            panel.setAttribute('popover', 'auto');
            const title = document.createElement('strong');
            title.textContent = button.title;
            panel.append(title);
            if (source.tagName === 'SELECT') {
                for (const option of source.options) {
                    const choice = document.createElement('button');
                    choice.type = 'button';
                    choice.className = 'table-filter-choice';
                    choice.textContent = option.textContent;
                    choice.disabled = option.disabled;
                    choice.setAttribute('aria-pressed', String(option.value === source.value));
                    choice.addEventListener('click', () => { source.value = option.value; form.requestSubmit(); });
                    panel.append(choice);
                }
            } else panel.append(input);
            button.setAttribute('popovertarget', panel.id);
            header.append(button, panel);
            panel.addEventListener('beforetoggle', event => {
                button.setAttribute('aria-expanded', String(event.newState === 'open'));
                if (event.newState === 'open') {
                    const rect = button.getBoundingClientRect();
                    panel.style.left = `${Math.max(8, Math.min(rect.left, innerWidth - 280))}px`;
                    panel.style.top = `${Math.max(8, Math.min(rect.bottom + 6, innerHeight - 280))}px`;
                }
            });
            input.addEventListener('change', () => { source.value = input.value; form.requestSubmit(); });
            if (wrapper !== form && !wrapper.querySelector('input[type="text"], input[type="search"], button')) wrapper.hidden = true;
        });
    });
}

let detailsDialog;
function showDetails(cell) {
    if (!detailsDialog) {
        detailsDialog = document.createElement('dialog');
        detailsDialog.className = 'table-details-dialog';
        detailsDialog.setAttribute('aria-label', 'Record details');
        document.body.append(detailsDialog);
    }
    const heading = document.createElement('h2');
    heading.textContent = 'Record details';
    const close = document.createElement('button');
    close.type = 'button';
    close.textContent = 'Close';
    close.addEventListener('click', () => detailsDialog.close());
    const fields = document.createElement('dl');
    const headers = [...(cell.closest('table').tHead?.rows[0]?.cells ?? [])];
    [...cell.parentElement.cells].forEach((item, index) => {
        if (item.querySelector('form, input, textarea, select') || item.classList.contains('table-actions')) return;
        const term = document.createElement('dt');
        term.textContent = headers[index]?.dataset.columnLabel || `Column ${index + 1}`;
        const description = document.createElement('dd');
        description.textContent = item.querySelector('.table-cell-content')?.textContent.trim() || item.textContent.trim();
        fields.append(term, description);
    });
    detailsDialog.replaceChildren(heading, fields, close);
    detailsDialog.showModal();
}

const observed = new WeakSet();
const cellObserver = new ResizeObserver(entries => entries.forEach(({target}) => {
    const more = target.parentElement?.querySelector('.table-more');
    if (more) more.hidden = target.scrollWidth <= target.clientWidth + 1;
}));

function enhanceTables() {
    document.querySelectorAll('table').forEach(table => {
        if (!table.tHead || table.closest('.sr-only, [contenteditable="true"], .canvas-editor')) return;
        table.classList.add('app-data-table');
        const headers = [...table.tHead.rows[0].cells];
        headers.forEach(header => { header.dataset.columnLabel ||= header.textContent.trim(); });
        [...table.tBodies].forEach(body => [...body.rows].forEach(row => {
            [...row.cells].forEach((cell, index) => {
                if (cell.dataset.tableReady || cell.colSpan > 1) return;
                cell.dataset.tableReady = 'true';
                const label = headers[index]?.dataset.columnLabel.toLowerCase() || '';
                if (/title|name|reviewer|subject|office|school|researcher/.test(label)) cell.classList.add('table-record-name');
                if (cell.querySelector('input, textarea, select, [contenteditable]')) return;
                const actions = [...cell.querySelectorAll('button, a')].filter(el => el.querySelector('svg'));
                for (const action of actions) {
                    const text = action.textContent.trim();
                    if (!text || action.dataset.tableIcon) continue;
                    action.dataset.tableIcon = 'true';
                    action.setAttribute('aria-label', text);
                    action.title = text;
                    action.classList.add('table-icon-action');
                    for (const node of [...action.childNodes]) {
                        if (node.nodeType === Node.TEXT_NODE && node.textContent.trim()) {
                            const span = document.createElement('span');
                            span.className = 'sr-only';
                            span.textContent = node.textContent;
                            node.replaceWith(span);
                        } else if (node.nodeType === Node.ELEMENT_NODE && node.tagName.toLowerCase() !== 'svg') node.classList.add('sr-only');
                    }
                }
                if (cell.querySelector('button, form') || (actions.length && !/title|name|subject/.test(label))) {
                    cell.classList.add('table-actions');
                    return;
                }
                const content = document.createElement('div');
                content.className = 'table-cell-content';
                if (/status|stage/.test(label) && !cell.querySelector('.research-status')) {
                    const state = cell.textContent.trim().toLowerCase();
                    const kind = /overdue|disabled|rejected|failed/.test(state) ? 'urgent' : /revision|pending|open|not started/.test(state) ? 'attention' : /approved|complete|resolved|active/.test(state) && !/inactive/.test(state) ? 'complete' : /progress|review|submitted/.test(state) ? 'progress' : 'neutral';
                    content.classList.add('table-status-chip', 'table-status-' + kind);
                }
                content.append(...cell.childNodes);
                const box = document.createElement('div');
                box.className = 'table-cell-box';
                box.append(content);
                cell.append(box);
                const more = document.createElement('button');
                more.type = 'button';
                more.className = 'table-more';
                more.textContent = 'More';
                more.hidden = true;
                more.setAttribute('aria-label', `View full ${label || 'record'} details`);
                more.addEventListener('click', () => showDetails(cell));
                box.append(more);
                if (!observed.has(content)) { cellObserver.observe(content); observed.add(content); }
            });
        }));
    });
    relocateFilters();
    document.querySelectorAll('.app-data-table').forEach(table => {
        if (table.querySelector('tbody input, tbody textarea, tbody select')) return;
        const headers = [...table.tHead.rows[0].cells];
        headers.forEach((header, index) => {
            if (header.dataset.sortReady || !header.dataset.columnLabel || /actions/i.test(header.dataset.columnLabel) || header.querySelector('a, button')) return;
            header.dataset.sortReady = 'true';
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'table-header-control';
            button.innerHTML = sortIcon;
            button.title = `Sort ${header.dataset.columnLabel} (visible rows)`;
            button.setAttribute('aria-label', button.title);
            button.addEventListener('click', () => {
                const direction = header.getAttribute('aria-sort') === 'ascending' ? -1 : 1;
                headers.forEach(h => h.removeAttribute('aria-sort'));
                header.setAttribute('aria-sort', direction === 1 ? 'ascending' : 'descending');
                [...table.tBodies].forEach(body => {
                    const rows = [...body.rows];
                    if (rows.some(r => r.cells.length !== headers.length)) return;
                    const value = cell => cell.querySelector('.table-cell-content')?.textContent.trim() || cell.textContent.trim();
                    rows.sort((a,b) => {
                        const left = value(a.cells[index]), right = value(b.cells[index]);
                        if (/date|submitted|updated|created|deleted/i.test(header.dataset.columnLabel)) {
                            const l = Date.parse(left), r = Date.parse(right);
                            if (Number.isFinite(l) && Number.isFinite(r)) return (l - r) * direction;
                        }
                        return left.localeCompare(right, undefined, {numeric:true}) * direction;
                    });
                    body.append(...rows);
                });
            });
            header.append(button);
        });
    });
}

let scheduled = false;
new MutationObserver(records => {
    if (!records.some(record => [...record.addedNodes].some(node => node.nodeType === 1 && (node.matches?.('table, tbody, tr, .research-filter') || node.querySelector?.('table'))))) return;
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(() => { scheduled = false; enhanceTables(); });
}).observe(document.body, {childList:true, subtree:true});
enhanceTables();

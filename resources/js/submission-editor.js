import Editor, { TextDecorationStyle } from '@hufe921/canvas-editor';
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
// Same submission, sibling route — derived rather than a second data attribute since the
// two always travel together (both only exist on an editable draft's own form).
let grammarCheckUrl = null;

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

// The "still needs content" red dot/ring (see section-editor.blade.php) is server-rendered
// once from SubmissionSectionService::missingRequiredSections() at page load, then never
// revisited — so a chapter that already had autosaved content since the page loaded kept
// showing "still needs content" until a full reload. Autosave already tells us, on every
// successful save, exactly what this chapter's content now is; updateMissingIndicator()
// just reflects that back onto the same three places the initial page render used, so it
// remains accurate for the rest of the session without a reload.
function updateMissingIndicator(sectionKey, isMissing) {
    if (! sectionKey) {
        return;
    }

    const tab = document.querySelector(`[data-wizard-chapter][data-section-key="${sectionKey}"]`);
    tab?.querySelector('[data-missing-dot]')?.toggleAttribute('hidden', ! isMissing);

    const panel = document.querySelector(`[data-chapter-panel][data-section-key="${sectionKey}"]`);
    panel?.classList.toggle('ring-2', isMissing);
    panel?.classList.toggle('ring-rose-300', isMissing);
    panel?.querySelector('[data-missing-message]')?.toggleAttribute('hidden', ! isMissing);
}

// Mirrors SubmissionSectionService::missingRequiredSections()'s rich_text check:
// trim(strip_tags($section->content_html)) === ''.
function isRichTextEmpty(html) {
    const container = document.createElement('div');
    container.innerHTML = html || '';

    return container.textContent.trim() === '';
}

// Mirrors saveSection()'s table row filter: a row only survives if at least one of its
// cells is non-blank, and the section counts as missing once no row survives that filter.
function isTableRowsEmpty(rows) {
    return ! rows.some((row) => Object.values(row).some((cell) => String(cell ?? '').trim() !== ''));
}

async function autosaveSection(sectionKey, value, isMissing) {
    if (! autosaveUrl) {
        return;
    }

    setAutosaveStatus('saving');

    try {
        await window.axios.patch(autosaveUrl, { section: sectionKey, value });
        setAutosaveStatus('saved');
        updateMissingIndicator(sectionKey, isMissing);
    } catch (error) {
        console.error('Autosave failed', error);
        setAutosaveStatus('error');
    }
}

// --- Live grammar checker (Google Docs-style wavy underline), driven by the same
// self-hosted LanguageTool instance SRAM already uses (see GrammarCheckService) — this
// just calls it far more often (debounced on every pause in typing) and applies the
// results as inline decorations instead of an aggregate score.
//
// canvas-editor has no public "add a decoration without touching the selection" API —
// the only way to mark a range is executeSetRange() + executeUnderline(), which moves the
// active selection and toggles (calling it twice on an already-fully-wavy range turns it
// OFF, it doesn't just no-op). Both are handled by: (1) always re-deriving "what's
// currently marked" by scanning the *live* document for existing wavy-underline elements
// right before applying, rather than trusting indices remembered from an earlier round —
// typing between rounds shifts every later element's index, so a remembered index can
// point at the wrong character entirely by the time it's reused; and (2) saving the caret
// position before touching any range and restoring it as the very last step — since every
// intermediate step runs synchronously (no `await` in between), the browser never actually
// paints an intermediate frame, so the user never sees their cursor move.
const GRAMMAR_CHECK_DEBOUNCE_MS = 2000;

// One entry per live editor instance: { indexToMatch: Map<elementIndex, match> } — just
// enough to resolve a click into a suggestion popover; matching/clearing spans is always
// re-derived fresh from the document itself (see applyGrammarRanges/runGrammarCheck).
const grammarState = new Map();

/**
 * Plain-text extraction for LanguageTool, paired with a map back from each character's
 * offset in that string to its index in the *same* elementList array executeSetRange()
 * addresses. Deliberately narrower than canvas-editor's own getTextFromElementList(): it
 * only walks plain text runs (skips tables/titles/hyperlinks/images entirely, rather than
 * trying to keep offsets aligned across their nested valueLists) — chapter prose is
 * overwhelmingly plain paragraphs, and a heading or table cell simply isn't grammar-
 * checked rather than risking a misaligned mark somewhere else in the chapter.
 */
function extractCheckableText(elementList) {
    let text = '';
    const indexMap = [];

    elementList.forEach((element, index) => {
        if (element.type && element.type !== 'text') {
            return;
        }

        const value = element.value ?? '';

        for (let i = 0; i < value.length; i++) {
            text += value[i];
            indexMap.push(index);
        }
    });

    return { text, indexMap };
}

// Collapses a sorted list of individual elementList indices into contiguous {start,end}
// spans, so a multi-character flagged phrase becomes one executeSetRange() call instead
// of one per character.
function mergeIndicesIntoRanges(sortedIndices) {
    const ranges = [];
    let start = null;
    let prev = null;

    sortedIndices.forEach((index) => {
        if (start === null) {
            start = index;
        } else if (index !== prev + 1) {
            ranges.push({ start, end: prev });
            start = index;
        }

        prev = index;
    });

    if (start !== null) {
        ranges.push({ start, end: prev });
    }

    return ranges;
}

// executeSetRange(startIndex, endIndex) selects elementList[startIndex + 1 .. endIndex]
// inclusive (verified against canvas-editor's own Range.getSelection() implementation) —
// i.e. startIndex is "the position just before the first selected element", not the first
// selected element's own index.
function toCanvasRange({ start, end }) {
    return { startIndex: start - 1, endIndex: end };
}

// Re-derives "what's currently marked" straight from the live elementList rather than
// trusting a remembered range from an earlier round — nothing else in this app sets a wavy
// underline, so this is an unambiguous, always-current source of truth immune to index
// drift from edits made since the last check.
function findExistingGrammarRanges(elementList) {
    const markedIndices = [];

    elementList.forEach((element, index) => {
        if (element.underline && element.textDecoration?.style === TextDecorationStyle.WAVY) {
            markedIndices.push(index);
        }
    });

    return mergeIndicesIntoRanges(markedIndices);
}

function applyGrammarRanges(editor, newRanges, previousRanges) {
    const savedRange = editor.command.getRange();

    // Clear every previously-marked range first: since underline() toggles based on
    // whether the *entire* target range is already uniformly wavy, marking is only safe
    // to call once per range per state change — clear-then-reapply avoids ever calling it
    // twice on the same still-flagged span, which would silently turn it back off.
    previousRanges.forEach((range) => {
        const { startIndex, endIndex } = toCanvasRange(range);
        editor.command.executeSetRange(startIndex, endIndex);
        editor.command.executeUnderline({ style: TextDecorationStyle.WAVY });
    });

    newRanges.forEach((range) => {
        const { startIndex, endIndex } = toCanvasRange(range);
        editor.command.executeSetRange(startIndex, endIndex);
        editor.command.executeUnderline({ style: TextDecorationStyle.WAVY });
    });

    editor.command.executeSetRange(savedRange.startIndex, savedRange.endIndex);
}

async function runGrammarCheck(editor) {
    if (! grammarCheckUrl) {
        return;
    }

    const { data } = editor.command.getValue();
    const { text, indexMap } = extractCheckableText(data.main || []);

    if (! text.trim()) {
        applyGrammarRanges(editor, [], findExistingGrammarRanges(data.main || []));
        grammarState.set(editor, { indexToMatch: new Map() });
        return;
    }

    let matches;
    try {
        const response = await window.axios.post(grammarCheckUrl, { text });
        matches = response.data.matches || [];
    } catch (error) {
        console.error('Live grammar check failed', error);
        return;
    }

    // The document may well have changed while that request was in flight (typing resumed
    // before the response arrived) — match offsets are only valid against the exact text
    // they were computed from, so applying them against different, current text would mark
    // arbitrary, wrong characters. Bail out silently: the edit that invalidated this round
    // already triggered its own debounced contentChange, which will re-check once the user
    // pauses again.
    const current = editor.command.getValue();
    const currentExtraction = extractCheckableText(current.data.main || []);
    if (currentExtraction.text !== text) {
        return;
    }

    const indexToMatch = new Map();
    matches.forEach((match) => {
        for (let offset = match.offset; offset < match.offset + match.length; offset++) {
            const elementIndex = indexMap[offset];
            if (elementIndex !== undefined) {
                indexToMatch.set(elementIndex, match);
            }
        }
    });

    const newRanges = mergeIndicesIntoRanges(Array.from(indexToMatch.keys()).sort((a, b) => a - b));

    applyGrammarRanges(editor, newRanges, findExistingGrammarRanges(current.data.main || []));
    grammarState.set(editor, { indexToMatch });
}

function closeGrammarPopover() {
    document.querySelector('[data-grammar-popover]')?.remove();
}

function grammarPopoverMarkup(match) {
    const replacements = (match.replacements || []).slice(0, 3);

    return `
        <div data-grammar-popover class="fixed z-50 w-72 rounded-xl bg-white p-3 text-sm shadow-lg ring-1 ring-slate-200">
            <p class="text-xs font-semibold uppercase tracking-wide text-rose-600">${escapeHtml(match.shortMessage || 'Possible issue')}</p>
            <p class="mt-1 text-slate-700">${escapeHtml(match.message || '')}</p>
            ${replacements.length ? `
                <div class="mt-2 flex flex-wrap gap-1.5">
                    ${replacements.map((r) => `<button type="button" data-grammar-apply="${escapeHtml(r.value)}" class="rounded-lg bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-100">${escapeHtml(r.value)}</button>`).join('')}
                </div>
            ` : ''}
            <button type="button" data-grammar-dismiss class="mt-2 text-xs font-medium text-slate-400 hover:text-slate-600">Dismiss</button>
        </div>
    `;
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';

    return div.innerHTML;
}

// A flagged word is only ever a canvas-editor decoration, not a real DOM element — there's
// nothing to attach a hover/click listener to directly, so this reads the *result* of
// canvas-editor's own click handling instead: after a click lands, its normal cursor-
// placement logic has already run, so the resulting (collapsed) range's position tells us
// which element the user clicked next to, which we can then check against this chapter's
// currently-flagged indices.
function wireGrammarPopover(editor, mount) {
    mount.addEventListener('click', (event) => {
        closeGrammarPopover();

        const state = grammarState.get(editor);
        if (! state || ! state.indexToMatch.size) {
            return;
        }

        const { endIndex } = editor.command.getRange();
        const match = state.indexToMatch.get(endIndex) ?? state.indexToMatch.get(endIndex + 1);

        if (! match) {
            return;
        }

        const popover = document.createElement('div');
        popover.innerHTML = grammarPopoverMarkup(match);
        document.body.appendChild(popover.firstElementChild);

        const el = document.querySelector('[data-grammar-popover]');
        const maxLeft = window.innerWidth - el.offsetWidth - 12;
        el.style.left = `${Math.max(12, Math.min(event.clientX, maxLeft))}px`;
        el.style.top = `${Math.min(event.clientY + 12, window.innerHeight - el.offsetHeight - 12)}px`;

        el.querySelector('[data-grammar-dismiss]')?.addEventListener('click', closeGrammarPopover);
        el.querySelectorAll('[data-grammar-apply]').forEach((button) => {
            button.addEventListener('click', () => {
                // Re-derived fresh at the moment of applying, not reused from whenever the
                // popover opened — the currently-marked ranges are the only reliable source
                // of the flagged span's real (possibly since-shifted) indices.
                const { data } = editor.command.getValue();
                const liveRanges = findExistingGrammarRanges(data.main || []);
                const range = liveRanges.find(({ start, end }) => endIndex >= start - 1 && endIndex <= end)
                    ?? liveRanges.find(({ start, end }) => endIndex + 1 >= start - 1 && endIndex + 1 <= end);

                if (range) {
                    const { startIndex, endIndex: rangeEndIndex } = toCanvasRange(range);
                    editor.command.executeSetRange(startIndex, rangeEndIndex);
                    editor.command.executeInsertElementList([{ value: button.dataset.grammarApply }]);
                }

                closeGrammarPopover();
            });
        });
    });

    document.addEventListener('click', (event) => {
        if (! event.target.closest('[data-grammar-popover]') && ! mount.contains(event.target)) {
            closeGrammarPopover();
        }
    });
}

function wireSectionAutosave(editor, sectionKey, mount) {
    if (mount) {
        wireGrammarPopover(editor, mount);
    }

    if (! sectionKey || ! autosaveUrl) {
        return;
    }

    editor.listener.contentChange = debounce(async () => {
        const { data } = editor.command.getValue();
        const html = await editor.command.getHTML();

        autosaveSection(sectionKey, { content: JSON.stringify(data), html: html.main }, isRichTextEmpty(html.main));
        runGrammarCheck(editor);
    }, GRAMMAR_CHECK_DEBOUNCE_MS);
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

    wireSectionAutosave(editor, wrapper.dataset.sectionKey, mount);

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

    wireSectionAutosave(editor, wrapper.dataset.sectionKey, wrapper.querySelector('[data-canvas-mount]'));

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
            ? debounce(() => {
                const rows = collectTableRows(rowsContainer);
                autosaveSection(sectionKey, rows, isTableRowsEmpty(rows));
            }, 2000)
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

    // A link into this page (the readiness banner/modal on the main submission page,
    // which no longer has its own copy of these tabs to click directly — see
    // researcher/submissions/show.blade.php) can name a starting chapter via ?section=.
    const requestedKey = new URLSearchParams(window.location.search).get('section');
    const requestedIndex = requestedKey ? chapterButtons.findIndex((button) => button.dataset.sectionKey === requestedKey) : -1;
    let currentIndex = requestedIndex >= 0 ? requestedIndex : 0;

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
        grammarCheckUrl = autosaveUrl ? autosaveUrl.replace(/\/autosave$/, '/grammar-check') : null;
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

import { getDocument, GlobalWorkerOptions, TextLayer } from 'pdfjs-dist';
import workerSrc from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
import 'pdfjs-dist/web/pdf_viewer.css';

GlobalWorkerOptions.workerSrc = workerSrc;

const SCALE = 1.4;
const HIGHLIGHT_COLOR = 'rgba(250, 204, 21, 0.35)';
const PENDING_HIGHLIGHT_COLOR = 'rgba(140, 23, 48, 0.35)';
const CARD_GAP = 12;

function init() {
    const root = document.querySelector('[data-pdf-review]');

    if (! root) {
        return;
    }

    const ctx = {
        documentUrl: root.dataset.documentUrl,
        commentsUrl: root.dataset.commentsUrl,
        channelName: root.dataset.channel,
        canCreate: root.dataset.canCreate === '1',
        canEditAll: root.dataset.canEditAll === '1',
        snapshotId: root.dataset.snapshotId ? Number(root.dataset.snapshotId) : null,
        currentUserId: Number(root.dataset.currentUserId),
        pagesContainer: root.querySelector('[data-pdf-pages]'),
        loadingEl: root.querySelector('[data-pdf-loading]'),
        track: root.querySelector('[data-comment-track]'),
        emptyState: root.querySelector('[data-comment-empty]'),
        viewControls: root.querySelector('[data-document-view-controls]'),
        modeButtons: root.querySelectorAll('[data-doc-view-mode]'),
        paginationControls: root.querySelector('[data-doc-pagination-controls]'),
        pagePrev: root.querySelector('[data-doc-page-prev]'),
        pageNext: root.querySelector('[data-doc-page-next]'),
        pageProgress: root.querySelector('[data-doc-page-progress]'),
        comments: new Map(),
        pageRefs: new Map(),
        composerOpen: false,
        pendingComposer: null,
    };

    boot(ctx).catch((error) => {
        console.error('Failed to load document review', error);
        ctx.loadingEl.textContent = 'Failed to load document.';
    });
}

async function boot(ctx) {
    const [pdf, comments] = await Promise.all([
        getDocument({ url: ctx.documentUrl }).promise,
        fetchComments(ctx),
    ]);

    comments.forEach((comment) => ctx.comments.set(comment.id, comment));

    for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber++) {
        await renderPage(pdf, pageNumber, ctx);
    }

    ctx.loadingEl.remove();

    ctx.comments.forEach((comment) => renderHighlight(comment, ctx));
    wireTrackActions(ctx);
    wireEcho(ctx);
    wireResize(ctx);
    initDocumentViewMode(ctx);
    layoutCommentTrack(ctx);
}

const DOC_VIEW_MODE_KEY = 'eprism-document-view-mode';

function initDocumentViewMode(ctx) {
    const pageNumbers = Array.from(ctx.pageRefs.keys()).sort((a, b) => a - b);

    if (! pageNumbers.length || ! ctx.viewControls) {
        ctx.showPage = () => {};

        return;
    }

    ctx.viewControls.classList.remove('hidden');
    ctx.viewControls.classList.add('flex');

    let mode = localStorage.getItem(DOC_VIEW_MODE_KEY) === 'paginated' ? 'paginated' : 'scroll';
    let currentPage = pageNumbers[0];

    function render() {
        ctx.modeButtons.forEach((button) => {
            const active = button.dataset.docViewMode === mode;
            button.classList.toggle('bg-cherry-700', active);
            button.classList.toggle('text-white', active);
            button.classList.toggle('bg-slate-100', ! active);
            button.classList.toggle('text-slate-600', ! active);
        });

        ctx.paginationControls.classList.toggle('hidden', mode !== 'paginated');
        ctx.paginationControls.classList.toggle('flex', mode === 'paginated');

        if (mode === 'paginated') {
            const index = pageNumbers.indexOf(currentPage);
            pageNumbers.forEach((pageNumber) => {
                ctx.pageRefs.get(pageNumber).pageEl.classList.toggle('hidden', pageNumber !== currentPage);
            });
            ctx.pageProgress.textContent = `Page ${index + 1} of ${pageNumbers.length}`;
            ctx.pagePrev.disabled = index === 0;
            ctx.pageNext.disabled = index === pageNumbers.length - 1;
        } else {
            pageNumbers.forEach((pageNumber) => {
                ctx.pageRefs.get(pageNumber).pageEl.classList.remove('hidden');
            });
        }

        layoutCommentTrack(ctx);
    }

    ctx.modeButtons.forEach((button) => {
        button.addEventListener('click', () => {
            mode = button.dataset.docViewMode;
            localStorage.setItem(DOC_VIEW_MODE_KEY, mode);
            currentPage = pageNumbers[0];
            render();
        });
    });

    ctx.pagePrev.addEventListener('click', () => {
        const index = pageNumbers.indexOf(currentPage);
        if (index > 0) {
            currentPage = pageNumbers[index - 1];
            render();
        }
    });

    ctx.pageNext.addEventListener('click', () => {
        const index = pageNumbers.indexOf(currentPage);
        if (index < pageNumbers.length - 1) {
            currentPage = pageNumbers[index + 1];
            render();
        }
    });

    ctx.showPage = (pageNumber) => {
        if (mode === 'paginated' && pageNumbers.includes(pageNumber)) {
            currentPage = pageNumber;
            render();
        }
    };

    render();
}

async function fetchComments(ctx) {
    try {
        const response = await window.axios.get(ctx.commentsUrl);

        return response.data;
    } catch (error) {
        console.error('Failed to load comments', error);

        return [];
    }
}

async function renderPage(pdf, pageNumber, ctx) {
    const page = await pdf.getPage(pageNumber);
    const viewport = page.getViewport({ scale: SCALE });

    const pageEl = document.createElement('div');
    pageEl.className = 'relative mx-auto bg-white shadow ring-1 ring-slate-200';
    pageEl.style.width = `${viewport.width}px`;
    pageEl.style.height = `${viewport.height}px`;

    const canvas = document.createElement('canvas');
    canvas.width = viewport.width;
    canvas.height = viewport.height;
    canvas.className = 'absolute inset-0';
    pageEl.appendChild(canvas);

    const textLayerDiv = document.createElement('div');
    textLayerDiv.className = 'textLayer';
    pageEl.appendChild(textLayerDiv);

    const highlightLayer = document.createElement('div');
    highlightLayer.className = 'pointer-events-none absolute inset-0';
    pageEl.appendChild(highlightLayer);

    ctx.pagesContainer.appendChild(pageEl);
    ctx.pageRefs.set(pageNumber, { pageEl, highlightLayer, viewport });

    await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;

    const textLayer = new TextLayer({
        textContentSource: page.streamTextContent(),
        container: textLayerDiv,
        viewport,
    });
    await textLayer.render();

    if (ctx.canCreate) {
        textLayerDiv.addEventListener('mousedown', (event) => {
            ctx.selectionAnchor = { x: event.clientX, y: event.clientY };
        });
        textLayerDiv.addEventListener('mouseup', (event) => handleSelection(pageNumber, ctx, event));
    }
}

// pdf.js's raw TextLayer groups every line-span of one PDF paragraph under a shared,
// zero-box `.markedContent` wrapper (display: contents), and each line-span is glyph-tight
// with real un-hit-testable gaps between/around lines. When a mouseup point lands in one of
// those gaps, the browser can't resolve an exact caret inside a text node there and instead
// resolves the Range boundary to that shared paragraph wrapper — silently expanding the
// "selection" to every sibling line in the paragraph. Detect that and prefer a precise Range
// rebuilt from the mousedown/mouseup screen coordinates instead.
function resolveCaretRange(x, y) {
    if (document.caretRangeFromPoint) {
        return document.caretRangeFromPoint(x, y);
    }

    if (document.caretPositionFromPoint) {
        const pos = document.caretPositionFromPoint(x, y);

        if (! pos || ! pos.offsetNode) {
            return null;
        }

        const range = document.createRange();
        range.setStart(pos.offsetNode, pos.offset);
        range.collapse(true);

        return range;
    }

    return null;
}

function isTextBoundary(range) {
    return range.startContainer.nodeType === Node.TEXT_NODE && range.endContainer.nodeType === Node.TEXT_NODE;
}

function handleSelection(pageNumber, ctx, event) {
    const selection = window.getSelection();

    if (! selection || selection.isCollapsed || selection.rangeCount === 0) {
        return;
    }

    let range = selection.getRangeAt(0);

    if (! isTextBoundary(range) && ctx.selectionAnchor && event) {
        const startRange = resolveCaretRange(ctx.selectionAnchor.x, ctx.selectionAnchor.y);
        const endRange = resolveCaretRange(event.clientX, event.clientY);

        if (startRange && endRange) {
            const rebuilt = document.createRange();

            if (startRange.compareBoundaryPoints(Range.START_TO_START, endRange) <= 0) {
                rebuilt.setStart(startRange.startContainer, startRange.startOffset);
                rebuilt.setEnd(endRange.startContainer, endRange.startOffset);
            } else {
                rebuilt.setStart(endRange.startContainer, endRange.startOffset);
                rebuilt.setEnd(startRange.startContainer, startRange.startOffset);
            }

            if (isTextBoundary(rebuilt) && rebuilt.toString().trim()) {
                range = rebuilt;
            }
        }
    }

    const quote = range.toString().trim();

    if (! quote) {
        return;
    }

    const { pageEl } = ctx.pageRefs.get(pageNumber);
    const rects = computeRelativeRects(range, pageEl);

    if (rects.length === 0) {
        return;
    }

    openComposer({ pageNumber, rects, quote }, ctx);
    selection.removeAllRanges();
}

function computeRelativeRects(range, pageEl) {
    const pageRect = pageEl.getBoundingClientRect();

    return Array.from(range.getClientRects())
        .filter((rect) => rect.width > 0 && rect.height > 0)
        .map((rect) => ({
            top: ((rect.top - pageRect.top) / pageRect.height) * 100,
            left: ((rect.left - pageRect.left) / pageRect.width) * 100,
            width: (rect.width / pageRect.width) * 100,
            height: (rect.height / pageRect.height) * 100,
        }));
}

function openComposer({ pageNumber, rects, quote }, ctx) {
    closeComposer(ctx);

    renderPendingHighlight(pageNumber, rects, ctx);

    ctx.pendingComposer = { pageNumber, rects, quote };
    ctx.composerOpen = true;
    layoutCommentTrack(ctx);
}

function closeComposer(ctx) {
    ctx.pendingComposer = null;
    ctx.composerOpen = false;
    clearPendingHighlight(ctx);
    layoutCommentTrack(ctx);
}

function composerCardMarkup(pending) {
    return `
        <div data-composer-card data-card class="absolute inset-x-0 rounded-xl bg-cherry-50 p-3 ring-1 ring-cherry-200">
            <p class="mb-2 line-clamp-2 text-xs italic text-slate-500">"${escapeHtml(pending.quote)}"</p>
            <textarea class="w-full rounded-lg border-slate-300 text-sm" rows="3" placeholder="Add a comment or suggestion…"></textarea>
            <div class="mt-2 flex justify-end gap-2">
                <button type="button" data-cancel class="rounded-lg px-3 py-1.5 text-xs font-medium text-slate-500">Cancel</button>
                <button type="button" data-save class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white">Save</button>
            </div>
        </div>
    `;
}

function wireComposerCard(ctx) {
    const card = ctx.track.querySelector('[data-composer-card]');

    if (! card) {
        return;
    }

    const textarea = card.querySelector('textarea');
    textarea.focus();
    card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    card.querySelector('[data-cancel]').addEventListener('click', () => closeComposer(ctx));
    card.querySelector('[data-save]').addEventListener('click', async (event) => {
        const body = textarea.value.trim();

        if (! body) {
            return;
        }

        const button = event.currentTarget;
        clearCardError(card);
        window.disableWithSpinner?.(button);

        const { pageNumber, rects, quote } = ctx.pendingComposer;

        try {
            const response = await window.axios.post(ctx.commentsUrl, {
                page_number: pageNumber,
                quote_text: quote,
                anchor: { rects },
                body,
            });

            ctx.comments.set(response.data.id, response.data);
            renderHighlight(response.data, ctx);
            closeComposer(ctx);
        } catch (error) {
            console.error('Failed to save comment', error);
            showCardError(card, 'Could not save this comment. Please try again.');
            resetSpinnerButton(button, 'Save');
        }
    });
}

function showCardError(card, message) {
    clearCardError(card);

    const error = document.createElement('p');
    error.dataset.cardError = '1';
    error.className = 'mt-2 text-xs font-medium text-rose-600';
    error.textContent = message;
    card.querySelector('.mt-2.flex.justify-end')?.insertAdjacentElement('beforebegin', error);
}

function clearCardError(card) {
    card.querySelector('[data-card-error]')?.remove();
}

function resetSpinnerButton(button, label) {
    button.disabled = false;
    delete button.dataset.spinnerApplied;
    button.textContent = label;
}

function renderPendingHighlight(pageNumber, rects, ctx) {
    const pageRef = ctx.pageRefs.get(pageNumber);

    if (! pageRef) {
        return;
    }

    rects.forEach((rect) => {
        const mark = document.createElement('div');
        mark.dataset.pendingHighlight = '1';
        mark.className = 'pointer-events-none absolute rounded-sm';
        mark.style.top = `${rect.top}%`;
        mark.style.left = `${rect.left}%`;
        mark.style.width = `${rect.width}%`;
        mark.style.height = `${rect.height}%`;
        mark.style.backgroundColor = PENDING_HIGHLIGHT_COLOR;
        pageRef.highlightLayer.appendChild(mark);
    });
}

function clearPendingHighlight(ctx) {
    ctx.pageRefs.forEach(({ highlightLayer }) => {
        highlightLayer.querySelectorAll('[data-pending-highlight]').forEach((el) => el.remove());
    });
}

function renderHighlight(comment, ctx) {
    const pageRef = ctx.pageRefs.get(comment.page_number);

    if (! pageRef) {
        return;
    }

    removeHighlight(comment.id, ctx);

    const rects = comment.anchor?.rects ?? [];

    rects.forEach((rect) => {
        const mark = document.createElement('div');
        mark.dataset.commentId = String(comment.id);
        mark.className = 'pointer-events-auto absolute cursor-pointer rounded-sm';
        mark.style.top = `${rect.top}%`;
        mark.style.left = `${rect.left}%`;
        mark.style.width = `${rect.width}%`;
        mark.style.height = `${rect.height}%`;
        mark.style.backgroundColor = HIGHLIGHT_COLOR;
        pageRef.highlightLayer.appendChild(mark);
    });
}

function removeHighlight(commentId, ctx) {
    ctx.pageRefs.forEach(({ highlightLayer }) => {
        highlightLayer.querySelectorAll(`[data-comment-id="${commentId}"]`).forEach((el) => el.remove());
    });
}

function canModify(comment, ctx) {
    return ctx.canEditAll || (ctx.canCreate && comment.author_id === ctx.currentUserId);
}

function commentCardMarkup(comment, ctx) {
    return `
        <div data-comment-item="${comment.id}" data-card class="absolute inset-x-0 cursor-pointer rounded-xl bg-slate-50 p-3 text-sm shadow-sm ring-1 ring-slate-200 transition hover:bg-slate-100">
            <div class="flex items-center justify-between gap-2">
                <span class="font-medium text-slate-900">${escapeHtml(comment.author?.name ?? 'Unknown')}</span>
                <span class="text-xs text-slate-400">p.${comment.page_number}</span>
            </div>
            ${comment.quote_text ? `<p class="mt-1 line-clamp-2 text-xs italic text-slate-500">"${escapeHtml(comment.quote_text)}"</p>` : ''}
            <p data-comment-body class="mt-2 whitespace-pre-wrap text-slate-700">${escapeHtml(comment.body)}</p>
            ${canModify(comment, ctx) ? `
                <div class="mt-2 flex gap-3 text-xs">
                    <button type="button" data-edit="${comment.id}" class="font-medium text-cherry-700">Edit</button>
                    <button type="button" data-delete="${comment.id}" class="font-medium text-rose-600">Delete</button>
                </div>
            ` : ''}
        </div>
    `;
}

/**
 * Anchor a comment/composer card to the same vertical position as its
 * highlight, measured as a pixel delta between the track's top edge and the
 * highlight's top edge — not offsetTop/offsetParent, since the two columns
 * have different padding/heading chrome above them. Both columns sit in the
 * same normal document flow (no independent scroll region on the aside), so
 * this delta stays valid across scroll positions and only needs recomputing
 * when content or layout actually changes.
 */
function computeAnchorTop(pageNumber, rects, ctx) {
    const pageRef = ctx.pageRefs.get(pageNumber);

    if (! pageRef || ! rects.length) {
        return 0;
    }

    const topPercent = Math.min(...rects.map((rect) => rect.top));
    const pageRect = pageRef.pageEl.getBoundingClientRect();
    const trackRect = ctx.track.getBoundingClientRect();
    const highlightViewportTop = pageRect.top + (topPercent / 100) * pageRect.height;

    return highlightViewportTop - trackRect.top;
}

/**
 * Re-measure and re-position the cards already in the track (no markup
 * rebuild) — used both after a full layoutCommentTrack() render and after
 * an in-place change like entering edit mode that alters a card's height.
 * Anchor data is re-derived live from ctx state rather than baked into the
 * DOM, so this stays correct no matter what triggered it.
 */
function restackCards(ctx) {
    const cards = Array.from(ctx.track.querySelectorAll('[data-card]'));

    const withTops = cards.map((el) => {
        let pageNumber;
        let rects;

        if (el.dataset.commentItem) {
            const comment = ctx.comments.get(Number(el.dataset.commentItem));
            pageNumber = comment?.page_number;
            rects = comment?.anchor?.rects ?? [];
        } else {
            pageNumber = ctx.pendingComposer?.pageNumber;
            rects = ctx.pendingComposer?.rects ?? [];
        }

        return { el, anchorTop: computeAnchorTop(pageNumber, rects, ctx) };
    });

    withTops.sort((a, b) => a.anchorTop - b.anchorTop);

    let cursor = 0;
    withTops.forEach((item) => {
        const top = Math.max(item.anchorTop, cursor);
        item.el.style.top = `${top}px`;
        cursor = top + item.el.offsetHeight + CARD_GAP;
    });

    const pagesHeight = ctx.pagesContainer.getBoundingClientRect().height;
    ctx.track.style.minHeight = withTops.length ? `${Math.max(pagesHeight, cursor)}px` : '';
}

/**
 * Full re-render of the comment track: which comments are eligible (only
 * those on a currently visible page — every page in scroll mode, just the
 * current one in paginated mode), their markup, and their stacked positions.
 */
function layoutCommentTrack(ctx) {
    const track = ctx.track;

    track.querySelectorAll('[data-card]').forEach((el) => el.remove());

    const visiblePages = new Set(
        Array.from(ctx.pageRefs.entries())
            .filter(([, ref]) => ! ref.pageEl.classList.contains('hidden'))
            .map(([pageNumber]) => pageNumber)
    );

    const comments = Array.from(ctx.comments.values())
        .filter((comment) => visiblePages.has(comment.page_number))
        .sort((a, b) => a.page_number - b.page_number || a.id - b.id);

    const showComposer = Boolean(ctx.pendingComposer) && visiblePages.has(ctx.pendingComposer.pageNumber);

    ctx.emptyState.classList.toggle('hidden', comments.length > 0 || showComposer);

    comments.forEach((comment) => {
        track.insertAdjacentHTML('beforeend', commentCardMarkup(comment, ctx));
    });

    if (showComposer) {
        track.insertAdjacentHTML('beforeend', composerCardMarkup(ctx.pendingComposer));
    }

    restackCards(ctx);

    if (showComposer) {
        wireComposerCard(ctx);
    }
}

let emphasizeTimeout = null;

function emphasizeCommentItem(item) {
    document.querySelectorAll('[data-comment-item].is-emphasized').forEach((el) => {
        el.classList.remove('is-emphasized', 'ring-2', 'ring-cherry-400', 'bg-cherry-50');
    });

    item.classList.add('is-emphasized', 'ring-2', 'ring-cherry-400', 'bg-cherry-50');

    clearTimeout(emphasizeTimeout);
    emphasizeTimeout = setTimeout(() => {
        item.classList.remove('is-emphasized', 'ring-2', 'ring-cherry-400', 'bg-cherry-50');
    }, 2500);
}

let emphasizeHighlightTimeout = null;

function emphasizeHighlight(mark) {
    document.querySelectorAll('[data-comment-id].is-emphasized').forEach((el) => {
        el.classList.remove('is-emphasized');
        el.style.outline = '';
        el.style.outlineOffset = '';
    });

    mark.classList.add('is-emphasized');
    mark.style.outline = '3px solid rgba(140, 23, 48, 0.85)';
    mark.style.outlineOffset = '1px';

    clearTimeout(emphasizeHighlightTimeout);
    emphasizeHighlightTimeout = setTimeout(() => {
        mark.classList.remove('is-emphasized');
        mark.style.outline = '';
        mark.style.outlineOffset = '';
    }, 2500);
}

function goToCommentHighlight(commentId, ctx) {
    const comment = ctx.comments.get(commentId);

    if (! comment) {
        return;
    }

    ctx.showPage?.(comment.page_number);

    const mark = ctx.pagesContainer.querySelector(`[data-comment-id="${commentId}"]`);

    if (! mark) {
        return;
    }

    mark.scrollIntoView({ behavior: 'smooth', block: 'center' });
    emphasizeHighlight(mark);
}

function wireTrackActions(ctx) {
    ctx.track.addEventListener('click', async (event) => {
        const editButton = event.target.closest('[data-edit]');
        const deleteButton = event.target.closest('[data-delete]');

        if (editButton) {
            beginEdit(Number(editButton.dataset.edit), ctx);

            return;
        }

        if (deleteButton) {
            window.disableWithSpinner?.(deleteButton);
            await deleteComment(Number(deleteButton.dataset.delete), ctx);

            return;
        }

        if (event.target.closest('button') || event.target.closest('textarea')) {
            return;
        }

        const item = event.target.closest('[data-comment-item]');

        if (item) {
            goToCommentHighlight(Number(item.dataset.commentItem), ctx);
        }
    });

    ctx.pagesContainer.addEventListener('click', (event) => {
        const commentId = event.target.dataset.commentId;

        if (! commentId) {
            return;
        }

        const item = ctx.track.querySelector(`[data-comment-item="${commentId}"]`);

        if (! item) {
            return;
        }

        item.scrollIntoView({ behavior: 'smooth', block: 'center' });
        emphasizeCommentItem(item);
    });
}

function beginEdit(commentId, ctx) {
    const comment = ctx.comments.get(commentId);
    const item = ctx.track.querySelector(`[data-comment-item="${commentId}"]`);

    if (! comment || ! item) {
        return;
    }

    const bodyEl = item.querySelector('[data-comment-body]');
    bodyEl.outerHTML = `
        <div data-comment-body>
            <textarea class="w-full rounded-lg border-slate-300 text-sm" rows="3">${escapeHtml(comment.body)}</textarea>
            <div class="mt-2 flex justify-end gap-2">
                <button type="button" data-cancel-edit="${commentId}" class="rounded-lg px-3 py-1.5 text-xs font-medium text-slate-500">Cancel</button>
                <button type="button" data-save-edit="${commentId}" class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white">Save</button>
            </div>
        </div>
    `;
    restackCards(ctx);

    item.querySelector(`[data-cancel-edit="${commentId}"]`).addEventListener('click', () => layoutCommentTrack(ctx));
    item.querySelector(`[data-save-edit="${commentId}"]`).addEventListener('click', async (event) => {
        const button = event.currentTarget;
        const textarea = item.querySelector('textarea');
        const body = textarea.value.trim();

        if (! body) {
            return;
        }

        item.querySelector('[data-edit-error]')?.remove();
        window.disableWithSpinner?.(button);

        const success = await updateComment(commentId, body, ctx);

        if (success) {
            layoutCommentTrack(ctx);
        } else {
            const error = document.createElement('p');
            error.dataset.editError = '1';
            error.className = 'mt-2 text-xs font-medium text-rose-600';
            error.textContent = 'Could not save this edit. Please try again.';
            textarea.insertAdjacentElement('afterend', error);
            resetSpinnerButton(button, 'Save');
        }
    });
}

async function updateComment(commentId, body, ctx) {
    try {
        const response = await window.axios.patch(`${ctx.commentsUrl}/${commentId}`, { body });
        ctx.comments.set(response.data.id, response.data);

        return true;
    } catch (error) {
        console.error('Failed to update comment', error);

        return false;
    }
}

async function deleteComment(commentId, ctx) {
    try {
        await window.axios.delete(`${ctx.commentsUrl}/${commentId}`);
        ctx.comments.delete(commentId);
        removeHighlight(commentId, ctx);
        layoutCommentTrack(ctx);
    } catch (error) {
        console.error('Failed to delete comment', error);
        layoutCommentTrack(ctx);
        window.alert?.('Could not delete this comment. Please try again.');
    }
}

function wireEcho(ctx) {
    if (! window.Echo) {
        return;
    }

    window.Echo.private(ctx.channelName).listen('.document-comment', (event) => {
        const { action, comment } = event;

        // A pinned-to-an-old-version view (see snapshotId) should stay frozen to that
        // version's comments — a live comment made against the current manuscript belongs
        // to a different snapshot and would render at the wrong spot on this older layout.
        if (ctx.snapshotId && comment.research_snapshot_id && comment.research_snapshot_id !== ctx.snapshotId) {
            return;
        }

        if (action === 'created' || action === 'updated') {
            ctx.comments.set(comment.id, comment);
            renderHighlight(comment, ctx);
        } else if (action === 'deleted') {
            ctx.comments.delete(comment.id);
            removeHighlight(comment.id, ctx);
        }

        layoutCommentTrack(ctx);
    });
}

function wireResize(ctx) {
    let timeout;

    window.addEventListener('resize', () => {
        clearTimeout(timeout);
        timeout = setTimeout(() => layoutCommentTrack(ctx), 150);
    });
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';

    return div.innerHTML;
}

document.addEventListener('DOMContentLoaded', init);

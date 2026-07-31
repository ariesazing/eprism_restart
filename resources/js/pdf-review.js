import { getDocument, GlobalWorkerOptions, TextLayer } from 'pdfjs-dist';
import workerSrc from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
import 'pdfjs-dist/web/pdf_viewer.css';

GlobalWorkerOptions.workerSrc = workerSrc;

const SCALE = 1.4;
const HIGHLIGHT_COLOR = 'rgba(250, 204, 21, 0.35)';
const PENDING_HIGHLIGHT_COLOR = 'rgba(185, 28, 28, 0.35)';

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
        currentUserId: Number(root.dataset.currentUserId),
        pagesContainer: root.querySelector('[data-pdf-pages]'),
        loadingEl: root.querySelector('[data-pdf-loading]'),
        sidebar: root.querySelector('[data-comment-sidebar]'),
        composerSlot: root.querySelector('[data-comment-composer]'),
        comments: new Map(),
        pageRefs: new Map(),
        composerOpen: false,
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
    renderSidebar(ctx);
    wireSidebarActions(ctx);
    wireEcho(ctx);
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
        textLayerDiv.addEventListener('mouseup', () => handleSelection(pageNumber, ctx));
    }
}

function handleSelection(pageNumber, ctx) {
    const selection = window.getSelection();

    if (! selection || selection.isCollapsed || selection.rangeCount === 0) {
        return;
    }

    const range = selection.getRangeAt(0);
    const quote = selection.toString().trim();

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

    ctx.composerSlot.innerHTML = `
        <div class="rounded-xl bg-red-50 p-3 ring-1 ring-red-200">
            <p class="mb-2 line-clamp-2 text-xs italic text-slate-500">"${escapeHtml(quote)}"</p>
            <textarea class="w-full rounded-lg border-slate-300 text-sm" rows="3" placeholder="Add a comment or suggestion…"></textarea>
            <div class="mt-2 flex justify-end gap-2">
                <button type="button" data-cancel class="rounded-lg px-3 py-1.5 text-xs font-medium text-slate-500">Cancel</button>
                <button type="button" data-save class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white">Save</button>
            </div>
        </div>
    `;
    ctx.composerSlot.classList.remove('hidden');
    ctx.composerOpen = true;
    ctx.composerSlot.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    const textarea = ctx.composerSlot.querySelector('textarea');
    textarea.focus();

    ctx.composerSlot.querySelector('[data-cancel]').addEventListener('click', () => closeComposer(ctx));
    ctx.composerSlot.querySelector('[data-save]').addEventListener('click', async (event) => {
        const body = textarea.value.trim();

        if (! body) {
            return;
        }

        window.disableWithSpinner?.(event.currentTarget);

        try {
            const response = await window.axios.post(ctx.commentsUrl, {
                page_number: pageNumber,
                quote_text: quote,
                anchor: { rects },
                body,
            });

            ctx.comments.set(response.data.id, response.data);
            renderHighlight(response.data, ctx);
            renderSidebar(ctx);
        } catch (error) {
            console.error('Failed to save comment', error);
        }

        closeComposer(ctx);
    });
}

function closeComposer(ctx) {
    ctx.composerSlot.innerHTML = '';
    ctx.composerSlot.classList.add('hidden');
    ctx.composerOpen = false;
    clearPendingHighlight(ctx);
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

function renderSidebar(ctx) {
    const comments = Array.from(ctx.comments.values()).sort((a, b) => a.page_number - b.page_number || a.id - b.id);

    if (comments.length === 0) {
        ctx.sidebar.innerHTML = '<div class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500">No comments yet.</div>';

        return;
    }

    ctx.sidebar.innerHTML = comments.map((comment) => `
        <div data-comment-item="${comment.id}" class="rounded-xl bg-slate-50 p-3 text-sm">
            <div class="flex items-center justify-between gap-2">
                <span class="font-medium text-slate-900">${escapeHtml(comment.author?.name ?? 'Unknown')}</span>
                <span class="text-xs text-slate-400">p.${comment.page_number}</span>
            </div>
            ${comment.quote_text ? `<p class="mt-1 line-clamp-2 text-xs italic text-slate-500">"${escapeHtml(comment.quote_text)}"</p>` : ''}
            <p data-comment-body class="mt-2 whitespace-pre-wrap text-slate-700">${escapeHtml(comment.body)}</p>
            ${canModify(comment, ctx) ? `
                <div class="mt-2 flex gap-3 text-xs">
                    <button type="button" data-edit="${comment.id}" class="font-medium text-red-700">Edit</button>
                    <button type="button" data-delete="${comment.id}" class="font-medium text-rose-600">Delete</button>
                </div>
            ` : ''}
        </div>
    `).join('');
}

let emphasizeTimeout = null;

function emphasizeCommentItem(item) {
    document.querySelectorAll('[data-comment-item].is-emphasized').forEach((el) => {
        el.classList.remove('is-emphasized', 'ring-2', 'ring-red-400', 'bg-red-50');
    });

    item.classList.add('is-emphasized', 'ring-2', 'ring-red-400', 'bg-red-50');

    clearTimeout(emphasizeTimeout);
    emphasizeTimeout = setTimeout(() => {
        item.classList.remove('is-emphasized', 'ring-2', 'ring-red-400', 'bg-red-50');
    }, 2500);
}

function wireSidebarActions(ctx) {
    ctx.sidebar.addEventListener('click', async (event) => {
        const editButton = event.target.closest('[data-edit]');
        const deleteButton = event.target.closest('[data-delete]');

        if (editButton) {
            beginEdit(Number(editButton.dataset.edit), ctx);
        }

        if (deleteButton) {
            window.disableWithSpinner?.(deleteButton);
            await deleteComment(Number(deleteButton.dataset.delete), ctx);
        }
    });

    ctx.pagesContainer.addEventListener('click', (event) => {
        const commentId = event.target.dataset.commentId;

        if (! commentId) {
            return;
        }

        const item = document.querySelector(`[data-comment-item="${commentId}"]`);

        if (! item) {
            return;
        }

        item.scrollIntoView({ behavior: 'smooth', block: 'center' });
        emphasizeCommentItem(item);
    });
}

function beginEdit(commentId, ctx) {
    const comment = ctx.comments.get(commentId);
    const item = document.querySelector(`[data-comment-item="${commentId}"]`);

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

    item.querySelector(`[data-cancel-edit="${commentId}"]`).addEventListener('click', () => renderSidebar(ctx));
    item.querySelector(`[data-save-edit="${commentId}"]`).addEventListener('click', async (event) => {
        const textarea = item.querySelector('textarea');
        window.disableWithSpinner?.(event.currentTarget);
        await updateComment(commentId, textarea.value.trim(), ctx);
    });
}

async function updateComment(commentId, body, ctx) {
    if (! body) {
        return;
    }

    try {
        const response = await window.axios.patch(`${ctx.commentsUrl}/${commentId}`, { body });
        ctx.comments.set(response.data.id, response.data);
        renderSidebar(ctx);
    } catch (error) {
        console.error('Failed to update comment', error);
        renderSidebar(ctx);
    }
}

async function deleteComment(commentId, ctx) {
    try {
        await window.axios.delete(`${ctx.commentsUrl}/${commentId}`);
        ctx.comments.delete(commentId);
        removeHighlight(commentId, ctx);
        renderSidebar(ctx);
    } catch (error) {
        console.error('Failed to delete comment', error);
        renderSidebar(ctx);
    }
}

function wireEcho(ctx) {
    if (! window.Echo) {
        return;
    }

    window.Echo.private(ctx.channelName).listen('.document-comment', (event) => {
        const { action, comment } = event;

        if (action === 'created' || action === 'updated') {
            ctx.comments.set(comment.id, comment);
            renderHighlight(comment, ctx);
        } else if (action === 'deleted') {
            ctx.comments.delete(comment.id);
            removeHighlight(comment.id, ctx);
        }

        renderSidebar(ctx);
    });
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';

    return div.innerHTML;
}

document.addEventListener('DOMContentLoaded', init);

// Every discussion panel lives inside a collapsed chat modal (see
// submissions/partials/discussion.blade.php) and boots on demand — via
// window.initSubmissionDiscussion(root), called when its trigger button opens the
// modal — rather than eagerly on page load. A page can render several of these at
// once (e.g. one per row in the admin submissions list), so eagerly fetching history
// and subscribing every one's channel up front would be wasteful and could hit
// channel-auth limits on a long list; the data-discussion-initialized guard makes
// re-opening the same modal a no-op instead of re-fetching/re-subscribing.
function initDiscussionPanel(root) {
    if (! root || root.dataset.discussionInitialized === '1') {
        return;
    }

    root.dataset.discussionInitialized = '1';

    const ctx = {
        messagesUrl: root.dataset.messagesUrl,
        channelName: root.dataset.channel,
        currentUserId: Number(root.dataset.currentUserId),
        currentUserName: root.dataset.currentUserName,
        canDeleteAll: root.dataset.canDeleteAll === '1',
        messagesEl: root.querySelector('[data-discussion-messages]'),
        emptyEl: root.querySelector('[data-discussion-empty]'),
        form: root.querySelector('[data-discussion-form]'),
        input: root.querySelector('[data-discussion-input]'),
        messages: new Map(),
    };

    boot(ctx).catch((error) => {
        console.error('Failed to load discussion', error);
    });
}

async function boot(ctx) {
    const { data } = await window.axios.get(ctx.messagesUrl, { skipProgress: true });
    data.forEach((message) => ctx.messages.set(message.id, message));

    renderMessages(ctx);
    wireForm(ctx);
    wireEcho(ctx);
}

function renderMessages(ctx) {
    const sorted = Array.from(ctx.messages.values()).sort(
        (a, b) => new Date(a.created_at) - new Date(b.created_at)
    );

    ctx.emptyEl.classList.toggle('hidden', sorted.length > 0);
    ctx.messagesEl.innerHTML = sorted.map((message) => messageMarkup(message, ctx)).join('');

    ctx.messagesEl.querySelectorAll('[data-discussion-delete]').forEach((button) => {
        button.addEventListener('click', () => deleteMessage(Number(button.dataset.discussionDelete), ctx));
    });

    ctx.messagesEl.querySelectorAll('[data-discussion-retry]').forEach((button) => {
        button.addEventListener('click', () => retrySend(button.dataset.discussionRetry, ctx));
    });

    ctx.messagesEl.scrollTop = ctx.messagesEl.scrollHeight;
}

function messageMarkup(message, ctx) {
    const timestamp = new Date(message.created_at).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });

    // A message that hasn't round-tripped to the server yet has no real id/author to key
    // delete on, so it gets a status indicator (a small circular spinner while in flight,
    // like Facebook Messenger — or a retry affordance if it failed) instead of the normal
    // timestamp + delete controls.
    let statusMarkup;
    if (message.pending) {
        statusMarkup = `<span class="discussion-spinner" aria-label="Sending…"></span>`;
    } else if (message.failed) {
        statusMarkup = `<button type="button" data-discussion-retry="${message.id}" class="font-medium text-rose-600 hover:underline">Failed — Retry</button>`;
    } else {
        const canDelete = ctx.canDeleteAll || message.author_id === ctx.currentUserId;
        statusMarkup = `<span>${timestamp}</span>${canDelete ? `<button type="button" data-discussion-delete="${message.id}" class="font-medium text-rose-600 hover:underline">Delete</button>` : ''}`;
    }

    return `
        <div class="rounded-xl bg-white p-3 text-sm shadow-sm ring-1 ring-slate-200 ${message.pending ? 'opacity-70' : ''}">
            <div class="flex items-center justify-between gap-3">
                <span class="font-semibold text-slate-800">${escapeHtml(message.author?.name ?? 'Unknown')}</span>
                <span class="flex items-center gap-2 text-xs text-slate-400">${statusMarkup}</span>
            </div>
            <p class="mt-1 whitespace-pre-wrap text-slate-700">${escapeHtml(message.body)}</p>
        </div>
    `;
}

async function deleteMessage(id, ctx) {
    try {
        await window.axios.delete(`${ctx.messagesUrl}/${id}`, { skipProgress: true });
        ctx.messages.delete(id);
        renderMessages(ctx);
    } catch (error) {
        console.error('Failed to delete message', error);
        window.alert?.('Could not delete this message. Please try again.');
    }
}

/**
 * Renders the message immediately in a "sending" state and only reconciles it with the
 * server afterwards — the message never waits offscreen for the round-trip, matching a
 * normal chat app rather than a request/response form.
 */
function queueMessage(id, body, ctx) {
    const existing = ctx.messages.get(id);

    ctx.messages.set(id, {
        id,
        body,
        author: { name: ctx.currentUserName },
        author_id: ctx.currentUserId,
        created_at: existing?.created_at ?? new Date().toISOString(),
        pending: true,
        failed: false,
    });

    renderMessages(ctx);

    window.axios.post(ctx.messagesUrl, { body }, { skipProgress: true })
        .then(({ data }) => {
            ctx.messages.delete(id);
            ctx.messages.set(data.id, data);
            renderMessages(ctx);
        })
        .catch((error) => {
            console.error('Failed to send message', error);
            const pending = ctx.messages.get(id);

            if (pending) {
                ctx.messages.set(id, { ...pending, pending: false, failed: true });
                renderMessages(ctx);
            }
        });
}

function retrySend(id, ctx) {
    const message = ctx.messages.get(id);

    if (message) {
        queueMessage(id, message.body, ctx);
    }
}

function wireForm(ctx) {
    ctx.form.addEventListener('submit', (event) => {
        event.preventDefault();

        const body = ctx.input.value.trim();

        if (! body) {
            return;
        }

        ctx.input.value = '';
        queueMessage(`pending-${Date.now()}-${Math.random().toString(36).slice(2)}`, body, ctx);
    });

    ctx.input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && ! event.shiftKey) {
            event.preventDefault();
            ctx.form.requestSubmit();
        }
    });
}

function wireEcho(ctx) {
    if (! window.Echo) {
        return;
    }

    window.Echo.private(ctx.channelName).listen('.discussion-message', (event) => {
        if (event.action === 'deleted') {
            ctx.messages.delete(event.message.id);
        } else {
            ctx.messages.set(event.message.id, event.message);
        }

        renderMessages(ctx);
    });
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';

    return div.innerHTML;
}

window.initSubmissionDiscussion = initDiscussionPanel;

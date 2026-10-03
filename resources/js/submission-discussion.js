// Every discussion panel lives inside a collapsed chat modal (see
// submissions/partials/discussion.blade.php) and boots on demand — via
// window.initSubmissionDiscussion(root), called when its trigger button opens the
// modal — rather than eagerly on page load. A page can render several of these at
// once (e.g. one per row in the admin submissions list), so eagerly fetching history
// and subscribing every one's channel up front would be wasteful and could hit
// channel-auth limits on a long list; the data-discussion-initialized guard makes
// re-opening the same modal a no-op instead of re-fetching/re-subscribing.
function initDiscussionPanel(root) {
    if (!root) return;
    if (root.discussionContext) {
        refreshMessages(root.discussionContext).catch(console.error);
        return;
    }

    root.dataset.discussionInitialized = '1';

    const ctx = {
        root,
        messagesUrl: root.dataset.messagesUrl,
        channelName: root.dataset.channel,
        currentUserId: Number(root.dataset.currentUserId),
        currentUserName: root.dataset.currentUserName,
        messagesEl: root.querySelector('[data-discussion-messages]'),
        emptyEl: root.querySelector('[data-discussion-empty]'),
        form: root.querySelector('[data-discussion-form]'),
        input: root.querySelector('[data-discussion-input]'),
        messages: new Map(),
    };

    root.discussionContext = ctx;
    boot(ctx).catch((error) => {
        console.error('Failed to load discussion', error);
    });
}

async function boot(ctx) {
    wireForm(ctx);
    wireEcho(ctx);
    await refreshMessages(ctx);
}

async function refreshMessages(ctx) {
    const { data } = await window.axios.get(ctx.messagesUrl, { skipProgress: true });
    // Preserve unsent local messages while refreshing the server's message list.
    for (const [id, message] of ctx.messages) {
        if (!message.pending && !message.failed) ctx.messages.delete(id);
    }
    data.forEach(message => ctx.messages.set(message.id, message));
    renderMessages(ctx);
    await markVisibleMessagesRead(ctx);
}

async function markVisibleMessagesRead(ctx) {
    if (!ctx.root.isConnected || ctx.root.offsetParent === null || document.hidden) return;
    const lastId = Math.max(0, ...Array.from(ctx.messages.keys()).filter(id => Number.isInteger(id)));
    const { data } = await window.axios.post(ctx.messagesUrl + '/read', { last_message_id: lastId }, { skipProgress: true });
    const button = document.querySelector('[data-discussion-indicator="' + ctx.root.id.replace('discussion-', '') + '"]');
    if (button) button.querySelector('[data-discussion-dot]').hidden = !data.unread;
}

function renderMessages(ctx) {
    const sorted = Array.from(ctx.messages.values()).sort(
        (a, b) => new Date(a.created_at) - new Date(b.created_at)
    );

    ctx.emptyEl.classList.toggle('hidden', sorted.length > 0);
    ctx.messagesEl.innerHTML = sorted.map((message) => messageMarkup(message, ctx)).join('');

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

    // A message that hasn't round-tripped to the server yet gets a status indicator (a small
    // circular spinner while in flight, like Facebook Messenger — or a retry affordance if it
    // failed) instead of the normal timestamp.
    let statusMarkup;
    if (message.pending) {
        statusMarkup = `<span class="discussion-spinner" aria-label="Sending…"></span>`;
    } else if (message.failed) {
        statusMarkup = `<button type="button" data-discussion-retry="${message.id}" class="font-medium text-rose-600 hover:underline">Failed — Retry</button>`;
    } else {
        statusMarkup = `<span>${timestamp}</span>`;
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
        markVisibleMessagesRead(ctx).catch(console.error);
    });
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';

    return div.innerHTML;
}

window.initSubmissionDiscussion = initDiscussionPanel;

// Observe indicators before a discussion is opened, including dynamically refreshed rows.
const discussionIndicators = new Map();
async function refreshIndicator(button) {
    if (!button.isConnected || document.hidden) return;
    const root = document.getElementById('discussion-' + button.dataset.discussionIndicator);
    if (root?.discussionContext && root.offsetParent !== null) {
        await refreshMessages(root.discussionContext);
        return;
    }
    const { data } = await window.axios.get(button.dataset.discussionUrl + '/unread', { skipProgress: true });
    button.querySelector('[data-discussion-dot]').hidden = !data.unread;
}
function discoverDiscussionIndicators() {
    for (const [button, dispose] of discussionIndicators) {
        if (!button.isConnected) { dispose(); discussionIndicators.delete(button); }
    }
    document.querySelectorAll('[data-discussion-indicator]').forEach(button => {
        if (discussionIndicators.has(button)) return;
        const callback = () => refreshIndicator(button).catch(console.error);
        const channel = window.Echo?.private('submission.' + button.dataset.discussionIndicator + '.discussion');
        channel?.listen('.discussion-message', callback);
        discussionIndicators.set(button, () => channel?.stopListening('.discussion-message', callback));
        callback();
    });
}
new MutationObserver(discoverDiscussionIndicators).observe(document.body, { childList: true, subtree: true });
discoverDiscussionIndicators();
setInterval(() => {
    for (const button of discussionIndicators.keys()) refreshIndicator(button).catch(console.error);
}, 15000);
document.addEventListener('visibilitychange', () => {
    if (!document.hidden) for (const button of discussionIndicators.keys()) refreshIndicator(button).catch(console.error);
});

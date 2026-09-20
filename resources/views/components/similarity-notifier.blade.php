{{--
    "Your similarity check is done" popup — see App\View\Components\SimilarityNotifier. A check
    takes minutes, so the researcher has usually left the page that started it; this is in both
    layouts so they're told wherever they are.

    How it behaves:
      - Finished checks the researcher hasn't been told about (the server's list, in $state) open
        the popup as soon as the page loads — including a check that finished while they were away
        from the app entirely.
      - While a check is still running ($state['active'] > 0) the page polls for it every few
        seconds; when it finishes the popup opens. With nothing running there's no polling at all.
      - Several finished checks are shown one at a time.
      - Closing it however — Later, Escape, the backdrop — tells the server, so it isn't repeated;
        opening the report does too (SimilarityCheckController::show()).
    Expects: $state (PendingNotifications::for()), $statusUrl, $dismissUrl (with an __ID__ slot).
--}}
<div x-data="similarityNotifier(@js($state), @js(['status' => $statusUrl, 'dismiss' => $dismissUrl]))" class="contents">
    <x-modal name="similarity-finished" max-width="md">
        <div class="p-6" x-init="$watch('show', (open) => { if (! open) { dismissCurrent(); } })">
            <div class="flex items-start gap-4">
                <span
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full"
                    :class="view?.status === 'completed' ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600'"
                    aria-hidden="true"
                >
                    <svg x-show="view?.status === 'completed'" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                    <svg x-show="view && view.status !== 'completed'" x-cloak class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01" /><path d="M10.3 3.9 2.7 17.1a1.5 1.5 0 0 0 1.3 2.3h16a1.5 1.5 0 0 0 1.3-2.3L13.7 3.9a1.5 1.5 0 0 0-2.6 0z" /></svg>
                </span>

                <div class="min-w-0 flex-1">
                    <h3 class="text-lg font-semibold text-slate-900" x-text="view?.status === 'completed' ? 'Your similarity check is ready' : 'Your similarity check didn\'t finish'"></h3>
                    <p class="mt-1 break-words text-sm text-slate-600">
                        <span x-text="view?.submission"></span>
                    </p>
                </div>
            </div>

            <template x-if="view?.status === 'completed'">
                <div class="mt-5 flex items-center gap-4 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200">
                    <div class="text-4xl font-semibold" :class="bandText[view.band] ?? 'text-slate-600'" x-text="view.score + '%'"></div>
                    <div class="text-sm text-slate-600">
                        <div class="font-medium text-slate-900">Similarity index</div>
                        <div class="text-xs text-slate-500">Open the report to see which passages matched, and where they came from.</div>
                    </div>
                </div>
            </template>

            <template x-if="view && view.status !== 'completed'">
                <p class="mt-5 rounded-xl bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-200" x-text="view.error || 'Something went wrong while checking your chapters.'"></p>
            </template>

            <p x-show="queue.length > 1" x-cloak class="mt-3 text-xs text-slate-500" x-text="(queue.length - 1) + (queue.length === 2 ? ' more check has' : ' more checks have') + ' finished too — they\'ll show next.'"></p>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" @click="$dispatch('close-modal', 'similarity-finished')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Later</button>
                <a :href="view?.url" class="rounded-xl bg-cherry-700 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-cherry-800" x-text="view?.status === 'completed' ? 'View report' : 'View details'"></a>
            </div>
        </div>
    </x-modal>
</div>

<script>
    // Defined here, before Alpine starts (app.js is a deferred module), so x-data="similarityNotifier(...)"
    // above can find it — the same pattern the report page uses for its own component.
    window.similarityNotifier = (initial, urls) => ({
        queue: [...initial.finished],
        // `current` is the one the modal is open for (null once it's closed); `view` is what the
        // modal displays, and deliberately outlives `current` so the content doesn't flip to
        // placeholder wording during the close fade-out.
        current: null,
        view: null,
        active: initial.active,
        timer: null,
        // Ids dismissed on this page, so a poll that lands before the server has recorded the
        // dismissal can't bring the same check back.
        dismissed: new Set(),
        // Full class names, not built by concatenation, so Tailwind's scanner keeps them.
        bandText: { emerald: 'text-emerald-600', amber: 'text-amber-600', orange: 'text-orange-600', rose: 'text-rose-600', slate: 'text-slate-600' },

        init() {
            this.dropOwnPage();

            // The modal's own listeners attach as Alpine walks down to it — a beat after this
            // runs — and the page-load skeleton is still fading out; open once both have settled.
            setTimeout(() => this.showNext(), 500);

            if (this.active > 0) {
                this.timer = setInterval(() => this.poll(), 5000);
                // A background tab's timers are throttled to about a minute; catch up the moment
                // the researcher comes back to it.
                document.addEventListener('visibilitychange', () => { if (! document.hidden) { this.poll(); } });
            }
        },

        // A check's own page (progress screen, then report) already says everything — don't
        // announce it on top of itself.
        dropOwnPage() {
            this.queue = this.queue.filter((item) => new URL(item.url, window.location.origin).pathname !== window.location.pathname);
        },

        poll() {
            fetch(urls.status, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then((response) => (response.ok ? response.json() : null))
                .then((data) => {
                    if (! data) {
                        return;
                    }

                    this.active = data.active;

                    data.finished.forEach((item) => {
                        if (! this.dismissed.has(item.id) && ! this.queue.some((queued) => queued.id === item.id) && this.current?.id !== item.id) {
                            this.queue.push(item);
                        }
                    });

                    this.dropOwnPage();
                    this.showNext();

                    if (this.active === 0 && this.timer) {
                        clearInterval(this.timer);
                        this.timer = null;
                    }
                })
                .catch(() => {});
        },

        showNext() {
            if (this.current || ! this.queue.length) {
                return;
            }

            this.current = this.view = this.queue[0];
            this.$dispatch('open-modal', 'similarity-finished');
        },

        // Runs however the modal closed (Later, Escape, the backdrop): tell the server so this
        // check isn't announced again, then move on to the next one, if any.
        dismissCurrent() {
            const done = this.current;

            if (! done) {
                return;
            }

            this.current = null;
            this.dismissed.add(done.id);
            this.queue = this.queue.filter((item) => item.id !== done.id);

            fetch(urls.dismiss.replace('__ID__', done.id), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                credentials: 'same-origin',
                keepalive: true,
            }).catch(() => {});

            // After the close transition, so the next one opens fresh rather than mid-fade.
            setTimeout(() => this.showNext(), 350);
        },
    });
</script>

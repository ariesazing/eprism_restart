{{--
    Grammar review for chapters edited in ONLYOFFICE (see App\Services\ChapterGrammarReview and
    ChapterGrammarReviewController). ONLYOFFICE can't underline as you type, so instead of a live
    checker this shows the chapter's *saved* text with every LanguageTool issue highlighted and its
    suggestions on hover. Opened by the "Check grammar" button (section-editor.blade.php), which
    tells initChapterWizard() in submission-editor.js to fire the `grammar-review-open` event with the
    chapter that's open.

    Read-only on purpose: the document lives inside ONLYOFFICE's iframe, which this page can't edit,
    so a suggestion is copied to the clipboard for the researcher to paste in, not "applied".

    Also used read-only by reviewers and admins (submissions.partials.text-checks), who can't edit the
    chapter: there `forceSaveUrl` is unused (nothing is ever saved first) and $viewer says so in the footer.

    Expects: $chapters — list of {key, label, url, forceSaveUrl} for the submission's rich_text chapters.
    Optional: $viewer — 'researcher' (default) or anything else for the reviewer/admin wording.
    The `grammar-review-open` event's detail may carry its own `chapters` list, replacing the one this was
    rendered with — how the admin submissions list shares ONE modal across every submission on the page.
--}}
@php
    $viewer ??= 'researcher';
@endphp
<style>
    .gr-mark { --c: #7c3aed; background: color-mix(in srgb, var(--c) 12%, transparent); border-radius: 2px; cursor: pointer; outline: none; text-decoration: underline wavy var(--c); text-decoration-thickness: 1.5px; text-underline-offset: 3px; transition: background-color .12s ease; }
    .gr-mark:hover, .gr-mark:focus-visible, .gr-mark.gr-open { background: color-mix(in srgb, var(--c) 28%, transparent); }
    .gr-mark:focus-visible { box-shadow: 0 0 0 2px color-mix(in srgb, var(--c) 45%, transparent); }
</style>

<div
    x-data="grammarReview(@js($chapters))"
    @grammar-review-open.window="open($event.detail)"
    class="contents"
>
    <x-modal name="grammar-review" max-width="4xl">
        <div class="flex max-h-[86vh] flex-col" @scroll.capture="hideNow()" x-init="$watch('show', (open) => { if (! open) { run++; hideNow(); } })">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-6 py-4">
                <div class="min-w-0">
                    <h3 class="text-lg font-semibold text-slate-900">Grammar review</h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        <template x-if="phase === 'ready' && data && data.saved_at">
                            <span>Text as last saved at <span class="font-medium text-slate-700" x-text="savedLabel"></span>. Changes you make afterwards need a new check.</span>
                        </template>
                        <template x-if="! (phase === 'ready' && data && data.saved_at)">
                            <span>Hover a highlighted word or phrase to see what's wrong and what to change it to.</span>
                        </template>
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <label class="sr-only" for="grammar-review-chapter">Chapter</label>
                    <select id="grammar-review-chapter" x-model="key" @change="load(false)" class="max-w-[16rem] rounded-lg border-slate-300 py-1.5 pl-2.5 pr-8 text-xs text-slate-700">
                        <template x-for="chapter in chapters" :key="chapter.key">
                            <option :value="chapter.key" x-text="chapter.label" :selected="chapter.key === key"></option>
                        </template>
                    </select>
                    <button type="button" @click="load(key === activeKey)" :disabled="busy" class="rounded-lg bg-cherry-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-cherry-800 disabled:cursor-not-allowed disabled:opacity-50">Check again</button>
                    <button type="button" @click="$dispatch('close-modal', 'grammar-review')" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Close">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
                    </button>
                </div>
            </div>

            <div class="min-h-[14rem] flex-1 overflow-y-auto px-6 py-5" x-ref="body">
                {{-- Working --}}
                <div x-show="busy" class="flex items-center justify-center gap-3 py-16 text-sm text-slate-600">
                    <span class="doc-spinner"></span>
                    <span x-text="phase === 'saving' ? 'Saving your latest edits…' : 'Checking grammar…'"></span>
                </div>

                {{-- Failed to run at all --}}
                <div x-show="phase === 'error'" x-cloak class="rounded-xl bg-rose-50 p-4 text-sm text-rose-700 ring-1 ring-rose-200">
                    Couldn't run the grammar review just now. Try <strong>Check again</strong> in a moment.
                </div>

                <template x-if="phase === 'ready' && data">
                    <div>
                        <div x-show="! data.available" class="mb-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-200">
                            The grammar checker isn't available right now, so nothing could be highlighted. Your chapter is shown below as saved — try again later.
                        </div>
                        <div x-show="data.available && data.incomplete" class="mb-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-200">
                            The checker stopped responding partway through, so only the first part of this chapter was checked.
                        </div>
                        <div x-show="data.truncated" class="mb-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-200">
                            This chapter is very long — only the first part is shown and checked.
                        </div>

                        <template x-if="paragraphs.length === 0">
                            <p class="rounded-xl border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-500">
                                This chapter has no saved text yet. Write something in the editor, then choose <strong>Check again</strong>.
                            </p>
                        </template>

                        <template x-if="paragraphs.length > 0">
                            <div>
                                <div class="mb-4 flex flex-wrap items-center gap-2 text-xs">
                                    <template x-if="data.available && data.issue_count === 0">
                                        <span class="rounded-full bg-emerald-50 px-3 py-1 font-medium text-emerald-700 ring-1 ring-emerald-200">No grammar or spelling issues found</span>
                                    </template>
                                    <template x-if="data.available && data.issue_count > 0">
                                        <span class="rounded-full bg-slate-100 px-3 py-1 font-medium text-slate-700" x-text="data.issue_count + (data.issue_count === 1 ? ' issue' : ' issues') + ' in ' + data.word_count.toLocaleString() + ' words'"></span>
                                    </template>
                                    <template x-for="entry in legend" :key="entry.type">
                                        <span x-show="entry.count > 0" class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-medium text-slate-600 ring-1 ring-slate-200">
                                            <span class="h-2 w-2 rounded-full" :style="{ background: entry.color }"></span>
                                            <span x-text="entry.label + ' ' + entry.count"></span>
                                        </span>
                                    </template>
                                </div>

                                <div class="space-y-3 text-[15px] leading-7 text-slate-800">
                                    <template x-for="(segments, pi) in paragraphs" :key="pi">
                                        <p>
                                            <template x-for="(seg, si) in segments" :key="si">
                                                <span
                                                    x-text="seg.text"
                                                    :class="seg.match ? 'gr-mark' : ''"
                                                    :style="seg.match ? { '--c': colorOf(seg.match) } : {}"
                                                    :tabindex="seg.match ? 0 : null"
                                                    :aria-label="seg.match ? ('Possible issue: ' + seg.text) : null"
                                                    @mouseenter="seg.match && showCard($event.currentTarget, seg.match)"
                                                    @mouseleave="seg.match && hideSoon()"
                                                    @focus="seg.match && showCard($event.currentTarget, seg.match)"
                                                    @blur="seg.match && hideSoon()"
                                                    @click="seg.match && showCard($event.currentTarget, seg.match)"
                                                ></span>
                                            </template>
                                        </p>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <div class="border-t border-slate-200 bg-slate-50 px-6 py-3 text-xs text-slate-500">
                @if ($viewer === 'researcher')
                    This is a read-only copy — your editor isn't changed. To use a suggestion, click it to copy it, then paste it into your chapter.
                @else
                    This is a read-only check of the chapter as the researcher last saved it — nothing here changes their document. Click a suggestion to copy it, e.g. into a comment.
                @endif
            </div>
        </div>
    </x-modal>

    {{-- The hover card lives OUTSIDE the modal on purpose: the modal's dialog carries a CSS
         transform, which would make this `fixed` element position relative to the dialog instead
         of the viewport. --}}
    <div
        x-show="card"
        x-cloak
        x-ref="card"
        @mouseenter="cancelHide()"
        @mouseleave="hideSoon()"
        :style="{ top: cardTop + 'px', left: cardLeft + 'px' }"
        class="fixed z-[120] w-80 max-w-[calc(100vw-1rem)] rounded-xl bg-white p-3.5 text-sm shadow-xl ring-1 ring-slate-200"
        role="tooltip"
    >
        <template x-if="card">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide" :style="{ color: colorOf(card) }" x-text="card.short || card.category || typeLabel(card)"></p>
                <p class="mt-1 text-slate-700" x-text="card.message"></p>

                <template x-if="card.suggestions.length">
                    <div class="mt-3">
                        <p class="text-xs font-medium text-slate-500">Suggestions — click to copy</p>
                        <div class="mt-1.5 flex flex-wrap gap-1.5">
                            <template x-for="suggestion in card.suggestions" :key="suggestion">
                                <button type="button" @click="copy(suggestion)" class="rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-200 hover:bg-emerald-100" x-text="copied === suggestion ? 'Copied ✓' : suggestion"></button>
                            </template>
                        </div>
                    </div>
                </template>
                <template x-if="! card.suggestions.length">
                    <p class="mt-2 text-xs text-slate-400">No automatic suggestion — reword this part yourself.</p>
                </template>
            </div>
        </template>
    </div>
</div>

<script>
    // Defined before Alpine starts (app.js is a deferred module) so x-data="grammarReview(...)" can find it.
    window.grammarReview = (chapters) => ({
        chapters,
        key: chapters[0]?.key ?? null,
        activeKey: null, // the chapter open in the editor when the review was opened
        phase: 'idle', // idle | saving | checking | ready | error
        data: null,
        paragraphs: [], // [[{ text, match|null }, …], …]
        legend: [],
        card: null,
        cardTop: 0,
        cardLeft: 0,
        copied: null,
        hideTimer: null,
        run: 0, // guards against an older response landing after a newer request

        // Class names/colours are literal so they can't be lost to string concatenation.
        colors: { misspelling: '#dc2626', grammar: '#2563eb', style: '#d97706', other: '#7c3aed' },
        labels: { misspelling: 'Spelling', grammar: 'Grammar', style: 'Style', other: 'Other' },

        get busy() {
            return this.phase === 'saving' || this.phase === 'checking';
        },

        get savedLabel() {
            return this.data?.saved_at ? new Date(this.data.saved_at).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', second: '2-digit' }) : '';
        },

        open(detail) {
            if (detail?.chapters) {
                this.chapters = detail.chapters;
            }

            const requested = detail?.sectionKey;
            this.activeKey = this.chapters.some((chapter) => chapter.key === requested) ? requested : null;
            this.key = this.activeKey ?? this.chapters[0]?.key ?? null;
            this.$dispatch('open-modal', 'grammar-review');
            this.load(this.activeKey !== null);
        },

        chapter() {
            return this.chapters.find((chapter) => chapter.key === this.key) ?? null;
        },

        // `saveFirst` asks ONLYOFFICE to save the open chapter right now — it otherwise saves on its
        // own schedule, so text typed a moment ago wouldn't be in what we read. The save arrives at
        // our server asynchronously (ONLYOFFICE calls back), hence the short wait, and the review
        // states which save it reflects.
        async load(saveFirst) {
            const chapter = this.chapter();

            if (! chapter) {
                return;
            }

            const run = ++this.run;
            this.hideNow();
            this.data = null;
            this.paragraphs = [];

            try {
                if (saveFirst) {
                    this.phase = 'saving';
                    await fetch(chapter.forceSaveUrl, {
                        method: 'POST',
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
                        credentials: 'same-origin',
                    }).catch(() => {});
                    await new Promise((resolve) => setTimeout(resolve, 2200));
                }

                if (run !== this.run) {
                    return;
                }

                this.phase = 'checking';
                const response = await fetch(chapter.url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });

                if (! response.ok) {
                    throw new Error('review failed');
                }

                const data = await response.json();

                if (run !== this.run) {
                    return;
                }

                this.data = data;
                this.build(data);
                this.phase = 'ready';
            } catch (error) {
                if (run === this.run) {
                    this.phase = 'error';
                }
            }
        },

        typeOf(match) {
            return this.colors[match.type] ? match.type : 'other';
        },

        colorOf(match) {
            return this.colors[this.typeOf(match)];
        },

        typeLabel(match) {
            return this.labels[this.typeOf(match)];
        },

        // Cuts every paragraph into plain and highlighted segments. Offsets are LanguageTool's, in
        // UTF-16 units relative to a chunk's paragraphs joined by a blank line ("\n\n") — the same unit
        // JavaScript's own string indexes use, so they slice the text directly.
        build(data) {
            const paragraphs = [];
            const counts = { misspelling: 0, grammar: 0, style: 0, other: 0 };

            data.chunks.forEach((chunk) => {
                const matches = [...chunk.matches].sort((a, b) => a.offset - b.offset);
                let start = 0;

                chunk.paragraphs.forEach((text) => {
                    const end = start + text.length;
                    const segments = [];
                    let cursor = 0;

                    matches.forEach((match) => {
                        // Only matches that begin inside this paragraph; one that spills over the
                        // blank line into the next is clamped, one that overlaps a previous match is skipped.
                        if (match.length < 1 || match.offset < start || match.offset >= end) {
                            return;
                        }

                        const from = match.offset - start;
                        const to = Math.min(from + match.length, text.length);

                        if (from < cursor || to <= from) {
                            return;
                        }

                        if (from > cursor) {
                            segments.push({ text: text.slice(cursor, from), match: null });
                        }

                        segments.push({ text: text.slice(from, to), match });
                        counts[this.typeOf(match)]++;
                        cursor = to;
                    });

                    if (cursor < text.length) {
                        segments.push({ text: text.slice(cursor), match: null });
                    }

                    paragraphs.push(segments);
                    start = end + 2;
                });
            });

            this.paragraphs = paragraphs;
            this.legend = Object.keys(counts).map((type) => ({ type, label: this.labels[type], color: this.colors[type], count: counts[type] }));
        },

        // Named showCard, not show: the modal component this lives in has its own `show` (is it open),
        // which shadows a same-named method of this component everywhere inside the modal.
        showCard(target, match) {
            this.cancelHide();
            this.card = match;

            this.$nextTick(() => {
                const card = this.$refs.card;
                const rect = target.getBoundingClientRect();
                const height = card?.offsetHeight ?? 160;
                const below = rect.bottom + 8;

                this.cardTop = below + height > window.innerHeight - 8 ? Math.max(8, rect.top - height - 8) : below;
                this.cardLeft = Math.min(Math.max(8, rect.left), window.innerWidth - 328);
            });
        },

        // A short grace period, so the pointer can travel from the highlighted word onto the card.
        hideSoon() {
            this.cancelHide();
            this.hideTimer = setTimeout(() => this.hideNow(), 220);
        },

        cancelHide() {
            clearTimeout(this.hideTimer);
        },

        hideNow() {
            this.cancelHide();
            this.card = null;
        },

        async copy(text) {
            try {
                await navigator.clipboard.writeText(text);
            } catch (error) {
                const field = document.createElement('textarea');
                field.value = text;
                field.style.position = 'fixed';
                field.style.opacity = '0';
                document.body.appendChild(field);
                field.select();
                document.execCommand('copy');
                field.remove();
            }

            this.copied = text;
            setTimeout(() => { if (this.copied === text) { this.copied = null; } }, 1500);
        },
    });
</script>

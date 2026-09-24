{{--
    A submission's similarity report (see App\Similarity, SimilarityCheckController): the
    checked text with every matching passage highlighted, and a side panel listing the
    sources those passages matched — click a source to focus its highlights, or a highlight to
    find its source. While a check is still running this same page shows progress and reloads
    itself into the report when it's done. x-app-layout (with sidebar) for the same reason the
    manuscript viewer uses it: reading a document alongside a panel wants the full width.

    Expects: $submission, $check, $backUrl (where "Back to submission" goes — it differs for a
    researcher, reviewer and admin), $report (SimilarityReportBuilder::build(), null unless the
    check completed).
--}}
@php
    $bandText = ['emerald' => 'text-emerald-600', 'amber' => 'text-amber-600', 'orange' => 'text-orange-600', 'rose' => 'text-rose-600', 'slate' => 'text-slate-500'];
    $bandBar = ['emerald' => 'bg-emerald-500', 'amber' => 'bg-amber-500', 'orange' => 'bg-orange-500', 'rose' => 'bg-rose-500', 'slate' => 'bg-slate-400'];
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="font-mono text-xs text-slate-400">{{ $submission->reference_code }}</div>
                <h2 class="text-xl font-semibold leading-tight text-slate-800">{{ $submission->title }}</h2>
                <p class="mt-1 text-sm text-slate-500">Similarity Report</p>
            </div>
            <a href="{{ $backUrl }}" class="text-sm font-medium text-cherry-700">Back to submission</a>
        </div>
    </x-slot>

    @if ($check->isActive())
        <div
            class="mx-auto max-w-xl px-4 py-16 text-center"
            x-data="{
                // True once a check has sat 'queued' long enough that nothing is picking it up.
                notStarted: @js($check->queuedForSeconds() !== null && $check->queuedForSeconds() >= \App\Models\SimilarityCheck::QUEUED_WARNING_AFTER_SECONDS),
                poll() {
                    fetch(@js(route('submissions.similarity.status', [$submission, $check])), { headers: { Accept: 'application/json' } })
                        .then((response) => response.json())
                        .then((data) => {
                            if (data.status !== 'queued' && data.status !== 'running') {
                                window.location.reload();

                                return;
                            }

                            this.notStarted = data.queued_for !== null && data.queued_for >= {{ \App\Models\SimilarityCheck::QUEUED_WARNING_AFTER_SECONDS }};
                        })
                        .catch(() => {});
                },
            }"
            x-init="setInterval(() => poll(), 3000)"
        >
            <div class="mx-auto flex h-14 w-14 items-center justify-center"><span class="doc-spinner"></span></div>
            <h3 class="mt-6 text-lg font-semibold text-slate-900">Checking the chapters&hellip;</h3>
            <p class="mt-2 text-sm text-slate-600">Searching the web for passages from the text. This usually takes a minute or two &mdash; this page updates by itself when it's done, and you can also leave and come back from the submission page.</p>

            <div x-show="notStarted" x-cloak class="mt-6 rounded-xl bg-amber-50 p-4 text-left text-sm text-amber-800 ring-1 ring-amber-200">
                <p class="font-medium">This check hasn't started yet.</p>
                <p class="mt-1">It runs in the background, and nothing has picked it up. The background worker (the queue) is probably not running &mdash; if this doesn't change soon, tell an administrator. Developers: run <code class="rounded bg-amber-100 px-1">php artisan queue:listen</code>, or <code class="rounded bg-amber-100 px-1">composer dev</code>.</p>
            </div>
            <a href="{{ $backUrl }}" class="mt-6 inline-block text-sm font-medium text-cherry-700">Back to submission</a>
        </div>
    @elseif ($check->isFailed())
        <div class="mx-auto max-w-xl px-4 py-16 text-center">
            <h3 class="text-lg font-semibold text-slate-900">The similarity check didn't finish</h3>
            <p class="mt-2 text-sm text-rose-700">{{ $check->error ?: 'Something went wrong while checking the chapters.' }}</p>
            <div class="mt-6 flex items-center justify-center gap-4">
                <form method="POST" action="{{ route('submissions.similarity.store', $submission) }}">
                    @csrf
                    <button type="submit" class="rounded-xl bg-cherry-700 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-cherry-800">Run again</button>
                </form>
                <a href="{{ $backUrl }}" class="text-sm font-medium text-cherry-700">Back to submission</a>
            </div>
        </div>
    @else
        @php
            $sources = $report['sources'];
            // What the Alpine component needs to know about each source to recolour highlights.
            $sourceMeta = collect($sources)->mapWithKeys(fn ($source) => [$source['id'] => ['color' => $source['color'], 'rank' => $source['rank']]])->all();
            $scoreLabel = $check->score > 0 && $check->score < 1 ? '<1' : number_format($check->score, 0);

            // Segments are joined with no whitespace between them on purpose — a stray space
            // before a comma that follows a highlight would render as "phrase ,". Ids, ranks and
            // colours are ints and palette constants; the text is escaped.
            $renderParagraph = function (array $segments): string {
                $html = '';

                foreach ($segments as $segment) {
                    if ($segment['source'] === null) {
                        $html .= e($segment['text']);

                        continue;
                    }

                    $ids = '['.implode(',', array_map('intval', $segment['sources'])).']';

                    $html .= '<mark class="sim-mark" data-sources="'.e(implode(' ', $segment['sources'])).'" style="--c: '.e($segment['color']).'"'
                        .' :class="markClass('.$ids.')" :style="markStyle('.$ids.')" @click="markClick('.$ids.')">'
                        .e($segment['text']).'<sup x-text="markRank('.$ids.', '.(int) $segment['rank'].')">'.(int) $segment['rank'].'</sup></mark>';
                }

                return $html;
            };
        @endphp

        <style>
            .sim-mark { background: color-mix(in srgb, var(--c) 18%, transparent); border-bottom: 2px solid var(--c); border-radius: 2px; color: inherit; cursor: pointer; padding: 0 1px; transition: background-color .15s ease, opacity .15s ease; }
            .sim-mark:hover { background: color-mix(in srgb, var(--c) 30%, transparent); }
            .sim-mark.sim-active { background: color-mix(in srgb, var(--c) 42%, transparent); box-shadow: 0 0 0 2px color-mix(in srgb, var(--c) 35%, transparent); }
            .sim-mark.sim-dim { background: transparent; border-bottom-color: color-mix(in srgb, var(--c) 30%, transparent); }
            .sim-mark sup { color: var(--c); font-size: .65em; font-weight: 700; margin-left: 1px; }
            .sim-mark.sim-dim sup { opacity: .35; }
            .sim-badge { align-items: center; border-radius: 9999px; color: #fff; display: inline-flex; flex-shrink: 0; font-size: .75rem; font-weight: 700; height: 1.5rem; justify-content: center; min-width: 1.5rem; padding: 0 .35rem; }
        </style>

        <script>
            // Defined here, before Alpine starts (app.js is a deferred module), so
            // x-data="similarityReport(...)" below can find it.
            //
            // `sources` is { id: { color, rank } }. Every highlight lists the ids of ALL sources
            // whose match overlaps it (the one that won its default colour first), so a source that
            // lost every word to a better match can still light up its own text when selected.
            window.similarityReport = (sources) => ({
                active: null,

                // The source a highlight should currently be drawn as: the selected one if it
                // covers this text, else null (= its default, best-match colour).
                shown(ids) {
                    return this.active !== null && ids.includes(this.active) ? this.active : null;
                },

                markClass(ids) {
                    return {
                        'sim-active': this.active !== null && ids.includes(this.active),
                        'sim-dim': this.active !== null && ! ids.includes(this.active),
                    };
                },

                markStyle(ids) {
                    const id = this.shown(ids);

                    return id === null ? {} : { '--c': sources[id].color };
                },

                markRank(ids, fallback) {
                    const id = this.shown(ids);

                    return id === null ? fallback : sources[id].rank;
                },

                markClick(ids) {
                    this.select(this.shown(ids) ?? ids[0]);
                },

                select(id) {
                    this.active = this.active === id ? null : id;

                    if (this.active === null) {
                        return;
                    }

                    this.$nextTick(() => {
                        this.$root.querySelector('.sim-mark[data-sources~="' + id + '"]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        this.$root.querySelector('[data-source-row="' + id + '"]')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    });
                },
            });
        </script>

        <div x-data="similarityReport(@js($sourceMeta))" class="mx-auto grid max-w-[96rem] gap-6 px-4 py-6 sm:px-6 lg:grid-cols-[minmax(0,1fr)_25rem]">
            <article class="min-w-0 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-10">
                <p class="text-xs text-slate-400">
                    Checked {{ $check->completed_at->format('M j, Y g:i A') }} &middot; {{ number_format($check->word_count) }} words
                    &middot; the text as it was when the check ran
                </p>

                @foreach ($report['sections'] as $section)
                    <h3 class="mt-8 text-base font-semibold text-slate-900 first:mt-4">{{ $section['label'] }}</h3>
                    @foreach ($section['paragraphs'] as $segments)
                        <p class="mt-3 text-[15px] leading-7 text-slate-800">{!! $renderParagraph($segments) !!}</p>
                    @endforeach
                @endforeach
            </article>

            <aside class="lg:sticky lg:top-4 lg:self-start">
                <div class="max-h-none overflow-y-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 lg:max-h-[calc(100vh-2rem)]">
                    <div class="p-5">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Similarity index</div>
                        <div class="mt-1 flex items-end gap-3">
                            <span class="text-4xl font-semibold {{ $bandText[$check->band()] }}">{{ $scoreLabel }}%</span>
                            <span class="pb-1 text-sm text-slate-500">{{ number_format($check->matched_words) }} of {{ number_format($check->word_count) }} words match</span>
                        </div>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full {{ $bandBar[$check->band()] }}" style="width: {{ min(100, max(0, (float) $check->score)) }}%"></div>
                        </div>
                        <p class="mt-3 text-xs text-slate-500">Each word counts once, however many sources it matches. Quotations and common phrases match too &mdash; a high index is a prompt to look closer, not proof of plagiarism.</p>
                    </div>

                    @if (! empty($check->warnings))
                        <div class="border-t border-slate-100 bg-amber-50 p-4">
                            <div class="text-xs font-semibold uppercase tracking-wide text-amber-700">Partial results</div>
                            <ul class="mt-2 list-inside list-disc space-y-1 text-xs text-amber-800">
                                @foreach ($check->warnings as $warning)
                                    <li>{{ $warning }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="border-t border-slate-100 p-3">
                        <div class="px-2 pb-2 pt-1 text-xs font-semibold uppercase tracking-wide text-slate-400">
                            {{ count($sources) }} {{ str('source')->plural(count($sources)) }} found
                        </div>

                        @if ($sources === [])
                            <div class="px-2 pb-3 pt-1 text-sm text-slate-600">
                                <p class="font-medium text-slate-900">No matching sources found.</p>
                                <p class="mt-1 text-xs text-slate-500">That means nothing matched in the sources that were searched &mdash; it can't rule out reworded text or work published where this couldn't look.</p>
                            </div>
                        @else
                            <ul class="grid gap-1">
                                @foreach ($sources as $source)
                                    <li data-source-row="{{ $source['id'] }}">
                                        <button
                                            type="button"
                                            @click="select({{ $source['id'] }})"
                                            :class="active === {{ $source['id'] }} ? 'bg-slate-50 ring-2 ring-slate-300' : 'hover:bg-slate-50'"
                                            class="flex w-full items-start gap-3 rounded-xl p-3 text-left"
                                        >
                                            <span class="sim-badge" style="background: {{ $source['color'] }}">{{ $source['rank'] }}</span>
                                            <span class="min-w-0 flex-1">
                                                <span class="line-clamp-2 block text-sm font-medium text-slate-900">{{ $source['title'] }}</span>
                                                @if ($source['host'])
                                                    <span class="mt-0.5 block text-xs text-slate-500">{{ $source['host'] }}</span>
                                                @endif
                                            </span>
                                            <span class="shrink-0 text-right">
                                                <span class="block text-sm font-semibold text-slate-900">{{ number_format($source['percent'], $source['percent'] < 10 ? 1 : 0) }}%</span>
                                                <span class="block text-xs text-slate-400">{{ $source['words'] }} words</span>
                                            </span>
                                        </button>
                                        @if ($source['url'])
                                            <a x-show="active === {{ $source['id'] }}" x-cloak href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer nofollow" class="mb-1 ml-12 inline-flex items-center gap-1 text-xs font-medium text-cherry-700 underline hover:no-underline">
                                                Open source
                                                <svg class="h-3 w-3" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 13 13 7M8 7h5v5" /></svg>
                                            </a>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <details class="border-t border-slate-100 p-4 text-xs text-slate-500">
                        <summary class="cursor-pointer font-medium text-slate-600">What this check can't see</summary>
                        <ul class="mt-2 list-inside list-disc space-y-1">
                            <li>Other ePrism submissions &mdash; the check looks at the web only.</li>
                            <li>Reworded text &mdash; only matching wording is detected.</li>
                            <li>Paywalled journals and pages behind a login, including most of ResearchGate.</li>
                            <li>Google Scholar (it has no way to be searched automatically) and PDF files found on the web.</li>
                            <li>Anything the search engines don't surface for an exact phrase from the text.</li>
                            <li>Reference lists and table chapters, which are left out of the check.</li>
                        </ul>
                    </details>
                </div>
            </aside>
        </div>
    @endif
</x-app-layout>

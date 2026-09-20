{{--
    The submission page's entry point to the similarity checker (see App\Similarity and
    SimilarityCheckController): shows what a check would search, the latest result at a
    glance, and the button that starts one. The full highlighted report lives on its own page
    (researcher/submissions/similarity.blade.php). A sibling of the draft form, never inside
    it — a nested <form> is invalid HTML and would break the page's single draft form.

    Expects: $submission, $latestSimilarityCheck (?SimilarityCheck), $similaritySources
    (SourceRegistry::status()).
--}}
@php
    $latest = $latestSimilarityCheck ?? null;
    $inProgress = $latest?->isActive() ?? false;

    $bandClasses = [
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'amber' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'orange' => 'bg-orange-50 text-orange-700 ring-orange-200',
        'rose' => 'bg-rose-50 text-rose-700 ring-rose-200',
        'slate' => 'bg-slate-50 text-slate-600 ring-slate-200',
    ];
    $sourceStateClasses = [
        'ready' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'unconfigured' => 'bg-slate-50 text-slate-500 ring-slate-200',
        'disabled' => 'bg-slate-50 text-slate-400 ring-slate-200',
    ];
    $sourceStateLabels = ['ready' => 'Ready', 'unconfigured' => 'Not set up', 'disabled' => 'Off'];
@endphp

<div id="similarity-check" class="app-card bg-white p-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="max-w-2xl">
            <h3 class="text-lg font-semibold text-slate-900">Similarity Check</h3>
            <p class="mt-1 text-sm text-slate-500">
                Search the web for passages copied from other sources, then open a report that highlights every matching passage and shows where it came from.
            </p>
        </div>

        @if ($inProgress)
            <a href="{{ route('submissions.similarity.show', [$submission, $latest]) }}" class="shrink-0 rounded-xl bg-cherry-700 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-cherry-800">Check in progress &rarr;</a>
        @else
            <form method="POST" action="{{ route('submissions.similarity.store', $submission) }}" class="shrink-0">
                @csrf
                <button type="submit" class="rounded-xl bg-cherry-700 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-cherry-800">{{ $latest ? 'Run again' : 'Run similarity check' }}</button>
            </form>
        @endif
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-2">
        <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Searches</span>
        @foreach ($similaritySources as $source)
            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 {{ $sourceStateClasses[$source['state']] }}">
                {{ $source['label'] }}
                <span class="opacity-70">&middot; {{ $sourceStateLabels[$source['state']] }}</span>
            </span>
        @endforeach
    </div>

    @if ($latest)
        <div class="mt-5 flex flex-wrap items-center justify-between gap-4 app-card-inset p-4 ring-1 ring-slate-200">
            @if ($latest->isCompleted())
                @php($matchCount = $latest->matches()->count())
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full text-lg font-semibold ring-1 {{ $bandClasses[$latest->band()] }}">{{ number_format($latest->score, 0) }}%</div>
                    <div>
                        <div class="text-sm font-medium text-slate-900">Similarity index</div>
                        <div class="text-xs text-slate-500">
                            {{ $matchCount }} {{ str('source')->plural($matchCount) }} found &middot; checked {{ $latest->completed_at->format('M j, Y g:i A') }}
                        </div>
                    </div>
                </div>
                <a href="{{ route('submissions.similarity.show', [$submission, $latest]) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-white">View report</a>
            @elseif ($inProgress)
                <div class="flex items-center gap-3 text-sm text-slate-600">
                    <span class="doc-spinner"></span>
                    Checking your chapters&hellip; this can take a minute or two.
                </div>
            @else
                <div class="text-sm text-rose-700">{{ $latest->error ?: 'The last similarity check did not finish.' }}</div>
            @endif
        </div>
    @endif

    <p class="mt-4 text-xs text-slate-500">
        This is a screening aid, not proof of anything: it finds exact-wording overlap only on the web (not against other ePrism submissions), so a low score doesn't guarantee originality and a high one isn't proof of plagiarism. Short phrases from your chapters are searched through this organisation's SearXNG server, which forwards them to public search engines such as Google and DuckDuckGo; nothing is published or indexed.
    </p>
</div>

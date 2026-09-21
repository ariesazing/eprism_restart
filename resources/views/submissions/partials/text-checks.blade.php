{{--
    The grammar and similarity checks, for the reviewer and admin views of a submission (a
    researcher has their own richer entry points — the chapters page and researcher/submissions/
    partials/similarity-panel.blade.php). Two buttons:

      - "Check grammar" opens the shared grammar-review modal (researcher/submissions/partials/
        grammar-review-modal.blade.php, which the PAGE must include once) for this submission's
        chapters — read-only, nothing here can change the researcher's text.
      - "Similarity check" starts a web similarity check that runs in the background; once one
        exists this becomes a link to its report (or to its progress while it runs).

    Both are advisory only. A similarity check belongs to whoever ran it, so this shows the
    signed-in user's own latest check, never someone else's.

    Expects: $submission, $role ('reviewer' | 'admin' — picks the grammar route, which is
    authorized per role). Sits inside the page's own markup, never inside another <form>.
--}}
@php
    $user = auth()->user();

    $grammarRoute = $role === 'admin' ? 'admin.submissions.sections.grammar-review' : 'reviewer.submissions.sections.grammar-review';

    // The manuscript engine keeps one whole document rather than per-chapter ones, so there is no
    // chapter to review there (ChapterGrammarReviewController refuses it too).
    $grammarChapters = $submission->usesManuscript()
        ? []
        : collect($submission->template()->sections)
            ->filter(fn ($definition) => $definition->type === 'rich_text')
            ->map(function ($definition) use ($submission, $grammarRoute) {
                $section = $submission->sections->firstWhere('section_key', $definition->key);

                return $section ? [
                    'key' => $definition->key,
                    'label' => $definition->label,
                    'url' => route($grammarRoute, [$submission, $section]),
                    'forceSaveUrl' => '',
                ] : null;
            })
            ->filter()
            ->values()
            ->all();

    $myChecks = $submission->relationLoaded('similarityChecks')
        ? $submission->similarityChecks
        : $submission->similarityChecks()->where('requested_by', $user->id)->get();
    $latestCheck = $myChecks->where('requested_by', $user->id)->sortByDesc('id')->first();
    $checkInProgress = $latestCheck?->isActive() ?? false;
@endphp

<div class="flex flex-wrap items-center gap-2" x-data>
    @if ($grammarChapters !== [])
        <button
            type="button"
            @click="$dispatch('grammar-review-open', { chapters: @js($grammarChapters) })"
            class="rounded-full border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"
        >Check grammar</button>
    @endif

    @if ($checkInProgress)
        <a href="{{ route('submissions.similarity.show', [$submission, $latestCheck]) }}" class="rounded-full border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Similarity check in progress &rarr;</a>
    @else
        @if ($latestCheck?->isCompleted())
            <a href="{{ route('submissions.similarity.show', [$submission, $latestCheck]) }}" class="rounded-full border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Similarity report ({{ number_format($latestCheck->score, 0) }}%)</a>
        @endif

        <form method="POST" action="{{ route('submissions.similarity.store', $submission) }}">
            @csrf
            <button type="submit" class="rounded-full border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">{{ $latestCheck ? 'Run similarity check again' : 'Run similarity check' }}</button>
        </form>
    @endif
</div>

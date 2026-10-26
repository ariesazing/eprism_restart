<div class="app-card bg-white p-6" x-data="{ readinessOpen: false }">
    <div class="flex items-center justify-between gap-3">
        <h3 class="text-lg font-semibold text-slate-900">Submission Readiness</h3>
        @if ($data['readiness']->count() > 1)
        <button
            type="button"
            @click="readinessOpen = ! readinessOpen"
            :aria-expanded="readinessOpen.toString()"
            aria-controls="researcher-submission-readiness-list"
            :aria-label="readinessOpen ? 'Show fewer submissions' : 'Show all submissions'"
            class="shrink-0 rounded-lg p-2 text-cherry-700 hover:bg-cherry-50 focus:outline-none focus:ring-2 focus:ring-cherry-500 focus:ring-offset-2"
        >
            <svg x-show="! readinessOpen" x-cloak class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.22 7.47a.75.75 0 0 1 1.06 0L10 11.19l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.53a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
            </svg>
            <svg x-show="readinessOpen" x-cloak class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M14.78 12.53a.75.75 0 0 1-1.06 0L10 8.81l-3.72 3.72a.75.75 0 1 1-1.06-1.06l4.25-4.25a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06Z" clip-rule="evenodd" />
            </svg>
        </button>
        @endif
    </div>
    <div id="researcher-submission-readiness-list" class="mt-4 grid gap-3">
        @forelse ($data['readiness'] as $submissionId => $assessment)
            @php
                $submission = $data['submissions']->firstWhere('id', $submissionId);
                $totalItems = $assessment['sections']['total'] + $assessment['attachments']['total'];
                $doneItems = $assessment['sections']['done'] + $assessment['attachments']['done'];
                $percent = $totalItems > 0 ? (int) round(($doneItems / $totalItems) * 100) : 0;
            @endphp
            <div @unless ($loop->first) x-show="readinessOpen" x-cloak @endunless class="app-card-inset p-4 text-sm">
                <div class="flex items-center justify-between gap-2">
                    <span class="font-medium text-slate-900">{{ $submission->title }}</span>
                    <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $assessment['ready'] ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                        {{ $assessment['ready'] ? 'Ready to submit' : 'Incomplete' }}
                    </span>
                </div>

                <div class="mt-3">
                    <div class="flex items-center justify-between text-xs text-slate-500">
                        <span>{{ $assessment['sections']['done'] }}/{{ $assessment['sections']['total'] }} chapters completed</span>
                        <span class="font-medium text-slate-600">{{ $percent }}%</span>
                    </div>
                    <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-slate-200">
                        <div class="h-full rounded-full {{ $assessment['ready'] ? 'bg-emerald-500' : 'bg-amber-500' }}" style="width: {{ $percent }}%"></div>
                    </div>
                </div>

                <div class="mt-2 text-xs text-slate-500">
                    Required attachments: {{ $assessment['attachments']['done'] }}/{{ $assessment['attachments']['total'] }}
                </div>

                @if (! $assessment['ready'])
                    <div class="mt-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Missing Requirements</p>
                        <ul class="mt-1 list-inside list-disc text-xs text-slate-500">
                            @foreach ($assessment['sections']['missing'] as $missing)
                                <li>{{ $missing['label'] }}</li>
                            @endforeach
                            @foreach ($assessment['attachments']['missing'] as $missing)
                                <li>{{ $missing }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <a href="{{ route('submissions.show', $submission) }}" class="mt-3 inline-flex text-sm font-medium text-cherry-700 hover:text-cherry-800">Continue Editing &rarr;</a>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500">No drafts or returned submissions to assess.</div>
        @endforelse
    </div>
</div>

<div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <h3 class="text-lg font-semibold text-slate-900">Submission Readiness</h3>
    <div class="mt-4 grid gap-3">
        @forelse ($data['readiness'] as $submissionId => $assessment)
            @php
                $submission = $data['submissions']->firstWhere('id', $submissionId);
                $totalItems = $assessment['sections']['total'] + $assessment['attachments']['total'];
                $doneItems = $assessment['sections']['done'] + $assessment['attachments']['done'];
                $percent = $totalItems > 0 ? (int) round(($doneItems / $totalItems) * 100) : 0;
            @endphp
            <div class="rounded-xl bg-slate-50 p-4 text-sm">
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

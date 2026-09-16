@php
    $routeName = request()->route()?->getName() ?? '';
    $crumbs = [['label' => 'Dashboard', 'url' => route('dashboard')]];
    $pages = [
        'repository.index' => 'Repository',
        'profile.edit' => 'Profile',
        'admin.users.index' => 'Users',
        'admin.reports' => 'Reports',
        'admin.activity.index' => 'Activity Log',
        'admin.organizational-units.index' => 'Organizational Units',
        'admin.submission-timeline.index' => 'Submission Timeline',
    ];

    if (isset($pages[$routeName])) {
        $crumbs[] = ['label' => $pages[$routeName], 'url' => null];
    } elseif (str_starts_with($routeName, 'admin.document-templates.')) {
        $crumbs[] = ['label' => 'Document Templates', 'url' => route('admin.document-templates.index')];
        if ($routeName !== 'admin.document-templates.index') {
            $crumbs[] = ['label' => 'Edit Template', 'url' => null];
        }
    } else {
        $prefix = match (true) {
            str_starts_with($routeName, 'reviewer.submissions.') => 'reviewer.submissions',
            str_starts_with($routeName, 'admin.submissions.') => 'admin.submissions',
            str_starts_with($routeName, 'submissions.') => 'submissions',
            default => null,
        };

        if ($prefix) {
            $label = match ($prefix) {
                'reviewer.submissions' => 'Reviewer Queue',
                'admin.submissions' => 'Reviewer Assignment',
                default => 'My Submissions',
            };
            $crumbs[] = ['label' => $label, 'url' => route($prefix.'.index')];
            $submission = request()->route('submission');

            if ($submission instanceof \App\Models\ResearchSubmission) {
                $crumbs[] = [
                    'label' => $submission->reference_code ?: $submission->title,
                    'url' => $prefix === 'admin.submissions' ? null : route($prefix.'.show', $submission),
                ];
                if ($routeName !== $prefix.'.show') {
                    $crumbs[] = ['label' => str_ends_with($routeName, '.chapters') ? 'Edit Chapters' : 'Manuscript', 'url' => null];
                }
            } elseif ($routeName === 'submissions.create') {
                $crumbs[] = ['label' => 'New Submission', 'url' => null];
            }
        }
    }
@endphp

<nav aria-label="Breadcrumb" class="min-w-0 flex-1">
    <ol class="flex min-w-0 flex-wrap items-center gap-1 text-xs sm:gap-1.5 sm:text-sm">
        @foreach ($crumbs as $crumb)
            <li class="flex min-w-0 items-center gap-1 sm:gap-1.5">
                @unless ($loop->first)
                    <svg aria-hidden="true" class="h-3.5 w-3.5 shrink-0 text-slate-300" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><path d="m7.5 5 5 5-5 5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                @endunless

                @if ($loop->last)
                    <span aria-current="page" title="{{ $crumb['label'] }}" class="max-w-[12rem] truncate rounded-lg bg-cherry-50 px-2.5 py-1.5 font-semibold text-cherry-800 sm:max-w-xs">{{ $crumb['label'] }}</span>
                @elseif ($crumb['url'])
                    <a href="{{ $crumb['url'] }}" title="{{ $crumb['label'] }}" class="max-w-[10rem] truncate rounded-lg px-2 py-1.5 font-medium text-slate-500 hover:bg-slate-100 hover:text-cherry-700 sm:max-w-xs">{{ $crumb['label'] }}</a>
                @else
                    <span class="max-w-[10rem] truncate px-2 py-1.5 text-slate-500">{{ $crumb['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>

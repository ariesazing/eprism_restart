@php
    $currentUser = auth()->user();
    $isResearcherTopbar = $currentUser && $currentUser->isResearcher() && $currentUser->isApproved();

    $topbarFeedback = collect();
    if ($isResearcherTopbar) {
        $topbarFeedback = \App\Models\DocumentComment::query()
            ->whereHas('submission', fn ($query) => $query->where('researcher_id', $currentUser->id))
            ->visibleToResearcher()
            ->with(['author:id,name', 'submission:id,title,reference_code'])
            ->latest()
            ->take(5)
            ->get();
    }
@endphp

<div class="border-b border-slate-200 bg-white">
    <div class="mx-auto flex h-14 max-w-[1920px] items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
        <p class="hidden text-xs font-medium uppercase tracking-[0.2em] text-slate-400 lg:block">
            e-PRISM &middot; Research Submission &amp; Review System
        </p>

        <div class="flex flex-1 items-center justify-end gap-3">
            @if ($isResearcherTopbar)
                <form method="GET" action="{{ route('submissions.index') }}" class="hidden max-w-xs flex-1 sm:block">
                    <label for="topbar-search" class="sr-only">Search submissions</label>
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input
                            id="topbar-search"
                            type="search"
                            name="search"
                            placeholder="Search submissions"
                            class="w-full rounded-xl border-slate-200 bg-slate-50 py-2 pl-9 pr-3 text-sm placeholder:text-slate-400 focus:border-red-300 focus:bg-white focus:ring-red-200"
                        >
                    </div>
                </form>

                <x-dropdown align="right" width="w-80">
                    <x-slot name="trigger">
                        <button type="button" class="relative flex h-9 w-9 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100" title="Reviewer feedback">
                            <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                            @if ($topbarFeedback->isNotEmpty())
                                <span class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-red-600"></span>
                            @endif
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <div class="max-h-96 overflow-y-auto p-2">
                            <p class="px-2 py-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Reviewer Feedback</p>
                            @forelse ($topbarFeedback as $comment)
                                <a href="{{ route('submissions.show', $comment->submission) }}" class="block rounded-lg px-2 py-2 hover:bg-slate-50">
                                    <p class="text-xs font-medium text-slate-800">{{ $comment->author->name ?? 'Reviewer' }} &middot; {{ $comment->submission->reference_code }}</p>
                                    <p class="mt-0.5 truncate text-xs text-slate-500">{{ $comment->body }}</p>
                                </a>
                            @empty
                                <p class="px-2 py-4 text-center text-xs text-slate-400">No reviewer comments requiring action.</p>
                            @endforelse
                        </div>
                    </x-slot>
                </x-dropdown>
            @endif

            @if ($currentUser)
                <div class="hidden rounded-full bg-red-50 px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-[0.2em] text-red-700 sm:block">
                    {{ $currentUser->role->label() }}
                </div>

                <x-dropdown align="right" width="w-64">
                    <x-slot name="trigger">
                        <button type="button" class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-800 text-sm font-semibold text-white hover:bg-slate-700" title="{{ $currentUser->name }}">
                            {{ strtoupper(substr($currentUser->name, 0, 1)) }}
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <div class="px-4 py-3">
                            <p class="text-sm font-medium text-slate-800">{{ $currentUser->name }}</p>
                            <p class="text-xs text-slate-500">{{ $currentUser->email }}</p>
                        </div>
                        <div class="border-t border-slate-100 py-1">
                            <x-dropdown-link :href="route('profile.edit')">Profile</x-dropdown-link>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">Log Out</x-dropdown-link>
                            </form>
                        </div>
                    </x-slot>
                </x-dropdown>
            @endif
        </div>
    </div>
</div>

@php
    $currentUser = auth()->user();
    $isResearcherTopbar = $currentUser && $currentUser->isResearcher();

    // One merged, chronological bell feed rather than two separate dropdowns — reviewer
    // feedback (comments) and system notifications are different models with different
    // "read" semantics (comments have none; notifications do), so each item stays
    // normalized to a common shape here and only branches on `type` when rendering.
    $topbarItems = collect();

    if ($isResearcherTopbar) {
        $topbarItems = $topbarItems->concat(
            \App\Models\DocumentComment::query()
                ->whereHas('submission', fn ($query) => $query->where('researcher_id', $currentUser->id))
                ->visibleToResearcher()
                ->with(['author:id,name', 'submission:id,title,reference_code'])
                ->latest()
                ->take(5)
                ->get()
                ->map(fn ($comment) => [
                    'type' => 'comment',
                    'url' => route('submissions.show', $comment->submission),
                    'title' => ($comment->author->name ?? 'Reviewer').' · '.$comment->submission->reference_code,
                    'body' => $comment->body,
                    'timestamp' => $comment->created_at,
                ])
        );
    }

    if ($currentUser) {
        $topbarItems = $topbarItems->concat(
            $currentUser->unreadNotifications()->latest()->take(5)->get()
                ->map(fn ($notification) => [
                    'type' => 'notification',
                    'notification' => $notification,
                    'title' => $notification->data['title'] ?? 'Notification',
                    'body' => $notification->data['reference_code'] ?? '',
                    'timestamp' => $notification->created_at,
                ])
        );
    }

    $topbarItems = $topbarItems->sortByDesc('timestamp')->take(8)->values();
@endphp

<div class="research-topbar border-b border-slate-200 bg-white">
    <div class="mx-auto flex min-h-14 max-w-[1920px] items-center justify-between gap-2 px-4 py-2 sm:gap-4 sm:px-6 lg:px-8">
        <x-breadcrumbs />

        <div class="flex shrink-0 items-center justify-end gap-3">
            @if ($currentUser)
                <form method="GET" action="{{ route($currentUser?->isAdmin() ? 'admin.submissions.index' : ($currentUser?->isReviewer() ? 'reviewer.submissions.index' : 'submissions.index')) }}" class="topbar-search-form hidden w-56 xl:block">
                    <label for="topbar-search" class="sr-only">Search submissions</label>
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input
                            id="topbar-search"
                            type="search"
                            name="search" required maxlength="255" oninput="this.setCustomValidity(this.value.trim() ? '' : 'Enter a search term.')"
                            placeholder="Search submissions"
                            class="w-full rounded-xl border-slate-200 bg-slate-50 py-2 pl-9 pr-3 text-sm placeholder:text-slate-400 focus:border-cherry-300 focus:bg-white focus:ring-cherry-200"
                        >
                    </div>
                </form>

            @endif

            @if ($currentUser)
                {{-- Wraps trigger + content together (not just the dropdown's content slot)
                     so the unread badge dot on the bell icon itself also live-updates — see
                     resources/js/live-refresh.js. --}}
                <div data-live-region="notifications">
                    <x-dropdown align="right" width="w-80">
                        <x-slot name="trigger">
                            <button type="button" class="relative flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-slate-800 hover:bg-slate-200" title="Notifications" aria-label="Notifications">
                                <svg class="h-5 w-5 text-slate-800" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                                @if ($topbarItems->isNotEmpty())
                                    <span class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-cherry-600"></span>
                                @endif
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            <div class="max-h-96 overflow-y-auto p-2">
                                <p class="px-2 py-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Notifications</p>
                                @forelse ($topbarItems as $item)
                                    @if ($item['type'] === 'notification')
                                        <form method="POST" action="{{ route('notifications.read', $item['notification']) }}">
                                            @csrf
                                            <button type="submit" class="block w-full rounded-lg px-2 py-2 text-left hover:bg-slate-50">
                                                <p class="text-xs font-medium text-slate-800">{{ $item['title'] }}</p>
                                                @if ($item['body'])
                                                    <p class="mt-0.5 truncate text-xs text-slate-500">{{ $item['body'] }}</p>
                                                @endif
                                            </button>
                                        </form>
                                    @else
                                        <a href="{{ $item['url'] }}" class="block rounded-lg px-2 py-2 hover:bg-slate-50">
                                            <p class="text-xs font-medium text-slate-800">{{ $item['title'] }}</p>
                                            <p class="mt-0.5 truncate text-xs text-slate-500">{{ $item['body'] }}</p>
                                        </a>
                                    @endif
                                @empty
                                    <p class="px-2 py-4 text-center text-xs text-slate-400">No new notifications.</p>
                                @endforelse
                            </div>
                        </x-slot>
                    </x-dropdown>
                </div>

                <div class="hidden rounded-full bg-cherry-50 px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-[0.2em] text-cherry-700 sm:block">
                    {{ $currentUser->role->label() }}
                </div>

                <div class="hidden lg:block">
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
                            <button type="button" @click="$dispatch('open-modal', 'global-report-issue-modal')" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-50">
                                <svg class="h-4 w-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                <span>Report an Issue</span>
                            </button>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">Log Out</x-dropdown-link>
                            </form>
                        </div>
                    </x-slot>
                </x-dropdown>
                </div>
            @endif
        </div>
    </div>

    @if ($currentUser)
        <x-modal name="global-report-issue-modal" max-width="md" focusable>
            <form method="POST" action="{{ route('concerns.store-global') }}" class="p-6">
                @csrf
                <div class="flex items-center gap-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </span>
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Report an Issue or Concern</h3>
                        <p class="text-xs text-slate-500">Reach out to the research committee administrators.</p>
                    </div>
                </div>

                <div class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">Category</label>
                        <select name="category" required class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-cherry-500 focus:ring-cherry-500">
                            <option value="general">General Inquiry</option>
                            <option value="technical">Technical / System Problem</option>
                            <option value="rubric">Evaluation / Rubric Question</option>
                            <option value="deadline">Timeline / Deadline Question</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">Subject</label>
                        <input type="text" name="subject" required maxlength="255" placeholder="Brief summary of your issue" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-cherry-500 focus:ring-cherry-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">Message</label>
                        <textarea name="message" required rows="4" maxlength="5000" placeholder="Please describe the issue or inquiry in detail..." class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-cherry-500 focus:ring-cherry-500"></textarea>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" @click="$dispatch('close-modal', 'global-report-issue-modal')" class="rounded-xl border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="rounded-xl bg-cherry-700 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-cherry-800">Submit Concern</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>

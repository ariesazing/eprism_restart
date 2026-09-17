<!-- Mobile top bar -->
<div class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3 lg:hidden">
    <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
        <x-prism-brand />
    </a>
    <button aria-label="Open navigation" aria-controls="primary-sidebar" :aria-expanded="mobileOpen.toString()" @click="mobileOpen = true" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 focus:outline-none">
        <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
    </button>
</div>

<!-- Mobile overlay -->
<div
    :class="{ 'hidden': ! mobileOpen }"
    class="hidden fixed inset-0 z-40 bg-slate-900/50 lg:hidden"
    @click="mobileOpen = false"
></div>

<!-- Sidebar -->
<aside
    id="primary-sidebar"
    @keydown.escape.window="mobileOpen = false"
    :data-collapsed="collapsed.toString()"
    data-sidebar
    class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-slate-200/80 bg-white shadow-[4px_0_24px_-16px_rgba(15,23,42,0.18)] transition-[transform,width] duration-200 ease-in-out motion-reduce:transition-none lg:translate-x-0"
    :class="[mobileOpen ? 'translate-x-0' : '-translate-x-full', collapsed ? 'lg:w-20' : 'lg:w-72']"
>
        <div class="reference-sidebar-art" aria-hidden="true"></div>
    <!-- Collapse toggle (desktop only) -->
    <button
        @click="collapsed = ! collapsed"
        class="absolute -right-3 top-6 z-10 hidden h-6 w-6 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-400 shadow-sm hover:text-cherry-700 focus:outline-none lg:flex"
        :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
        :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
        :aria-expanded="(!collapsed).toString()"
    >
        <svg class="h-3.5 w-3.5 transition-transform duration-200" :class="collapsed ? 'rotate-180' : ''" stroke="currentColor" fill="none" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
    </button>

    <div class="sidebar-brand flex shrink-0 items-center justify-between border-b border-slate-100 px-6 py-6" :class="collapsed ? 'lg:justify-center lg:px-3' : ''">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 overflow-hidden">
            <x-prism-brand />
        </a>
        <button aria-label="Close navigation" @click="mobileOpen = false" class="rounded-md p-1 text-slate-400 hover:bg-slate-100 focus:outline-none lg:hidden">
            <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <nav aria-label="Main navigation" class="sidebar-navigation min-h-0 flex-1 space-y-1 overflow-y-auto overflow-x-hidden px-3 py-5">
        <div class="sidebar-section" :class="collapsed ? 'lg:invisible' : ''">{{ __('Workspace') }}</div>
        <x-sidebar-link :title="__('Dashboard')" :aria-label="__('Dashboard')" class="whitespace-nowrap" :href="route('dashboard')" :active="request()->routeIs('dashboard')">
            <svg aria-hidden="true" class="h-5 w-5 shrink-0" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect></svg>
            <span :class="collapsed ? 'lg:hidden' : ''">{{ __('Dashboard') }}</span>
        </x-sidebar-link>

        @auth
            @if (Auth::user()->isResearcher())
                <x-sidebar-link :title="__('My Submissions')" :aria-label="__('My Submissions')" class="whitespace-nowrap" :href="route('submissions.index')" :active="request()->routeIs('submissions.*')">
                    <svg aria-hidden="true" class="h-5 w-5 shrink-0" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3h5l5 5v11a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"></path><polyline points="13 3 13 8 18 8"></polyline><line x1="9.5" y1="13" x2="15" y2="13"></line><line x1="9.5" y1="16.5" x2="15" y2="16.5"></line></svg>
                    <span :class="collapsed ? 'lg:hidden' : ''">{{ __('My Submissions') }}</span>
                </x-sidebar-link>
            @endif
        @endauth

        <x-sidebar-link :title="__('Repository')" :aria-label="__('Repository')" class="whitespace-nowrap" :href="route('repository.index')" :active="request()->routeIs('repository.index')">
            <svg aria-hidden="true" class="h-5 w-5 shrink-0" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6.5c-1.5-1.2-3.6-2-6-2-.7 0-1.4.06-2 .2v13.5c.6-.14 1.3-.2 2-.2 2.4 0 4.5.8 6 2"></path><path d="M12 6.5c1.5-1.2 3.6-2 6-2 .7 0 1.4.06 2 .2v13.5c-.6-.14-1.3-.2-2-.2-2.4 0-4.5.8-6 2"></path><line x1="12" y1="6.5" x2="12" y2="20"></line></svg>
            <span :class="collapsed ? 'lg:hidden' : ''">{{ __('Repository') }}</span>
        </x-sidebar-link>

        @auth
            @if (Auth::user()->isReviewer())
                <x-sidebar-link :title="__('Reviewer Queue')" :aria-label="__('Reviewer Queue')" class="whitespace-nowrap" :href="route('reviewer.submissions.index')" :active="request()->routeIs('reviewer.submissions.*')">
                    <svg aria-hidden="true" class="h-5 w-5 shrink-0" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4h6a1 1 0 0 1 1 1v1H8V5a1 1 0 0 1 1-1z"></path><rect x="6" y="5" width="12" height="16" rx="1.5"></rect><polyline points="9 13 11 15 15 10.5"></polyline></svg>
                    <span :class="collapsed ? 'lg:hidden' : ''">{{ __('Reviewer Queue') }}</span>
                </x-sidebar-link>
            @endif

            @if (Auth::user()->isAdmin())
                <div class="sidebar-section !mt-6" :class="collapsed ? 'lg:invisible' : ''">{{ __('Administration') }}</div>
                @php
                    // Same predicate AdminSubmissionController::index()'s ?reviewer=unassigned
                    // filter already uses, so the dot and the filtered view it points at agree.
                    $needsReviewerAssignment = \App\Models\ResearchSubmission::query()
                        ->where('status', '!=', \App\Enums\SubmissionStatus::DRAFT->value)
                        ->whereDoesntHave('reviewers')
                        ->exists();
                @endphp
                <x-sidebar-link :title="__('Users')" :aria-label="__('Users')" class="whitespace-nowrap" :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                    <svg aria-hidden="true" class="h-5 w-5 shrink-0" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"></circle><path d="M4 20c0-3 2.5-5.5 5.5-5.5S15 17 15 20"></path><circle cx="17" cy="9" r="2.4"></circle><path d="M15.2 14.7c2.3.3 4.3 2.5 4.3 5.3"></path></svg>
                    <span :class="collapsed ? 'lg:hidden' : ''">{{ __('Users') }}</span>
                </x-sidebar-link>
                <x-sidebar-link :title="__('A submission needs a reviewer assigned')" :aria-label="__('A submission needs a reviewer assigned')" class="whitespace-nowrap" :href="route('admin.submissions.index')" :active="request()->routeIs('admin.submissions.*') || request()->routeIs('admin.reviews.*')">
                    <svg aria-hidden="true" class="h-5 w-5 shrink-0" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.5-3 8-7 9-4-1-7-4.5-7-9V6z"></path><polyline points="9 12 11 14 15 9.5"></polyline></svg>
                    @if ($needsReviewerAssignment)
                        <span class="absolute left-4 top-1 h-2 w-2 rounded-full bg-cherry-600 ring-2 ring-white" title="{{ __('A submission needs a reviewer assigned') }}"></span>
                    @endif
                    <span :class="collapsed ? 'lg:hidden' : ''">{{ __('Reviewer Assignment') }}</span>
                </x-sidebar-link>
                <x-sidebar-link :title="__('Reports')" :aria-label="__('Reports')" class="whitespace-nowrap" :href="route('admin.reports')" :active="request()->routeIs('admin.reports')">
                    <svg aria-hidden="true" class="h-5 w-5 shrink-0" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="20" x2="20" y2="20"></line><rect x="6" y="12" width="3" height="8"></rect><rect x="11" y="8" width="3" height="12"></rect><rect x="16" y="4" width="3" height="16"></rect></svg>
                    <span :class="collapsed ? 'lg:hidden' : ''">{{ __('Reports') }}</span>
                </x-sidebar-link>
                <x-sidebar-link :title="__('Activity Log')" :aria-label="__('Activity Log')" class="whitespace-nowrap" :href="route('admin.activity.index')" :active="request()->routeIs('admin.activity.*')">
                    <svg aria-hidden="true" class="h-5 w-5 shrink-0" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.5"></circle><polyline points="12 7 12 12 15.5 14"></polyline></svg>
                    <span :class="collapsed ? 'lg:hidden' : ''">{{ __('Activity Log') }}</span>
                </x-sidebar-link>
                <x-sidebar-link :title="__('Document Templates')" :aria-label="__('Document Templates')" class="whitespace-nowrap" :href="route('admin.document-templates.index')" :active="request()->routeIs('admin.document-templates.*')">
                    <svg aria-hidden="true" class="h-5 w-5 shrink-0" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3h5l5 5v11a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"></path><polyline points="13 3 13 8 18 8"></polyline><line x1="9.5" y1="13" x2="15" y2="13"></line><line x1="9.5" y1="16.5" x2="12.5" y2="16.5"></line></svg>
                    <span :class="collapsed ? 'lg:hidden' : ''">{{ __('Document Templates') }}</span>
                </x-sidebar-link>
                <x-sidebar-link :title="__('Office / School Units')" :aria-label="__('Office / School Units')" class="whitespace-nowrap" :href="route('admin.organizational-units.index')" :active="request()->routeIs('admin.organizational-units.*')">
                    <svg aria-hidden="true" class="h-5 w-5 shrink-0" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"></path><path d="M5 21V7l7-4 7 4v14"></path><path d="M9 21v-6h6v6"></path></svg>
                    <span :class="collapsed ? 'lg:hidden' : ''">{{ __('Office / School Units') }}</span>
                </x-sidebar-link>
                <x-sidebar-link :title="__('Submission Timeline')" :aria-label="__('Submission Timeline')" class="whitespace-nowrap" :href="route('admin.submission-timeline.index')" :active="request()->routeIs('admin.submission-timeline.*')">
                    <svg aria-hidden="true" class="h-5 w-5 shrink-0" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 7V3m8 4V3M3 11h18M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"></path></svg>
                    <span :class="collapsed ? 'lg:hidden' : ''">{{ __('Submission Timeline') }}</span>
                </x-sidebar-link>
            @endif
        @endauth
    </nav>

    <div class="shrink-0 border-t border-slate-100 bg-slate-50/60 p-3">
        @auth
            <div class="flex items-center gap-3 rounded-xl border border-slate-200/70 bg-white p-3" :class="collapsed ? 'lg:hidden' : ''">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-cherry-100 text-sm font-semibold text-cherry-800" aria-hidden="true">{{ mb_strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}</div>
                <div class="min-w-0">
                    <div class="truncate text-sm font-semibold text-slate-800" title="{{ Auth::user()->name }}">{{ Auth::user()->name }}</div>
                    <div class="truncate text-xs text-slate-500" title="{{ Auth::user()->email }}">{{ Auth::user()->email }}</div>
                    <div class="mt-1 text-[10px] font-medium text-cherry-700">{{ Auth::user()->role->label() }} &middot; {{ Auth::user()->status->label() }}</div>
                </div>
            </div>

            <div class="mt-3 space-y-1" :class="collapsed ? 'lg:mt-0' : ''">
                <x-sidebar-link :title="__('Profile')" :aria-label="__('Profile')" class="whitespace-nowrap" :href="route('profile.edit')" :active="request()->routeIs('profile.edit')">
                    <svg aria-hidden="true" class="h-5 w-5 shrink-0" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8.5" r="3.25"></circle><path d="M5 20c0-3.5 3-6 7-6s7 2.5 7 6"></path></svg>
                    <span :class="collapsed ? 'lg:hidden' : ''">{{ __('Profile') }}</span>
                </x-sidebar-link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-sidebar-link :title="__('Log Out')" :aria-label="__('Log Out')" class="whitespace-nowrap" href="{{ route('logout') }}"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        <svg aria-hidden="true" class="h-5 w-5 shrink-0" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h4"></path><polyline points="15 16 20 12 15 8"></polyline><line x1="20" y1="12" x2="9" y2="12"></line></svg>
                        <span :class="collapsed ? 'lg:hidden' : ''">{{ __('Log Out') }}</span>
                    </x-sidebar-link>
                </form>
            </div>
        @else
            <div class="space-y-2 px-1 text-sm" :class="collapsed ? 'lg:hidden' : ''">
                <a href="{{ route('login') }}" class="block rounded-xl border border-slate-300 px-4 py-2 text-center font-medium text-slate-700 hover:bg-slate-50">Log in</a>
                <a href="{{ route('register') }}" class="block rounded-xl bg-cherry-700 px-4 py-2 text-center font-medium text-white shadow-sm hover:bg-cherry-800">Register</a>
            </div>
        @endauth
    </div>
</aside>

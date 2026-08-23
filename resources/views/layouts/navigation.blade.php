<!-- Mobile top bar -->
<div class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3 lg:hidden">
    <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
        <img src="{{ asset('images/logo.png') }}" alt="E-PRISM" class="h-10 w-auto">
    </a>
    <button @click="mobileOpen = true" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 focus:outline-none">
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
    data-sidebar
    class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-slate-200 bg-white transition-[transform,width] duration-200 ease-in-out lg:translate-x-0"
    :class="[mobileOpen ? 'translate-x-0' : '-translate-x-full', collapsed ? 'lg:w-20' : 'lg:w-72']"
>
    <!-- Collapse toggle (desktop only) -->
    <button
        @click="collapsed = ! collapsed"
        class="absolute -right-3 top-6 z-10 hidden h-6 w-6 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-400 shadow-sm hover:text-cherry-700 focus:outline-none lg:flex"
        :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
    >
        <svg class="h-3.5 w-3.5 transition-transform duration-200" :class="collapsed ? 'rotate-180' : ''" stroke="currentColor" fill="none" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
    </button>

    <div class="flex items-center justify-between px-5 py-5" :class="collapsed ? 'lg:justify-center lg:px-3' : ''">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 overflow-hidden">
            <img src="{{ asset('images/logo.png') }}" alt="E-PRISM" class="w-auto shrink-0 transition-all duration-200" :class="collapsed ? 'h-12' : 'h-16'">
        </a>
        <button @click="mobileOpen = false" class="rounded-md p-1 text-slate-400 hover:bg-slate-100 focus:outline-none lg:hidden">
            <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto overflow-x-hidden px-3 py-2">
        <x-responsive-nav-link class="relative rounded-lg whitespace-nowrap" :href="route('dashboard')" :active="request()->routeIs('dashboard')" x-data="{ tip: false }" @mouseenter="tip = true" @mouseleave="tip = false" @click="tip = false">
            <svg class="mr-2 inline-block h-5 w-5 shrink-0 align-middle" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect></svg>
            <span :class="collapsed ? 'lg:hidden' : ''">{{ __('Dashboard') }}</span>
            <span x-cloak x-show="collapsed && tip" x-transition.opacity class="pointer-events-none absolute left-full top-1/2 z-50 ml-2 -translate-y-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs font-medium text-white shadow-lg">{{ __('Dashboard') }}</span>
        </x-responsive-nav-link>

        @auth
            @if (Auth::user()->isResearcher())
                <x-responsive-nav-link class="relative rounded-lg whitespace-nowrap" :href="route('submissions.index')" :active="request()->routeIs('submissions.*')" x-data="{ tip: false }" @mouseenter="tip = true" @mouseleave="tip = false" @click="tip = false">
                    <svg class="mr-2 inline-block h-5 w-5 shrink-0 align-middle" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3h5l5 5v11a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"></path><polyline points="13 3 13 8 18 8"></polyline><line x1="9.5" y1="13" x2="15" y2="13"></line><line x1="9.5" y1="16.5" x2="15" y2="16.5"></line></svg>
                    <span :class="collapsed ? 'lg:hidden' : ''">{{ __('My Submissions') }}</span>
                    <span x-cloak x-show="collapsed && tip" x-transition.opacity class="pointer-events-none absolute left-full top-1/2 z-50 ml-2 -translate-y-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs font-medium text-white shadow-lg">{{ __('My Submissions') }}</span>
                </x-responsive-nav-link>
            @endif
        @endauth

        <x-responsive-nav-link class="relative rounded-lg whitespace-nowrap" :href="route('repository.index')" :active="request()->routeIs('repository.index')" x-data="{ tip: false }" @mouseenter="tip = true" @mouseleave="tip = false" @click="tip = false">
            <svg class="mr-2 inline-block h-5 w-5 shrink-0 align-middle" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6.5c-1.5-1.2-3.6-2-6-2-.7 0-1.4.06-2 .2v13.5c.6-.14 1.3-.2 2-.2 2.4 0 4.5.8 6 2"></path><path d="M12 6.5c1.5-1.2 3.6-2 6-2 .7 0 1.4.06 2 .2v13.5c-.6-.14-1.3-.2-2-.2-2.4 0-4.5.8-6 2"></path><line x1="12" y1="6.5" x2="12" y2="20"></line></svg>
            <span :class="collapsed ? 'lg:hidden' : ''">{{ __('Repository') }}</span>
            <span x-cloak x-show="collapsed && tip" x-transition.opacity class="pointer-events-none absolute left-full top-1/2 z-50 ml-2 -translate-y-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs font-medium text-white shadow-lg">{{ __('Repository') }}</span>
        </x-responsive-nav-link>

        @auth
            @if (Auth::user()->isReviewer() && Auth::user()->isApproved())
                <x-responsive-nav-link class="relative rounded-lg whitespace-nowrap" :href="route('reviewer.submissions.index')" :active="request()->routeIs('reviewer.submissions.*')" x-data="{ tip: false }" @mouseenter="tip = true" @mouseleave="tip = false" @click="tip = false">
                    <svg class="mr-2 inline-block h-5 w-5 shrink-0 align-middle" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4h6a1 1 0 0 1 1 1v1H8V5a1 1 0 0 1 1-1z"></path><rect x="6" y="5" width="12" height="16" rx="1.5"></rect><polyline points="9 13 11 15 15 10.5"></polyline></svg>
                    <span :class="collapsed ? 'lg:hidden' : ''">{{ __('Reviewer Queue') }}</span>
                    <span x-cloak x-show="collapsed && tip" x-transition.opacity class="pointer-events-none absolute left-full top-1/2 z-50 ml-2 -translate-y-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs font-medium text-white shadow-lg">{{ __('Reviewer Queue') }}</span>
                </x-responsive-nav-link>
            @endif

            @if (Auth::user()->isAdmin() && Auth::user()->isApproved())
                <x-responsive-nav-link class="relative rounded-lg whitespace-nowrap" :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')" x-data="{ tip: false }" @mouseenter="tip = true" @mouseleave="tip = false" @click="tip = false">
                    <svg class="mr-2 inline-block h-5 w-5 shrink-0 align-middle" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"></circle><path d="M4 20c0-3 2.5-5.5 5.5-5.5S15 17 15 20"></path><circle cx="17" cy="9" r="2.4"></circle><path d="M15.2 14.7c2.3.3 4.3 2.5 4.3 5.3"></path></svg>
                    <span :class="collapsed ? 'lg:hidden' : ''">{{ __('Users') }}</span>
                    <span x-cloak x-show="collapsed && tip" x-transition.opacity class="pointer-events-none absolute left-full top-1/2 z-50 ml-2 -translate-y-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs font-medium text-white shadow-lg">{{ __('Users') }}</span>
                </x-responsive-nav-link>
                <x-responsive-nav-link class="relative rounded-lg whitespace-nowrap" :href="route('admin.submissions.index')" :active="request()->routeIs('admin.submissions.*') || request()->routeIs('admin.reviews.*')" x-data="{ tip: false }" @mouseenter="tip = true" @mouseleave="tip = false" @click="tip = false">
                    <svg class="mr-2 inline-block h-5 w-5 shrink-0 align-middle" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.5-3 8-7 9-4-1-7-4.5-7-9V6z"></path><polyline points="9 12 11 14 15 9.5"></polyline></svg>
                    <span :class="collapsed ? 'lg:hidden' : ''">{{ __('Reviewer Assignment') }}</span>
                    <span x-cloak x-show="collapsed && tip" x-transition.opacity class="pointer-events-none absolute left-full top-1/2 z-50 ml-2 -translate-y-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs font-medium text-white shadow-lg">{{ __('Reviewer Assignment') }}</span>
                </x-responsive-nav-link>
                <x-responsive-nav-link class="relative rounded-lg whitespace-nowrap" :href="route('admin.reports')" :active="request()->routeIs('admin.reports')" x-data="{ tip: false }" @mouseenter="tip = true" @mouseleave="tip = false" @click="tip = false">
                    <svg class="mr-2 inline-block h-5 w-5 shrink-0 align-middle" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="20" x2="20" y2="20"></line><rect x="6" y="12" width="3" height="8"></rect><rect x="11" y="8" width="3" height="12"></rect><rect x="16" y="4" width="3" height="16"></rect></svg>
                    <span :class="collapsed ? 'lg:hidden' : ''">{{ __('Reports') }}</span>
                    <span x-cloak x-show="collapsed && tip" x-transition.opacity class="pointer-events-none absolute left-full top-1/2 z-50 ml-2 -translate-y-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs font-medium text-white shadow-lg">{{ __('Reports') }}</span>
                </x-responsive-nav-link>
                <x-responsive-nav-link class="relative rounded-lg whitespace-nowrap" :href="route('admin.activity.index')" :active="request()->routeIs('admin.activity.*')" x-data="{ tip: false }" @mouseenter="tip = true" @mouseleave="tip = false" @click="tip = false">
                    <svg class="mr-2 inline-block h-5 w-5 shrink-0 align-middle" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.5"></circle><polyline points="12 7 12 12 15.5 14"></polyline></svg>
                    <span :class="collapsed ? 'lg:hidden' : ''">{{ __('Activity Log') }}</span>
                    <span x-cloak x-show="collapsed && tip" x-transition.opacity class="pointer-events-none absolute left-full top-1/2 z-50 ml-2 -translate-y-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs font-medium text-white shadow-lg">{{ __('Activity Log') }}</span>
                </x-responsive-nav-link>
                <x-responsive-nav-link class="relative rounded-lg whitespace-nowrap" :href="route('admin.document-templates.index')" :active="request()->routeIs('admin.document-templates.*')" x-data="{ tip: false }" @mouseenter="tip = true" @mouseleave="tip = false" @click="tip = false">
                    <svg class="mr-2 inline-block h-5 w-5 shrink-0 align-middle" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3h5l5 5v11a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"></path><polyline points="13 3 13 8 18 8"></polyline><line x1="9.5" y1="13" x2="15" y2="13"></line><line x1="9.5" y1="16.5" x2="12.5" y2="16.5"></line></svg>
                    <span :class="collapsed ? 'lg:hidden' : ''">{{ __('Document Templates') }}</span>
                    <span x-cloak x-show="collapsed && tip" x-transition.opacity class="pointer-events-none absolute left-full top-1/2 z-50 ml-2 -translate-y-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs font-medium text-white shadow-lg">{{ __('Document Templates') }}</span>
                </x-responsive-nav-link>
                <x-responsive-nav-link class="relative rounded-lg whitespace-nowrap" :href="route('admin.rapm-templates.index')" :active="request()->routeIs('admin.rapm-templates.*')" x-data="{ tip: false }" @mouseenter="tip = true" @mouseleave="tip = false" @click="tip = false">
                    <svg class="mr-2 inline-block h-5 w-5 shrink-0 align-middle" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                    <span :class="collapsed ? 'lg:hidden' : ''">{{ __('RAPM Templates') }}</span>
                    <span x-cloak x-show="collapsed && tip" x-transition.opacity class="pointer-events-none absolute left-full top-1/2 z-50 ml-2 -translate-y-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs font-medium text-white shadow-lg">{{ __('RAPM Templates') }}</span>
                </x-responsive-nav-link>
            @endif
        @endauth
    </nav>

    <div class="border-t border-slate-200 p-4">
        @auth
            <div class="px-1 overflow-hidden whitespace-nowrap" :class="collapsed ? 'lg:hidden' : ''">
                <div class="text-sm font-medium text-slate-800">{{ Auth::user()->name }}</div>
                <div class="text-xs text-slate-500">{{ Auth::user()->email }}</div>
                <div class="mt-1 text-[11px] uppercase tracking-[0.2em] text-slate-400">{{ Auth::user()->role->label() }} &middot; {{ Auth::user()->approval_status->label() }}</div>
            </div>

            <div class="mt-3 space-y-1" :class="collapsed ? 'lg:mt-0' : ''">
                <x-responsive-nav-link class="relative rounded-lg whitespace-nowrap" :href="route('profile.edit')" :active="request()->routeIs('profile.edit')" x-data="{ tip: false }" @mouseenter="tip = true" @mouseleave="tip = false" @click="tip = false">
                    <svg class="mr-2 inline-block h-5 w-5 shrink-0 align-middle" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8.5" r="3.25"></circle><path d="M5 20c0-3.5 3-6 7-6s7 2.5 7 6"></path></svg>
                    <span :class="collapsed ? 'lg:hidden' : ''">{{ __('Profile') }}</span>
                    <span x-cloak x-show="collapsed && tip" x-transition.opacity class="pointer-events-none absolute left-full top-1/2 z-50 ml-2 -translate-y-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs font-medium text-white shadow-lg">{{ __('Profile') }}</span>
                </x-responsive-nav-link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link class="relative rounded-lg whitespace-nowrap" href="{{ route('logout') }}"
                            x-data="{ tip: false }"
                            @mouseenter="tip = true"
                            @mouseleave="tip = false"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        <svg class="mr-2 inline-block h-5 w-5 shrink-0 align-middle" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h4"></path><polyline points="15 16 20 12 15 8"></polyline><line x1="20" y1="12" x2="9" y2="12"></line></svg>
                        <span :class="collapsed ? 'lg:hidden' : ''">{{ __('Log Out') }}</span>
                        <span x-cloak x-show="collapsed && tip" x-transition.opacity class="pointer-events-none absolute left-full top-1/2 z-50 ml-2 -translate-y-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs font-medium text-white shadow-lg">{{ __('Log Out') }}</span>
                    </x-responsive-nav-link>
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

<div x-data="{ open: false }">
    <!-- Mobile top bar -->
    <div class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3 lg:hidden">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-red-700 text-xs font-bold text-white">E-P</div>
            <span class="text-sm font-bold uppercase tracking-[0.15em] text-red-700">E-PRISM</span>
        </a>
        <button @click="open = true" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 focus:outline-none">
            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </div>

    <!-- Mobile overlay -->
    <div
        :class="{ 'hidden': ! open }"
        class="hidden fixed inset-0 z-40 bg-slate-900/50 lg:hidden"
        @click="open = false"
    ></div>

    <!-- Sidebar -->
    <aside
        class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-slate-200 bg-white transition-transform duration-200 ease-in-out lg:translate-x-0"
        :class="open ? 'translate-x-0' : '-translate-x-full'"
    >
        <div class="flex items-center justify-between px-5 py-5">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-red-700 text-xs font-bold text-white">E-P</div>
                <div>
                    <div class="text-sm font-bold uppercase tracking-[0.15em] text-red-700">E-PRISM</div>
                    <div class="text-[11px] text-slate-500">Research Workflow</div>
                </div>
            </a>
            <button @click="open = false" class="rounded-md p-1 text-slate-400 hover:bg-slate-100 focus:outline-none lg:hidden">
                <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-2">
            <x-responsive-nav-link class="rounded-lg" :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>

            <x-responsive-nav-link class="rounded-lg" :href="route('repository.index')" :active="request()->routeIs('repository.index')">
                {{ __('Repository') }}
            </x-responsive-nav-link>

            @auth
                @if (Auth::user()->isResearcher())
                    <x-responsive-nav-link class="rounded-lg" :href="route('submissions.index')" :active="request()->routeIs('submissions.*')">
                        {{ __('My Submissions') }}
                    </x-responsive-nav-link>
                @endif

                @if (Auth::user()->isReviewer() && Auth::user()->isApproved())
                    <x-responsive-nav-link class="rounded-lg" :href="route('reviewer.submissions.index')" :active="request()->routeIs('reviewer.submissions.*')">
                        {{ __('Reviewer Queue') }}
                    </x-responsive-nav-link>
                @endif

                @if (Auth::user()->isAdmin() && Auth::user()->isApproved())
                    <x-responsive-nav-link class="rounded-lg" :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                        {{ __('Users') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link class="rounded-lg" :href="route('admin.submissions.index')" :active="request()->routeIs('admin.submissions.*') || request()->routeIs('admin.reviews.*')">
                        {{ __('Reviewer Assignment') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link class="rounded-lg" :href="route('admin.reports')" :active="request()->routeIs('admin.reports')">
                        {{ __('Reports') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link class="rounded-lg" :href="route('admin.activity.index')" :active="request()->routeIs('admin.activity.*')">
                        {{ __('Activity Log') }}
                    </x-responsive-nav-link>
                @endif
            @endauth
        </nav>

        <div class="border-t border-slate-200 p-4">
            @auth
                <div class="px-1">
                    <div class="text-sm font-medium text-slate-800">{{ Auth::user()->name }}</div>
                    <div class="text-xs text-slate-500">{{ Auth::user()->email }}</div>
                    <div class="mt-1 text-[11px] uppercase tracking-[0.2em] text-slate-400">{{ Auth::user()->role->label() }} &middot; {{ Auth::user()->approval_status->label() }}</div>
                </div>

                <div class="mt-3 space-y-1">
                    <x-responsive-nav-link class="rounded-lg" :href="route('profile.edit')" :active="request()->routeIs('profile.edit')">
                        {{ __('Profile') }}
                    </x-responsive-nav-link>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf

                        <x-responsive-nav-link class="rounded-lg" href="{{ route('logout') }}"
                                onclick="event.preventDefault();
                                            this.closest('form').submit();">
                            {{ __('Log Out') }}
                        </x-responsive-nav-link>
                    </form>
                </div>
            @else
                <div class="space-y-2 px-1 text-sm">
                    <a href="{{ route('login') }}" class="block rounded-lg border border-slate-200 px-4 py-2 text-center font-medium text-slate-700 hover:bg-slate-50">Log in</a>
                    <a href="{{ route('register') }}" class="block rounded-lg bg-red-700 px-4 py-2 text-center font-medium text-white hover:bg-red-800">Register</a>
                </div>
            @endauth
        </div>
    </aside>
</div>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'ePrism Research Workflow') }}</title>

        <!-- Applied synchronously, before Alpine loads, so a previously-collapsed sidebar
             renders collapsed on the very first paint of every full page navigation
             instead of painting expanded and then visibly animating shut once Alpine
             catches up (see the matching CSS in app.css and the x-init handoff below). -->
        <script>
            (function () {
                try {
                    if (localStorage.getItem('eprism-sidebar-collapsed') === '1') {
                        document.documentElement.classList.add('sidebar-collapsed-init');
                    }
                } catch (e) {}
            })();
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600|lora:500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-slate-100 text-slate-900">
        {{-- Generic page shell shown while a full-page navigation is in flight (see
             resources/js/app.js) — not a per-page skeleton, since it only needs to bridge
             the gap between "clicked" and "new page ready," not mirror every layout
             exactly. Sits on top of the real content below and fades away once loaded. --}}
        <div id="page-skeleton" aria-hidden="true">
            <div class="skeleton-sidebar">
                <div class="skeleton-block mb-8 h-8 w-2/3"></div>
                <div class="grid gap-3">
                    <div class="skeleton-block h-4 w-full"></div>
                    <div class="skeleton-block h-4 w-full"></div>
                    <div class="skeleton-block h-4 w-4/5"></div>
                    <div class="skeleton-block h-4 w-full"></div>
                    <div class="skeleton-block h-4 w-3/4"></div>
                    <div class="skeleton-block h-4 w-full"></div>
                </div>
            </div>
            <div class="skeleton-main">
                <div class="skeleton-topbar"></div>
                <div class="mx-auto grid w-full max-w-7xl gap-4 px-4 py-8 sm:px-6 lg:px-8">
                    <div class="skeleton-block h-8 w-1/3"></div>
                    <div class="skeleton-block h-32 w-full"></div>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="skeleton-block h-24"></div>
                        <div class="skeleton-block h-24"></div>
                        <div class="skeleton-block h-24"></div>
                    </div>
                    <div class="skeleton-block h-48 w-full"></div>
                </div>
            </div>
        </div>

        <div
            class="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(140,23,48,0.12),_transparent_35%),linear-gradient(180deg,_#f8fafc,_#e2e8f0)]"
            x-data="{ mobileOpen: false, collapsed: localStorage.getItem('eprism-sidebar-collapsed') === '1' }"
            x-init="$nextTick(() => document.documentElement.classList.remove('sidebar-collapsed-init'))"
            x-effect="localStorage.setItem('eprism-sidebar-collapsed', collapsed ? '1' : '0')"
        >
            @include('layouts.navigation')

            <div data-sidebar-content class="transition-[padding] duration-200 ease-in-out" :class="collapsed ? 'lg:pl-20' : 'lg:pl-72'">
                <div class="hidden lg:block">
                    @include('layouts.masthead')
                </div>
                @include('layouts.topbar')

                <!-- Page Heading -->
                @isset($header)
                    <header class="page-header-shell border-b border-slate-200 bg-white">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <!-- Page Content -->
                <main>
                    @if (session('status'))
                        <div class="max-w-7xl mx-auto px-4 pt-6 sm:px-6 lg:px-8">
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 shadow-sm">
                                {{ session('status') }}
                            </div>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="max-w-7xl mx-auto px-4 pt-6 sm:px-6 lg:px-8">
                            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 shadow-sm">
                                @if ($errors->count() === 1)
                                    {{ $errors->first() }}
                                @else
                                    <ul class="list-inside list-disc space-y-1">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>

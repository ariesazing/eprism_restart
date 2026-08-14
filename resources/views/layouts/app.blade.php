<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'ePrism Research Workflow') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-slate-100 text-slate-900">
        <div
            class="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(185,28,28,0.12),_transparent_35%),linear-gradient(180deg,_#f8fafc,_#e2e8f0)]"
            x-data="{ mobileOpen: false, collapsed: localStorage.getItem('eprism-sidebar-collapsed') === '1' }"
            x-effect="localStorage.setItem('eprism-sidebar-collapsed', collapsed ? '1' : '0')"
        >
            @include('layouts.navigation')

            <div class="transition-[padding] duration-200 ease-in-out" :class="collapsed ? 'lg:pl-20' : 'lg:pl-72'">
                <div class="hidden lg:block">
                    @include('layouts.masthead')
                </div>
                @include('layouts.topbar')

                <!-- Page Heading -->
                @isset($header)
                    <header class="bg-white shadow">
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
                                {{ $errors->first() }}
                            </div>
                        </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>

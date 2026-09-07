<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'ePrism Research Workflow') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600|lora:500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-slate-100 text-slate-900">
        {{--
            A dedicated, full-width page — no sidebar navigation (see layouts/app.blade.php
            for the sidebar-carrying counterpart). Used for a view that's itself a focused
            task rather than a page within the app's usual section-to-section browsing:
            manuscript review and a submission's chapter canvas editor both use this, via
            x-focus-layout. layouts.topbar is reused as-is — it's already independent of
            the sidebar (notifications + account menu only), so logout/profile/
            notifications stay reachable even here.
        --}}
        <div class="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(140,23,48,0.12),_transparent_35%),linear-gradient(180deg,_#f8fafc,_#e2e8f0)]">
            @include('layouts.topbar')

            @isset($header)
                <header class="page-header-shell border-b border-slate-200 bg-white">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

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
    </body>
</html>

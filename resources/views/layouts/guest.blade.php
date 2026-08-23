<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'E-PRISM') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600|lora:500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-900 antialiased">
        <div class="flex min-h-screen flex-col bg-[radial-gradient(circle_at_top,_rgba(140,23,48,0.12),_transparent_35%),linear-gradient(180deg,_#f8fafc,_#e2e8f0)]">
            <div class="border-b-[3px] border-cherry-700 bg-white">
                <div class="mx-auto flex max-w-7xl items-center justify-center gap-3 px-6 py-2.5">
                    <img src="{{ asset('images/deped-bagong-pilipinas.png') }}" alt="Department of Education" class="h-7 w-auto shrink-0">
                    <p class="text-center text-[10px] font-medium uppercase tracking-[0.2em] text-slate-500 sm:text-xs">
                        Republic of the Philippines &middot; Department of Education &middot; Schools Division of Santiago City
                    </p>
                    <img src="{{ asset('images/sdo-santiago-seal.png') }}" alt="Schools Division of Santiago City" class="h-7 w-auto shrink-0">
                </div>
            </div>

            <div class="flex flex-1 flex-col items-center justify-center px-6 py-10">
                <a href="/" class="flex items-center gap-3">
                    <img src="{{ asset('images/logo.png') }}" alt="E-PRISM" class="h-16 w-auto">
                </a>
                <p class="mt-2 text-center text-xs font-medium uppercase tracking-[0.25em] text-slate-400">
                    Electronic Program for Research Initiative Submission and Management
                </p>

                <div class="mt-6 w-full sm:max-w-md rounded-2xl border-t-4 border-cherry-700 bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    {{ $slot }}
                </div>
            </div>

            <footer class="border-t border-slate-200 bg-white/60 px-6 py-4 text-center text-xs text-slate-400">
                &copy; {{ now()->year }} Schools Division of Santiago City &middot; Department of Education, Republic of the Philippines
            </footer>
        </div>
    </body>
</html>

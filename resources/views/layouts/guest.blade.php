<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'E-PRISM') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-900 antialiased">
        <div class="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(185,28,28,0.12),_transparent_35%),linear-gradient(180deg,_#f8fafc,_#e2e8f0)]">
            <div class="border-b-4 border-red-700 bg-white">
                <div class="mx-auto max-w-7xl px-6 py-3 text-center text-xs font-medium uppercase tracking-[0.2em] text-slate-500">
                    Republic of the Philippines &middot; Department of Education &middot; Schools Division of Santiago City
                </div>
            </div>

            <div class="flex min-h-[calc(100vh-45px)] flex-col items-center justify-center px-6 py-10">
                <a href="/" class="flex items-center gap-3">
                    <img src="{{ asset('images/logo.png') }}" alt="E-PRISM" class="h-16 w-auto">
                </a>

                <div class="mt-6 w-full sm:max-w-md rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>

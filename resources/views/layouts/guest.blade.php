<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'E-PRISM') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600|lora:500,600,600i,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="dashboard-reference reference-public research-ui font-sans text-slate-900 antialiased">
        <div class="min-h-screen bg-slate-50 lg:grid lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)]">
            {{-- Brand panel — the official DepEd/SDO identification and mission statement that
                 used to sit in a separate top strip, folded into one editorial panel instead.
                 On mobile this collapses to a compact hero (the quote/hairline/copyright below
                 are lg-only) so the form itself stays within the first scroll. --}}
            <aside class="auth-brand relative overflow-hidden px-8 py-8 sm:px-12 lg:flex lg:flex-col lg:justify-between lg:gap-10 lg:px-14 lg:py-14">
                <div class="auth-brand__texture" aria-hidden="true"></div>
                <x-balamban-butterfly class="auth-butterfly" />

                <div class="deped-mark-group relative z-10 flex items-center gap-3">
                    <img src="{{ asset('images/deped-bagong-pilipinas.png') }}" alt="Department of Education" class="h-8 w-auto shrink-0 sm:h-9">
                    <div class="h-6 w-px bg-cherry-100/25 sm:h-7"></div>
                    <img src="{{ asset('images/sdo-santiago-seal.png') }}" alt="Schools Division of Santiago City" class="h-8 w-auto shrink-0 sm:h-9">
                </div>

                <div class="auth-rise relative z-10 mt-6 max-w-lg lg:mt-0" style="animation-delay:.05s">
                    <span class="auth-studio-chip">IDEAS INTO IMPACT</span>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-cherry-200/80">
                        Schools Division of Santiago City, Department of Education
                    </p>
                    <div class="mt-3 flex flex-col items-start gap-4">
                        <x-prism-brand />
                        <h1 class="font-serif text-2xl font-semibold leading-tight text-white sm:text-3xl lg:text-4xl">
                            Electronic Program for Research Initiative Submission &amp; Management
                        </h1>
                    </div>

                    <div class="auth-studio-path"><span>Develop your idea</span><span>Exchange feedback</span><span>Share knowledge</span></div>
                    <div class="hidden lg:block">
                        <div class="mt-5 h-px w-16 bg-gradient-to-r from-amber-300/90 to-transparent"></div>
                        <p class="mt-5 font-serif text-base italic leading-relaxed text-cherry-100/85">
                            &ldquo;The system of record for every research proposal, review, and approval
                            across the Schools Division of Santiago City.&rdquo;
                        </p>
                    </div>
                </div>

                <p class="relative z-10 hidden text-xs text-cherry-200/60 lg:block">
                    &copy; {{ now()->year }} Schools Division of Santiago City, Department of Education
                </p>
            </aside>

            {{-- Form panel --}}
            <div class="flex items-center justify-center px-6 py-10 sm:px-10 lg:py-12">
                <div class="research-auth-form w-full sm:max-w-lg">
                    <div class="auth-rise" style="animation-delay:.1s">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>

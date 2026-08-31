<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'E-PRISM') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|lora:500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-white text-slate-900">
    <div class="border-b-4 border-cherry-700 bg-white">
        <div class="mx-auto max-w-7xl px-6 py-3 text-center text-xs font-medium uppercase tracking-[0.2em] text-slate-500 lg:px-8">
            Republic of the Philippines &middot; Department of Education &middot; Schools Division of Santiago City
        </div>
    </div>

    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-5 lg:px-8">
            <div class="flex items-center gap-3">
                <img src="{{ asset('images/logo.png') }}" alt="E-PRISM" class="h-14 w-auto">
            </div>
            @auth
                <nav class="flex items-center gap-3 text-sm">
                    <a href="{{ route('dashboard') }}" class="rounded-xl bg-cherry-700 px-4 py-2 font-medium text-white hover:bg-cherry-800">Dashboard</a>
                </nav>
            @endauth
        </div>
    </header>

    <main>
        <section class="relative isolate overflow-hidden">
            <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top_right,_rgba(140,23,48,0.08),_transparent_45%)]"></div>
            <div class="mx-auto max-w-5xl px-6 py-10 text-center lg:px-8">
                <span class="inline-block rounded-full bg-cherry-50 px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.3em] text-cherry-700 ring-1 ring-cherry-200"> E-PRISM </span>
                <h1 class="mt-4 text-2xl font-bold leading-tight text-slate-900 sm:text-3xl lg:text-4xl">
                   Electronic Program for Research Initiative Submission and Management
                </h1>
                <p class="mx-auto mt-3 max-w-3xl text-sm leading-relaxed text-slate-600 lg:text-base">
                    E-PRISM digitizes the Schools Division's basic and action research process end to end — replacing paper routing with a single system where researchers submit proposals and completed studies, assigned reviewers evaluate and score each submission against a standard rubric, and approved research is published to a searchable repository.
                </p>
            </div>
        </section>

        @auth
            <section class="border-t border-slate-200 bg-slate-50">
                <div class="mx-auto max-w-3xl px-6 py-10 text-center lg:px-8">
                    <a href="{{ route('dashboard') }}" class="inline-flex rounded-xl bg-cherry-700 px-7 py-3 text-sm font-semibold text-white shadow-sm hover:bg-cherry-800">Open your dashboard</a>
                </div>
            </section>
        @else
            <section class="border-t border-slate-200 bg-slate-50">
                <div class="mx-auto max-w-4xl px-6 py-8 lg:px-8">
                    <p class="text-center text-lg font-semibold text-slate-900">What brings you here today?</p>

                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        <a href="{{ route('guest-submissions.create') }}" class="group flex flex-col rounded-2xl bg-white p-6 text-left shadow-sm ring-1 ring-slate-200 transition hover:ring-cherry-300">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-cherry-50 text-cherry-700">
                                <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14" /></svg>
                            </div>
                            <h3 class="mt-3 text-base font-semibold text-slate-900">I'm new &amp; submitting research</h3>
                            <p class="mt-1.5 flex-1 text-sm leading-relaxed text-slate-600">Start your proposal or completed research right away — you'll only need to register once you're ready to save it.</p>
                            <span class="mt-3 text-sm font-semibold text-cherry-700 group-hover:underline">Start a submission &rarr;</span>
                        </a>

                        <a href="{{ route('login') }}" class="group flex flex-col rounded-2xl bg-white p-6 text-left shadow-sm ring-1 ring-slate-200 transition hover:ring-cherry-300">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-700">
                                <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><path d="M10 17l5-5-5-5"></path><path d="M15 12H3"></path></svg>
                            </div>
                            <h3 class="mt-3 text-base font-semibold text-slate-900">I already have an account</h3>
                            <p class="mt-1.5 flex-1 text-sm leading-relaxed text-slate-600">Researcher, reviewer, or administrator — log in to pick up where you left off.</p>
                            <span class="mt-3 text-sm font-semibold text-slate-700 group-hover:underline">Log in &rarr;</span>
                        </a>
                    </div>
                </div>
            </section>
        @endauth

        <section class="border-t border-slate-200 bg-white">
            <div class="mx-auto max-w-4xl px-6 py-10 lg:px-8">
                <div class="mx-auto max-w-xl text-center">
                    <h2 class="text-sm font-semibold uppercase tracking-[0.3em] text-cherry-700">Submission Timeline</h2>
                    <p class="mt-2 text-sm text-slate-500">Current status of each submission window.</p>
                </div>
                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    @foreach ($windows as $classification => $window)
                        @php $isOpen = $window->isCurrentlyOpen(); @endphp
                        <div class="rounded-2xl border border-slate-200 p-5">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-sm font-semibold text-slate-900">{{ $classification === 'proposal' ? 'Proposal Research' : 'Completed Research' }}</h3>
                                <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold {{ $isOpen ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $isOpen ? 'Accepting Submissions' : 'Closed' }}
                                </span>
                            </div>
                            @if ($window->opens_at || $window->closes_at)
                                <p class="mt-2 text-xs text-slate-500">
                                    @if ($window->opens_at){{ $window->opens_at->format('M j, Y') }}@endif
                                    @if ($window->opens_at && $window->closes_at) &ndash; @endif
                                    @if ($window->closes_at){{ $window->closes_at->format('M j, Y') }}@endif
                                </p>
                            @endif
                            @if ($window->memorandum_path)
                                <a href="{{ route('submission-timeline.memorandum', $classification) }}" target="_blank" class="mt-3 inline-block text-xs font-medium text-cherry-700 hover:underline">View memorandum &rarr;</a>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="border-t border-slate-200 bg-white">
            <div class="mx-auto max-w-7xl px-6 py-16 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-sm font-semibold uppercase tracking-[0.3em] text-cherry-700">Purpose</h2>
                    <p class="mt-3 text-2xl font-semibold text-slate-900">One workflow, from proposal to publication</p>
                </div>

                <div class="mt-12 grid gap-6 md:grid-cols-3">
                    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-cherry-50 text-sm font-bold text-cherry-700">1</div>
                        <h3 class="mt-4 text-lg font-semibold text-slate-900">Submission</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">Researchers register proponent details, position, and school/station, then complete each chapter of the standardized basic or action research template with supporting attachments.</p>
                    </div>
                    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-cherry-50 text-sm font-bold text-cherry-700">2</div>
                        <h3 class="mt-4 text-lg font-semibold text-slate-900">Reviewer Evaluation</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">A panel of assigned reviewers scores every submission against a shared rubric and leaves sidebar comments directly on the manuscript, without altering the original document.</p>
                    </div>
                    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-cherry-50 text-sm font-bold text-cherry-700">3</div>
                        <h3 class="mt-4 text-lg font-semibold text-slate-900">Repository &amp; Publication</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">Once every reviewer approves, a proposal advances toward its completed research stage and, once finalized, is published to the repository for the division to reference.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto max-w-7xl px-6 py-8 text-center text-xs text-slate-400 lg:px-8">
            &copy; {{ now()->year }} Schools Division of Santiago City &middot; Department of Education
        </div>
    </footer>
</body>

</html>

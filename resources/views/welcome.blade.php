<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'ePrism Research Workflow') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-950 text-white">
    <div class="relative isolate overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(8,145,178,0.35),_transparent_30%),radial-gradient(circle_at_bottom_right,_rgba(249,115,22,0.25),_transparent_30%)]"></div>
        <div class="relative mx-auto flex min-h-screen max-w-7xl flex-col px-6 py-8 lg:px-8">
            <header class="flex items-center justify-between">
                <div class="rounded-full border border-white/20 px-4 py-2 text-sm font-semibold uppercase tracking-[0.3em] text-cyan-200">ePrism</div>
                <nav class="flex items-center gap-3 text-sm">
                    <a href="{{ route('repository.index') }}" class="rounded-full border border-white/15 px-4 py-2 text-slate-200 hover:bg-white/10">Repository</a>
                    @auth
                    <a href="{{ route('dashboard') }}" class="rounded-full bg-white px-4 py-2 font-medium text-slate-900">Dashboard</a>
                    @else
                    <a href="{{ route('login') }}" class="rounded-full border border-white/15 px-4 py-2 text-slate-200 hover:bg-white/10">Log in</a>
                    <a href="{{ route('register') }}" class="rounded-full bg-cyan-400 px-4 py-2 font-medium text-slate-950">Register</a>
                    @endauth
                </nav>
            </header>

            <main class="grid flex-1 items-center gap-12 py-16 lg:grid-cols-[1.2fr,0.8fr]">
                <section>
                    <p class="text-sm uppercase tracking-[0.4em] text-cyan-200">Traditional Workflow</p>
                    <h1 class="mt-6 max-w-4xl text-5xl font-semibold leading-tight text-white lg:text-6xl">Manage research submissions, review cycles, approvals, and publication in one workflow.</h1>
                    <p class="mt-6 max-w-2xl text-lg text-slate-300">Researchers submit manuscripts and revisions, reviewers score against a rubric, and administrators approve evaluations, decisions, and repository publication.</p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="{{ auth()->check() ? route('dashboard') : route('register') }}" class="rounded-full bg-cyan-400 px-6 py-3 text-sm font-semibold text-slate-950">{{ auth()->check() ? 'Open dashboard' : 'Create researcher account' }}</a>
                        <a href="{{ route('repository.index') }}" class="rounded-full border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10">Browse repository</a>
                    </div>
                </section>

                <section class="grid gap-4 rounded-[2rem] border border-white/10 bg-white/10 p-6 shadow-2xl backdrop-blur">
                    <div class="rounded-2xl bg-slate-900/70 p-5">
                        <div class="text-sm text-cyan-200">Module 1</div>
                        <div class="mt-2 text-xl font-semibold">Access and approval</div>
                        <p class="mt-2 text-sm text-slate-300">Authentication, role-based access, user approval, and user management.</p>
                    </div>
                    <div class="rounded-2xl bg-slate-900/70 p-5">
                        <div class="text-sm text-cyan-200">Module 2</div>
                        <div class="mt-2 text-xl font-semibold">Submission workflow</div>
                        <p class="mt-2 text-sm text-slate-300">Research intake, document uploads, status tracking, revisions, and repository publication.</p>
                    </div>
                    <div class="rounded-2xl bg-slate-900/70 p-5">
                        <div class="text-sm text-cyan-200">Review and administration</div>
                        <div class="mt-2 text-xl font-semibold">Evaluation and decisions</div>
                        <p class="mt-2 text-sm text-slate-300">Reviewer assignments, rubric scoring, comment capture, evaluation approval, reports, and final approval.</p>
                    </div>
                </section>
            </main>
        </div>
    </div>
</body>

</html>
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <link rel="icon" type="image/png" href="{{ asset('images/eprism-prism.png') }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'e-PRISM') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="dashboard-reference reference-public guest-landing research-ui font-sans antialiased">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:p-4">Skip to content</a>
    @include('layouts.masthead', ['guestHeading' => true])
    <nav class="studio-nav" aria-label="Main navigation">
        <div class="studio-nav-links">
            <a href="#research-journey">How it works</a>
            @auth
                <x-research-action :href="route('dashboard')">Dashboard</x-research-action>
            @else
                <x-research-action :href="route('login')">Log in</x-research-action>
            @endauth
        </div>
    </nav>
    <main id="main-content" class="studio-wrap">
        <section class="guest-hero-grid" aria-labelledby="hero-title">
            <div class="guest-introduction">
            <div class="guest-body-brand auth-rise"><x-prism-brand /></div>
            <h1 id="hero-title" class="auth-rise" style="animation-delay:.08s">electronic Program for Research Initiative<br>Submission &amp; Management</h1>
            <p class="guest-summary auth-rise" style="animation-delay:.16s">Submit your research, respond to reviewer feedback, and track your progress from proposal to completed research.</p>
            <div class="studio-hero-actions auth-rise" style="animation-delay:.24s">
                @auth
                    <x-research-action :href="route('dashboard')">Open your dashboard</x-research-action>
                @else
                    <x-research-action :href="route('guest-submissions.create')">Start a submission</x-research-action>
                    <a href="{{ route('login') }}" class="studio-text-link">Already have an account? Log in &rarr;</a>
                @endauth
            </div>
            @guest
                <p class="guest-registration-note auth-rise" style="animation-delay:.3s">You can start entering your details as a guest. Register or log in to save your draft to your account.</p>
            @endguest
            </div>
        <aside id="submission-windows" class="guest-announcements" aria-label="Research submission availability">
            @foreach ($windows as $researchType => $classifications)
                <div class="studio-window-group auth-rise" style="animation-delay:{{ 0.16 + $loop->index * 0.08 }}s">
                    <h3 class="studio-window-group-title">
                        <span class="guest-category-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                @if ($researchType === 'basic')
                                    <path d="M4 4h6l2 2 2-2h6v15h-6l-2 2-2-2H4Z M12 6v15 M7 8h2 M7 12h2 M15 8h2 M15 12h2"/>
                                @else
                                    <path d="M5 3h10v5M5 3v18h7M8 7h3M8 11h2"/>
                                    <g class="guest-research-lens"><circle cx="15" cy="14" r="4"/><path d="m18 17 3 3"/></g>
                                @endif
                            </svg>
                        </span>
                        {{ $researchType === 'basic' ? 'Basic Research' : 'Action Research' }}
                    </h3>
                    <div class="studio-windows">
                        @foreach ($classifications as $classification => $window)
                            @php $isOpen = $window->isCurrentlyOpen(); @endphp
                            <div class="studio-window">
                                <div>
                                    <strong>{{ $classification === 'proposal' ? 'Proposal' : 'Completed Research' }}</strong>
                                    @if ($window->opens_at || $window->closes_at)
                                        <p>@if ($window->opens_at)Opens {{ $window->opens_at->format('M j, Y') }}@endif @if ($window->closes_at) &middot; Closes {{ $window->closes_at->format('M j, Y') }}@endif</p>
                                    @endif
                                    @if ($window->memorandum_path)
                                        <a href="{{ route('submission-timeline.memorandum', [$researchType, $classification]) }}" target="_blank" rel="noopener" class="studio-text-link mt-2 inline-block">View memorandum &rarr;</a>
                                    @endif
                                </div>
                                <span class="studio-window-badge" data-open="{{ $isOpen ? 'true' : 'false' }}">{{ $isOpen ? 'Accepting Submissions' : 'Closed' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </aside>
        </section>
        <section id="research-journey" class="guest-workflow" aria-labelledby="journey-title">
            <div class="studio-section-title">
                <div>
                    <span class="guest-section-eyebrow">How it works</span>
                    <h2 id="journey-title">From submission to completion</h2>
                </div>
            </div>
            <ol>
                <li><span class="guest-step" aria-hidden="true">01</span><h3>Prepare and submit</h3><p>Enter your research and proponent details, complete your manuscript, and attach the required documents before submitting for review.</p></li>
                <li><span class="guest-step" aria-hidden="true">02</span><h3>Review and revise</h3><p>Assigned reviewers evaluate your submission. Read their feedback, make any requested revisions, and resubmit for evaluation.</p></li>
                <li><span class="guest-step" aria-hidden="true">03</span><h3>Complete your research</h3><p>When all assigned reviewers approve your proposal, it becomes a completed-research draft. Update the manuscript with your completed study and submit it for another review.</p></li>
                <li><span class="guest-step" aria-hidden="true">04</span><h3>Final approval</h3><p>Reviewers evaluate the completed research. Address any further revisions; the submission is marked Approved when all assigned reviewers approve it.</p></li>
            </ol>
        </section>
    </main>
    <div class="studio-wrap"><footer class="studio-footer">
        <div class="studio-footer-logos"><img src="{{ asset('images/deped-bagong-pilipinas.png') }}" alt="Department of Education"><img src="{{ asset('images/sdo-santiago-seal.png') }}" alt="Schools Division of Santiago City"></div>
        <p>&copy; {{ now()->year }} Schools Division of Santiago City</p>
    </footer></div>
</body>
</html>

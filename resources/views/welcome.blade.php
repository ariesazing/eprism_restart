<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'E-PRISM') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="research-ui font-sans antialiased">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:p-4">Skip to content</a>
    @include('layouts.masthead')
    <nav class="studio-nav" aria-label="Main navigation">
        <a href="{{ url('/') }}" class="studio-wordmark"><img src="{{ asset('images/logo_notext.png') }}" alt="">E-PRISM<span class="sr-only"> home</span></a>
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
        <section class="studio-hero" aria-labelledby="hero-title">
            <div>
                <span class="studio-eyebrow">A home for research &amp; discovery</span>
                <h1 id="hero-title">Small questions.<br><em>Meaningful change.</em></h1>
                <p class="studio-intro">Bring your ideas to life with E-PRISM. A shared space to develop research, exchange feedback, and grow knowledge across the Schools Division of Santiago City.</p>
                <div class="studio-hero-actions">
                    @auth
                        <x-research-action :href="route('dashboard')">Open your workspace</x-research-action>
                        <a href="{{ route('repository.index') }}" class="studio-text-link">Explore the repository &rarr;</a>
                    @else
                        <x-research-action :href="route('guest-submissions.create')">Start your research</x-research-action>
                        <a href="#submission-windows" class="studio-text-link">Submission windows &rarr;</a>
                    @endauth
                </div>
            </div>
            <div class="studio-bento" aria-label="Explore the research process">
                <article class="studio-tile studio-tile--idea">
                    <small>01 / The spark</small>
                    <h2>Every discovery begins with a question.</h2>
                    <p>Let your ideas take flight.</p>
                    <x-balamban-butterfly class="studio-butterfly" />
                </article>
                <article class="studio-tile studio-tile--review">
                    <span class="studio-icon" aria-hidden="true"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.7"><path d="M4 4h16v12H9l-5 4V4Z"/><path d="m8 10 3 3 5-6"/></svg></span>
                    <small>02 / The conversation</small><h2>Better, together.</h2><p>Thoughtful reviews. Stronger research.</p>
                </article>
                <article class="studio-tile studio-tile--knowledge">
                    <span class="studio-icon" aria-hidden="true"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.7"><path d="M3 4h7l2 2 2-2h7v15h-7l-2 2-2-2H3Z"/><path d="M12 6v15"/></svg></span>
                    <small>03 / The contribution</small><h2>Knowledge that grows.</h2><p>A repository of ideas worth sharing.</p>
                </article>
            </div>
        </section>
        <section aria-labelledby="workspace-title">
            <div class="studio-section-title"><h2 id="workspace-title">Find your starting point.</h2><p>Your next chapter starts here.</p></div>
            <div class="studio-destinations">
                <article class="studio-destination studio-destination--yellow">
                    <span class="studio-number" aria-hidden="true">01</span>
                    <h3>Have a research idea?</h3>
                    <p>Start a proposal or completed research submission. Organize your manuscript, add your team, and prepare it for review.</p>
                    @auth
                        <x-research-action :href="route('dashboard')">Go to your dashboard</x-research-action>
                    @else
                        <x-research-action :href="route('guest-submissions.create')">Start a submission</x-research-action>
                    @endauth
                </article>
                <article class="studio-destination">
                    <span class="studio-number" aria-hidden="true">02</span>
                    <h3>Pick up where you left off.</h3>
                    <p>Researcher, reviewer, or administrator: your submissions, feedback, and next steps are waiting in your workspace.</p>
                    @auth
                        <x-research-action :href="route('dashboard')" class="research-action--yellow">Open your dashboard</x-research-action>
                    @else
                        <x-research-action :href="route('login')" class="research-action--yellow">Log in to your workspace</x-research-action>
                    @endauth
                </article>
            </div>
        </section>
        <aside class="balamban-note" aria-label="Our local inspiration">
            <x-balamban-butterfly class="balamban-note__icon" />
            <div><p class="balamban-note__title">Rooted in Santiago. Ready to take flight.</p>
            <p>Inspired by Balamban, our butterfly motif celebrates growth and discovery through education and research.</p></div>
            <span class="balamban-place">Santiago City, Isabela</span>
        </aside>
        <section id="research-journey" class="studio-journey" aria-labelledby="journey-title">
            <div class="studio-section-title"><h2 id="journey-title">From a question to a contribution.</h2></div>
            <ol>
                <li><span class="studio-eyebrow">Develop</span><h3>Give your idea a home</h3><p>Build your manuscript, organize attachments, and submit your research for review.</p></li>
                <li><span class="studio-eyebrow">Refine</span><h3>Make room for feedback</h3><p>Work through reviewer comments and revisions, with your research history in one place.</p></li>
                <li><span class="studio-eyebrow">Share</span><h3>Add to what we know</h3><p>Approved proposals move toward completed research. Finalized work joins the division's research repository.</p></li>
            </ol>
        </section>
        <section id="submission-windows" aria-labelledby="windows-title">
            <div class="studio-section-title"><h2 id="windows-title">Submission Timeline</h2><p>Plan your next step around the current submission windows.</p></div>
            <div class="studio-windows">
                @foreach ($windows as $classification => $window)
                    @php $isOpen = $window->isCurrentlyOpen(); @endphp
                    <div class="studio-window">
                        <div>
                            <strong>{{ $classification === 'proposal' ? 'Proposal Research' : 'Completed Research' }}</strong>
                            @if ($window->opens_at || $window->closes_at)
                                <p>@if ($window->opens_at)Opens {{ $window->opens_at->format('M j, Y') }}@endif @if ($window->closes_at) ? Closes {{ $window->closes_at->format('M j, Y') }}@endif</p>
                            @endif
                            @if ($window->memorandum_path)
                                <a href="{{ route('submission-timeline.memorandum', $classification) }}" target="_blank" rel="noopener" class="studio-text-link mt-2 inline-block">View memorandum &rarr;</a>
                            @endif
                        </div>
                        <span class="studio-window-badge" data-open="{{ $isOpen ? 'true' : 'false' }}">{{ $isOpen ? 'Accepting Submissions' : 'Closed' }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    </main>
    <div class="studio-wrap"><footer class="studio-footer">
        <div class="studio-footer-logos"><img src="{{ asset('images/deped-bagong-pilipinas.png') }}" alt="Department of Education"><img src="{{ asset('images/sdo-santiago-seal.png') }}" alt="Schools Division of Santiago City"></div>
        <p>Electronic Program for Research Initiative Submission &amp; Management<br>&copy; {{ now()->year }} Schools Division of Santiago City</p>
    </footer></div>
</body>
</html>

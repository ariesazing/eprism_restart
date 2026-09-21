<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Start a Research Submission &middot; {{ config('app.name', 'e-PRISM') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|lora:500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="dashboard-reference reference-public research-ui min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    {{-- Same editorial "official document" treatment as layouts/guest.blade.php's auth
         pages — a textured cherry hero carrying the DepEd/SDO identification and a large,
         low-opacity e-PRISM watermark, with the actual form on a card layered below it. --}}
    <header class="auth-brand relative overflow-hidden px-6 py-8 sm:px-10 lg:px-14 lg:py-12">
        <div class="auth-brand__texture" aria-hidden="true"></div>
        <x-balamban-butterfly class="auth-butterfly" />

        <div class="relative z-10 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <img src="{{ asset('images/deped-bagong-pilipinas.png') }}" alt="Department of Education" class="h-8 w-auto shrink-0 sm:h-9">
                <div class="h-6 w-px bg-cherry-100/25 sm:h-7"></div>
                <img src="{{ asset('images/sdo-santiago-seal.png') }}" alt="Schools Division of Santiago City" class="h-8 w-auto shrink-0 sm:h-9">
            </div>

            <a href="{{ route('login') }}" class="shrink-0 rounded-full border border-white/25 px-3.5 py-2 text-xs font-medium text-cherry-50 hover:bg-white/10 sm:px-4 sm:text-sm">
                Already registered? <span class="font-semibold">Log in</span>
            </a>
        </div>

        <div class="auth-rise relative z-10 mt-8 max-w-xl" style="animation-delay:.05s">
            <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-cherry-200/80">Guest Submission</p>
            <h1 class="mt-3 font-serif text-2xl font-semibold leading-tight text-white sm:text-3xl">
                Start Your Research Submission
            </h1>
            <p class="mt-3 max-w-md text-sm leading-relaxed text-cherry-100/85">
                Fill in the basics now — nothing is saved to your account until you register. Once
                you register, this draft carries over automatically so you don't have to re-enter it.
            </p>
        </div>
    </header>

    <main class="relative -mt-8 pb-16">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="auth-rise app-card border-t-4 border-t-cherry-700 bg-white p-6 sm:p-8" style="animation-delay:.1s">
                <div id="guest-draft-restore-notice" class="hidden rounded-xl bg-cherry-50 p-3 text-xs text-cherry-700 ring-1 ring-cherry-200">
                    We restored what you last typed here.
                </div>

                @php
                    $closedProposalTypes = collect(['basic' => 'Basic Research', 'action' => 'Action Research'])
                        ->filter(fn ($label, $type) => ! $proposalWindowOpen[$type])
                        ->values();
                @endphp
                @if ($closedProposalTypes->isNotEmpty())
                    <div class="mt-4 rounded-xl bg-amber-50 p-3 text-xs text-amber-800 ring-1 ring-amber-200">
                        {{ $closedProposalTypes->implode(' and ') }} proposal submissions are currently closed. You can still draft here, but you won't be able to send it for review until an administrator reopens submissions for your research type.
                    </div>
                @endif

                <form id="guest-draft-form" class="mt-6 grid gap-6" data-submission-form data-register-url="{{ route('register') }}">
                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-slate-700">Title</label>
                            <input type="text" id="guest-title" class="mt-2 w-full rounded-xl border-slate-300" required />
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Research Type</label>
                            <select id="guest-research-type" class="mt-2 w-full rounded-xl border-slate-300" required>
                                <option value="basic">Basic Research</option>
                                <option value="action">Action Research</option>
                            </select>
                        </div>
                    </div>

                    @include('researcher.submissions.partials.organizational-unit-fields', [
                        'organizationalUnits' => $organizationalUnits,
                        'disabled' => false,
                    ])

                    {{-- submission-form-script.blade.php's renderAllPositions() looks for
                         [data-proponent] blocks *inside* [data-proponents] — the lead
                         proponent block below must nest inside this container (not sit
                         beside it) or the script never finds it and the Position dropdown
                         never gets populated. --}}
                    <div data-proponents data-next-index="1">
                        <div class="app-card-inset p-5" data-proponent data-index="0">
                            <h4 class="text-sm font-semibold text-slate-900">Your Details (Lead Proponent)</h4>
                            <div class="mt-4 grid gap-6 md:grid-cols-3">
                                <div>
                                    <label class="text-xs font-medium text-slate-700">Last Name</label>
                                    <input type="text" id="guest-last-name" class="mt-2 w-full rounded-xl border-slate-300" required />
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-slate-700">First Name</label>
                                    <input type="text" id="guest-first-name" class="mt-2 w-full rounded-xl border-slate-300" required />
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-slate-700">Middle Initial</label>
                                    <input type="text" id="guest-middle-initial" maxlength="10" class="mt-2 w-full rounded-xl border-slate-300" />
                                </div>
                            </div>
                            <div class="mt-4">
                                <label class="text-xs font-medium text-slate-700">Position</label>
                                <select id="guest-position" class="mt-2 w-full rounded-xl border-slate-300" data-position required>
                                    <option value="" disabled selected>Select school/station first</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div>
                        <button type="submit" class="w-full rounded-xl bg-cherry-700 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-cherry-800 sm:w-auto">
                            Continue &mdash; Create My Account to Save This Draft
                        </button>
                        <p class="mt-2 text-xs text-slate-500">You'll be asked to register on the next step. Your draft is kept in this browser only until then.</p>
                    </div>
                </form>
            </div>
        </div>
    </main>

    @include('researcher.submissions.partials.submission-form-script')
    @vite(['resources/js/guest-draft.js'])
</body>
</html>

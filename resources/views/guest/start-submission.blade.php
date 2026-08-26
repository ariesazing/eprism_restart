<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Start a Research Submission &middot; {{ config('app.name', 'E-PRISM') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|lora:500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-50 text-slate-900">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-5 lg:px-8">
            <a href="{{ route('welcome') }}" class="flex items-center gap-3">
                <img src="{{ asset('images/logo.png') }}" alt="E-PRISM" class="h-12 w-auto">
            </a>
            <a href="{{ route('login') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Already registered? Log in</a>
        </div>
    </header>

    <main class="py-10">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h1 class="text-xl font-semibold text-slate-900">Start Your Research Submission</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Fill in the basics now — nothing is saved to your account until you register. Once you register,
                    this draft carries over automatically so you don't have to re-enter it.
                </p>

                <div id="guest-draft-restore-notice" class="mt-4 hidden rounded-xl bg-cherry-50 p-3 text-xs text-cherry-700 ring-1 ring-cherry-200">
                    We restored what you last typed here.
                </div>

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
                        <div class="rounded-2xl border border-slate-200 p-5" data-proponent data-index="0">
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

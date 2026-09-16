<x-guest-layout>
    <div class="auth-rise mb-7" style="animation-delay:.15s">
        <p class="text-[11px] font-semibold uppercase tracking-[0.25em] text-cherry-600">Email Verification</p>
        <h1 class="mt-2 font-serif text-[26px] font-semibold leading-snug text-slate-900">Verify your email</h1>
    </div>

    <div class="auth-rise mb-5 text-sm leading-relaxed text-slate-600" style="animation-delay:.2s">
        {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="auth-rise mb-5 flex items-center gap-2 rounded-xl bg-emerald-50 px-3.5 py-2.5 text-sm font-medium text-emerald-700 ring-1 ring-emerald-200" style="animation-delay:.2s">
            <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
            </svg>
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="auth-rise flex items-center justify-between gap-4" style="animation-delay:.25s">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <x-primary-button>
                {{ __('Resend Verification Email') }}
            </x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="text-sm font-medium text-slate-600 underline-offset-2 hover:text-cherry-700 hover:underline rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cherry-500">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>

<x-guest-layout>
    <div class="auth-rise mb-7" style="animation-delay:.15s">
        <p class="text-[11px] font-semibold uppercase tracking-[0.25em] text-cherry-600">Password Recovery</p>
        <h1 class="mt-2 font-serif text-[26px] font-semibold leading-snug text-slate-900">Reset your password</h1>
        <p class="mt-1.5 text-sm text-slate-500">
            {{ __('Let us know your email address and we will email you a password reset link.') }}
        </p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="auth-rise mb-5" style="animation-delay:.2s" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="auth-rise" style="animation-delay:.2s">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1.5 w-full" type="email" name="email" :value="old('email')" required autofocus data-rules="required|email" />
            <x-input-error :messages="$errors->get('email')" field="email" class="mt-2" />
        </div>

        <x-primary-button class="mt-6 w-full justify-center">
            {{ __('Email Password Reset Link') }}
        </x-primary-button>
    </form>

    <p class="auth-rise mt-7 border-t border-slate-100 pt-5 text-center text-sm text-slate-600" style="animation-delay:.25s">
        <a class="font-semibold text-cherry-700 underline-offset-2 hover:text-cherry-800 hover:underline rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cherry-500" href="{{ route('login') }}">
            &larr; {{ __('Back to sign in') }}
        </a>
    </p>
</x-guest-layout>

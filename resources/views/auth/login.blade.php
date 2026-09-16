<x-guest-layout>
    <div class="auth-rise mb-7" style="animation-delay:.15s">
        <p class="text-[11px] font-semibold uppercase tracking-[0.25em] text-cherry-600">Account Access</p>
        <h1 class="mt-2 font-serif text-[26px] font-semibold leading-snug text-slate-900">Sign in</h1>
        <p class="mt-1.5 text-sm text-slate-500">Access your E-PRISM research workspace.</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="auth-rise mb-5" style="animation-delay:.2s" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="auth-rise" style="animation-delay:.2s">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1.5 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" data-rules="required|email" />
            <x-input-error :messages="$errors->get('email')" field="email" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1.5 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password"
                            data-rules="required" />

            <x-input-error :messages="$errors->get('password')" field="password" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="mt-4 flex items-center justify-between gap-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-slate-300 text-cherry-600 shadow-sm focus:ring-cherry-500" name="remember">
                <span class="ms-2 text-sm text-slate-600">{{ __('Remember me') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm font-medium text-slate-600 underline-offset-2 hover:text-cherry-700 hover:underline rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cherry-500" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif
        </div>

        <x-primary-button class="mt-6 w-full justify-center">
            {{ __('Log in') }}
        </x-primary-button>
    </form>

    @if (Route::has('register'))
        <p class="auth-rise mt-7 border-t border-slate-100 pt-5 text-center text-sm text-slate-600" style="animation-delay:.25s">
            {{ __("Don't have an account?") }}
            <a class="font-semibold text-cherry-700 underline-offset-2 hover:text-cherry-800 hover:underline rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cherry-500" href="{{ route('register') }}">
                {{ __('Register') }}
            </a>
        </p>
    @endif
</x-guest-layout>

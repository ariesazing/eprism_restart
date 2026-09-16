<x-guest-layout>
    <div class="auth-rise mb-7" style="animation-delay:.15s">
        <p class="text-[11px] font-semibold uppercase tracking-[0.25em] text-cherry-600">Security Check</p>
        <h1 class="mt-2 font-serif text-[26px] font-semibold leading-snug text-slate-900">Confirm your password</h1>
        <p class="mt-1.5 text-sm text-slate-500">
            {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
        </p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="auth-rise" style="animation-delay:.2s">
        @csrf

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1.5 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <x-primary-button class="mt-6 w-full justify-center">
            {{ __('Confirm') }}
        </x-primary-button>
    </form>
</x-guest-layout>

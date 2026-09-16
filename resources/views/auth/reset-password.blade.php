<x-guest-layout>
    <div class="auth-rise mb-7" style="animation-delay:.15s">
        <p class="text-[11px] font-semibold uppercase tracking-[0.25em] text-cherry-600">Password Recovery</p>
        <h1 class="mt-2 font-serif text-[26px] font-semibold leading-snug text-slate-900">Choose a new password</h1>
        <p class="mt-1.5 text-sm text-slate-500">Make it something you haven't used here before.</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}" class="auth-rise" style="animation-delay:.2s">
        @csrf

        <!-- Password Reset Token -->
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1.5 w-full" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" data-rules="required|email" />
            <x-input-error :messages="$errors->get('email')" field="email" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="block mt-1.5 w-full" type="password" name="password" required autocomplete="new-password" data-rules="required|password" />
            <x-input-error :messages="$errors->get('password')" field="password" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1.5 w-full"
                                type="password"
                                name="password_confirmation" required autocomplete="new-password"
                                data-rules="required|matches:password" />

            <x-input-error :messages="$errors->get('password_confirmation')" field="password_confirmation" class="mt-2" />
        </div>

        <x-primary-button class="mt-6 w-full justify-center">
            {{ __('Reset Password') }}
        </x-primary-button>
    </form>
</x-guest-layout>

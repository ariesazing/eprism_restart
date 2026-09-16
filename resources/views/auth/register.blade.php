<x-guest-layout>
    <div class="auth-rise mb-7" style="animation-delay:.15s">
        <p class="text-[11px] font-semibold uppercase tracking-[0.25em] text-cherry-600">New Researcher Account</p>
        <h1 class="mt-2 font-serif text-[26px] font-semibold leading-snug text-slate-900">Create an account</h1>
        <p class="mt-1.5 text-sm text-slate-500">Register as a researcher to start submitting to E-PRISM.</p>
    </div>

    <div id="guest-draft-register-notice" class="mb-5 hidden items-center gap-2 rounded-xl bg-cherry-50 px-3.5 py-2.5 text-xs font-medium text-cherry-700 ring-1 ring-cherry-200">
        <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-8-5a1 1 0 100 2 1 1 0 000-2zm0 4a1 1 0 011 1v4a1 1 0 11-2 0v-4a1 1 0 011-1z" clip-rule="evenodd" />
        </svg>
        We'll save the research draft you started once you finish registering.
    </div>
    <script>
        (function () {
            try {
                const raw = localStorage.getItem('eprism_guest_draft');
                if (! raw) return;
                const draft = JSON.parse(raw);
                const EXPIRY_MS = 24 * 60 * 60 * 1000;
                if (draft.savedAt && Date.now() - draft.savedAt <= EXPIRY_MS) {
                    document.getElementById('guest-draft-register-notice')?.classList.remove('hidden');
                    document.getElementById('guest-draft-register-notice')?.classList.add('flex');
                }
            } catch (e) {
                // No usable draft — registration proceeds as normal either way.
            }
        })();
    </script>

    <form method="POST" action="{{ route('register') }}" class="auth-rise" style="animation-delay:.2s">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1.5 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" data-rules="required" />
            <x-input-error :messages="$errors->get('name')" field="name" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1.5 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" data-rules="required|email" />
            <x-input-error :messages="$errors->get('email')" field="email" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1.5 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password"
                            data-rules="required|password" />

            <p class="mt-1.5 text-xs text-slate-500">At least 8 characters, with uppercase, lowercase, a number, and a symbol.</p>
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
            {{ __('Register') }}
        </x-primary-button>
    </form>

    <p class="auth-rise mt-7 border-t border-slate-100 pt-5 text-center text-sm text-slate-600" style="animation-delay:.25s">
        {{ __('Already have an account?') }}
        <a class="font-semibold text-cherry-700 underline-offset-2 hover:text-cherry-800 hover:underline rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cherry-500" href="{{ route('login') }}">
            {{ __('Sign in') }}
        </a>
    </p>
</x-guest-layout>

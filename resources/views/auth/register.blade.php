<x-guest-layout>
    <div class="mb-6">
        <h1 class="font-serif text-2xl font-semibold text-slate-900">Create an account</h1>
        <p class="mt-1 text-sm text-slate-500">Register as a researcher to start submitting to E-PRISM.</p>
    </div>

    <div id="guest-draft-register-notice" class="mb-4 hidden rounded-xl bg-cherry-50 p-3 text-xs text-cherry-700 ring-1 ring-cherry-200">
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
                }
            } catch (e) {
                // No usable draft — registration proceeds as normal either way.
            }
        })();
    </script>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <p class="mt-1 text-xs text-slate-500">At least 8 characters, with uppercase, lowercase, a number, and a symbol.</p>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-slate-600 hover:text-slate-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cherry-500" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>

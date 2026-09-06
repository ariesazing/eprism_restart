<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Applies everywhere `Password::defaults()` is used — registration, admin-created
        // accounts, the logged-in password-change form, and the emailed-reset flow — so
        // the policy only needs to be set once instead of per form.
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers()->symbols());

        // Framework doesn't wire this up on its own — without it, RegisteredUserController
        // firing Registered never actually sends the "verify your email" notification, so
        // self-registered users would sit unverified with no email ever sent to fix that.
        Event::listen(Registered::class, SendEmailVerificationNotification::class);
    }
}

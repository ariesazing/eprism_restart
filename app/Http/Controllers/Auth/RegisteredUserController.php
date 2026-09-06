<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        // 'unique' enforces one account per email address; a soft-deleted account still
        // holds its address (the users.email unique index is never cleared), so a deleted
        // email can't be re-registered either. A malformed or already-taken address fails
        // here and redirects back to the register form with the message below, rather than
        // creating the account and taking the visitor on to the "verify your email" screen.
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'email.email' => 'That email address looks incorrect. Please check it and try again.',
            'email.unique' => 'That email address is already registered. Try signing in instead.',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => UserRole::RESEARCHER,
            'status' => AccountStatus::ACTIVE,
        ]);

        event(new Registered($user));

        Auth::login($user);

        $this->activity->log($user, 'auth.register', $user, $user->name.' registered as a researcher.');

        return redirect(route('dashboard', absolute: false));
    }
}

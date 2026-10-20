<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Controllers\RegisteredUserController as BaseRegisteredUserController;
use Throwable;

class RegisteredUserController extends BaseRegisteredUserController
{
    /**
     * Create a new registered user.
     *
     * Fortify sends the verification email synchronously from a listener on
     * the Registered event, before the user is logged in. A mail transport
     * failure therefore aborts the request after the account row has already
     * been committed: the user sees a 500, stays logged out, and cannot
     * register again because their email is now taken.
     *
     * Logging the user in regardless keeps the account usable. They land on
     * the verification notice and can resend the email once delivery
     * recovers, while the failure is reported instead of silently lost.
     */
    public function store(Request $request, CreatesNewUsers $creator): RegisterResponse
    {
        if (config('fortify.lowercase_usernames') && $request->has(Fortify::username())) {
            $request->merge([
                Fortify::username() => Str::lower($request->{Fortify::username()}),
            ]);
        }

        $user = $creator->create($request->all());

        $verificationEmailFailed = false;

        try {
            event(new Registered($user));
        } catch (Throwable $exception) {
            report($exception);

            $verificationEmailFailed = true;
        }

        $this->guard->login($user, $request->boolean('remember'));

        if ($request->hasSession()) {
            $request->session()->regenerate();

            if ($verificationEmailFailed) {
                $request->session()->put('verification_email_failed', true);
            }
        }

        return app(RegisterResponse::class);
    }
}

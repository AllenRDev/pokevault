<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A mail transport that is down must not turn a valid form submission into a
 * 500. Registration in particular commits the user row before the
 * verification email is sent, so failing loudly there would leave an account
 * that the user cannot get back into.
 */

test('registration stays usable when the verification email cannot be sent', function () {
    useFailingMailer();

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false))
        ->assertSessionHas('verification_email_failed', true);

    $this->assertAuthenticated();

    $user = User::where('email', 'test@example.com')->sole();

    expect($user->hasVerifiedEmail())->toBeFalse();
});

test('resending the verification email survives a mail transport failure', function () {
    useFailingMailer();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->from(route('verification.notice'))
        ->post(route('verification.send'))
        ->assertRedirect(route('verification.notice'))
        ->assertSessionHas('verification_email_failed', true);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('a password reset request survives a mail transport failure', function () {
    useFailingMailer();

    $user = User::factory()->create();

    $this->from(route('password.request'))
        ->post(route('password.email'), ['email' => $user->email])
        ->assertRedirect(route('password.request'))
        ->assertSessionHasErrors('email');
});

test('the verification screen explains when the email could not be sent', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->withSession(['verification_email_failed' => true])
        ->get(route('verification.notice'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/verify-email')
            ->where('mailFailed', true));
});

test('the mail test command reports a transport failure', function () {
    useFailingMailer();

    $this->artisan('mail:test', ['email' => 'test@example.com'])
        ->expectsOutputToContain('Mail delivery failed')
        ->assertFailed();
});

test('the mail test command succeeds with a working transport', function () {
    config()->set('mail.default', 'array');

    $this->artisan('mail:test', ['email' => 'test@example.com'])
        ->expectsOutputToContain('Test message sent')
        ->assertSuccessful();
});

<?php

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('an account stays usable when the verification email cannot be sent', function () {
    Mail::extend('exploding', fn () => new class extends AbstractTransport
    {
        protected function doSend(SentMessage $message): void
        {
            throw new TransportException('mail is down');
        }

        public function __toString(): string
        {
            return 'exploding';
        }
    });

    config()->set('mail.mailers.exploding', ['transport' => 'exploding']);
    config()->set('mail.default', 'exploding');

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    /*
     * Fortify sends the verification email before logging the user in, so a
     * transport failure used to produce a 500 after the account row was
     * committed. The request must still succeed and authenticate the user.
     */
    $response->assertRedirect(route('dashboard', absolute: false))
        ->assertSessionHas('verification_email_failed', true);

    $this->assertAuthenticated();

    $user = User::where('email', 'test@example.com')->sole();

    expect($user->hasVerifiedEmail())->toBeFalse();
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

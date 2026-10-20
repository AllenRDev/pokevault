<?php

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Symfony\Component\Mime\Email;

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * The messages actually handed to the mail transport.
 *
 * Tests run on the `array` mailer, so nothing leaves the process. Reading the
 * transport directly is the only way to see the message a real recipient
 * would receive: faking notifications would intercept the message before the
 * body is ever rendered, which is exactly the part that can break.
 *
 * @return array<Email>
 */
function deliveredMail(): array
{
    $transport = Mail::mailer('array')->getSymfonyTransport();

    return $transport->messages()
        ->map(fn ($message) => $message->getOriginalMessage())
        ->all();
}

/**
 * Pull a URL out of a rendered message.
 */
function urlIn(Email $message, string $pattern): ?string
{
    return preg_match($pattern, (string) $message->getHtmlBody(), $matches)
        ? html_entity_decode($matches[1])
        : null;
}

/**
 * Boot the provider as if the application were in production.
 */
function bootAsProduction(): AppServiceProvider
{
    app()->detectEnvironment(fn () => 'production');

    return tap(new AppServiceProvider(app()))->boot();
}

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::emailVerification());
    $this->skipUnlessFortifyHas(Features::resetPasswords());
});

afterEach(function () {
    // Put the environment back so later tests are not booted as production.
    app()->detectEnvironment(fn () => 'testing');
});

/*
|--------------------------------------------------------------------------
| A real message reaches the transport
|--------------------------------------------------------------------------
*/

test('registering delivers a verification email containing a signed link', function () {
    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $messages = deliveredMail();

    expect($messages)->toHaveCount(1);

    $message = $messages[0];

    expect($message->getTo())->toHaveCount(1);
    expect($message->getTo()[0]->getAddress())->toBe('test@example.com');

    expect(urlIn($message, '#href="([^"]*/email/verify/[^"]*)"#'))
        ->toContain('/email/verify/')
        ->not->toBeNull();
});

test('the link inside the delivered email actually verifies the account', function () {
    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'test@example.com')->sole();

    expect($user->hasVerifiedEmail())->toBeFalse();

    $link = urlIn(deliveredMail()[0], '#href="([^"]*/email/verify/[^"]*)"#');

    /*
     * The link is absolute and signed against APP_URL, so the token is only
     * valid when that URL is right. Rebuilding the request against the host
     * in the message is what makes this an end-to-end check rather than a
     * restatement of the unit test.
     */
    $path = parse_url((string) $link, PHP_URL_PATH).'?'.parse_url((string) $link, PHP_URL_QUERY);

    $this->actingAs($user)->get($path)->assertRedirect();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('a password reset email carries a token that actually resets the password', function () {
    $user = User::factory()->create();

    $this->post(route('password.email'), [
        'email' => $user->email,
    ])->assertRedirect();

    $messages = deliveredMail();

    expect($messages)->toHaveCount(1);

    $link = urlIn($messages[0], '#href="([^"]*/reset-password/[^"]*)"#');

    expect($link)->not->toBeNull();

    // Fortify links to /reset-password/{token}.
    $token = rawurldecode(basename((string) parse_url((string) $link, PHP_URL_PATH)));

    expect($token)->not->toBe('reset-password');

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('login'));

    expect(Hash::check('a-brand-new-password', $user->fresh()->password))->toBeTrue();
});

test('the message is sent from the configured application address', function () {
    config()->set('mail.from.address', 'pokevault@cards.test');

    $user = User::factory()->unverified()->create();

    Notification::send($user, new VerifyEmail);

    expect(deliveredMail()[0]->getFrom()[0]->getAddress())
        ->toBe('pokevault@cards.test');
});

/*
|--------------------------------------------------------------------------
| A deployment that cannot send mail must not look healthy
|--------------------------------------------------------------------------
*/

test('production refuses to boot on a mailer that delivers nothing', function () {
    config()->set('mail.default', 'log');

    bootAsProduction();
})->throws(RuntimeException::class, 'no email can be delivered');

test('production refuses to boot on the placeholder from address', function () {
    config()->set('mail.default', 'resend');
    config()->set('mail.from.address', 'hello@example.com');
    config()->set('services.resend.key', 're_test');

    bootAsProduction();
})->throws(RuntimeException::class, 'still the default');

test('production refuses to boot when resend has no key', function () {
    config()->set('mail.default', 'resend');
    config()->set('mail.from.address', 'pokevault@cards.test');
    config()->set('services.resend.key', null);

    bootAsProduction();
})->throws(RuntimeException::class, 'RESEND_API_KEY is empty');

test('production refuses to boot on a malformed from address', function () {
    config()->set('mail.default', 'smtp');
    config()->set('mail.from.address', 'not-an-address');

    bootAsProduction();
})->throws(RuntimeException::class, 'not a valid email address');

test('production refuses to boot on smtp without a host', function () {
    config()->set('mail.default', 'smtp');
    config()->set('mail.from.address', 'pokevault@cards.test');
    config()->set('mail.mailers.smtp.host', null);

    bootAsProduction();
})->throws(RuntimeException::class, 'MAIL_HOST is empty');

test('production boots cleanly on a complete smtp configuration', function () {
    config()->set('mail.default', 'smtp');
    config()->set('mail.from.address', 'pokevault@cards.test');
    config()->set('mail.mailers.smtp.host', 'smtp.example.test');

    expect(bootAsProduction())->toBeInstanceOf(AppServiceProvider::class);
});

test('production boots cleanly on a complete transport configuration', function () {
    config()->set('mail.default', 'resend');
    config()->set('mail.from.address', 'pokevault@cards.test');
    config()->set('services.resend.key', 're_test');

    expect(bootAsProduction())->toBeInstanceOf(AppServiceProvider::class);
});

test('the configured resend transport can actually be built', function () {
    config()->set('mail.default', 'resend');
    config()->set('mail.from.address', 'pokevault@cards.test');
    config()->set('services.resend.key', 're_test');

    /*
     * The production mail guard only checks that a key is present, so a
     * missing resend/resend-php package would slip through boot and only blow
     * up when the verification email is sent - after the account row has
     * already been committed. Resolving the transport here catches that.
     */
    expect(Mail::mailer('resend')->getSymfonyTransport())
        ->toBeInstanceOf(ResendTransport::class);
});

test('a non production environment is not subject to the mail guard', function () {
    config()->set('mail.default', 'log');
    config()->set('mail.from.address', 'hello@example.com');

    /*
     * Local and test runs are expected to log mail rather than send it, so
     * booting with the placeholder still in place has to stay allowed.
     */
    expect(app()->environment())->toBe('testing');

    expect(tap(new AppServiceProvider(app()))->boot())
        ->toBeInstanceOf(AppServiceProvider::class);
});

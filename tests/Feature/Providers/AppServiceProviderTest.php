<?php

use App\Providers\AppServiceProvider;
use Illuminate\Validation\Rules\Password;

/**
 * Boot the provider as if the application were in production.
 */
function bootProviderAsProduction(): AppServiceProvider
{
    app()->detectEnvironment(fn () => 'production');

    return tap(new AppServiceProvider(app()))->boot();
}

afterEach(function () {
    app()->detectEnvironment(fn () => 'testing');
});

test('production requires a strengthened default password', function () {
    /*
     * The mail guard runs first, so a complete transport has to be configured
     * before the provider will boot far enough to register the policy.
     */
    config()->set('mail.default', 'resend');
    config()->set('mail.from.address', 'pokevault@cards.test');
    config()->set('services.resend.key', 're_test');

    bootProviderAsProduction();

    expect(Password::defaults()->toPasswordRulesString())
        ->toContain('minlength: 12')
        ->toContain('required: lower')
        ->toContain('required: upper')
        ->toContain('required: digit')
        ->toContain('required: special');
});

test('a non production environment keeps the framework password default', function () {
    expect(Password::defaults()->toPasswordRulesString())
        ->not->toContain('minlength: 12');
});

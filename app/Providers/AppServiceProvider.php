<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

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
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->guardEmailConfiguration();
    }

    /**
     * Refuse to boot in production with mail that cannot be delivered.
     *
     * Every authenticated route sits behind the `verified` middleware, so an
     * account is unusable until its verification email arrives. A silent
     * misconfiguration here therefore locks every new user out of the
     * application with no obvious cause, which is far worse than failing on
     * deploy with a message naming the missing value.
     */
    protected function guardEmailConfiguration(): void
    {
        if (! app()->isProduction()) {
            return;
        }

        $mailer = config('mail.default');

        // Nothing reaches a mailbox from these, so a production deployment
        // using one of them is almost certainly a mistake.
        if (in_array($mailer, ['log', 'array'], true)) {
            throw new RuntimeException(
                "MAIL_MAILER is [{$mailer}], so no email can be delivered and no "
                .'account can be verified. Set MAIL_MAILER to a real transport.'
            );
        }

        $from = (string) config('mail.from.address');

        if (filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException(
                'MAIL_FROM_ADDRESS is not a valid email address, so every '
                ."message would be sent from [{$from}]."
            );
        }

        /*
         * The stock placeholder is accepted by every mailer but is rejected
         * by the receiving domain's spam policy, so it fails after the
         * deployment is already live and looking healthy.
         */
        if (str_ends_with(strtolower($from), '@example.com')) {
            throw new RuntimeException(
                "MAIL_FROM_ADDRESS is still the default [{$from}]. Set it to an "
                .'address on a domain the mail provider has verified.'
            );
        }

        if ($mailer === 'resend' && empty((string) config('services.resend.key'))) {
            throw new RuntimeException(
                'MAIL_MAILER is [resend] but RESEND_API_KEY is empty.'
            );
        }

        if ($mailer === 'smtp' && empty((string) config('mail.mailers.smtp.host'))) {
            throw new RuntimeException(
                'MAIL_MAILER is [smtp] but MAIL_HOST is empty.'
            );
        }
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(60)->by(
                $request->user()?->id ?: $request->ip()
            );
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}

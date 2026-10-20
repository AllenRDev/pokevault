<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        /*
         * Rate limit the stateless API surface. These endpoints proxy the
         * public TCGdex API, so they are unauthenticated and must be
         * throttled to keep the upstream quota from being exhausted.
         */
        $middleware->throttleApi();

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
         * A broken mail transport should not turn an otherwise valid form
         * submission into a 500. Registration and verification resends handle
         * this themselves; this is the safety net for the remaining mail
         * flows, such as password resets. The exception is still reported, so
         * the real provider error remains in the logs.
         */
        $exceptions->render(function (TransportExceptionInterface $exception, Request $request) {
            report($exception);

            $message = 'We could not send that email. Please try again shortly.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 503);
            }

            return back()->withErrors(['email' => $message]);
        });
    })->create();

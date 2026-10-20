<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\EmailVerificationNotificationSentResponse;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController as BaseController;
use Throwable;

class EmailVerificationNotificationController extends BaseController
{
    /**
     * Send a new email verification notification.
     *
     * Unlike registration, this endpoint has no account creation to protect,
     * but a failed transport would still turn the "resend" button into a 500
     * with no way for the user to tell what happened. Report the failure and
     * return to the verification screen, which explains that the message
     * could not be sent and lets them try again.
     */
    public function store(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return parent::store($request);
        }

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('verification_email_failed', true);
        }

        return app(EmailVerificationNotificationSentResponse::class);
    }
}

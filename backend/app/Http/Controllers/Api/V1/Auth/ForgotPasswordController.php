<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController
{
    /**
     * Every outcome (unknown or deactivated address, link sent a moment ago,
     * link sent) gets the same empty response, so the endpoint cannot be used
     * to learn which addresses have accounts. The mail goes out after the
     * response for the same reason: a slow or failing mail server would
     * otherwise only show for real accounts. A failed send is reported.
     */
    public function __invoke(ForgotPasswordRequest $request): Response
    {
        Password::broker()->sendResetLink(
            ['email' => $request->validated('email'), 'is_active' => true],
            function (User $user, string $token): void {
                defer(fn () => rescue(fn () => $user->sendPasswordResetNotification($token)));
            },
        );

        return response()->noContent();
    }
}

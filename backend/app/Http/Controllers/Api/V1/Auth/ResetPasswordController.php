<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResetPasswordController
{
    public function __invoke(ResetPasswordRequest $request): Response
    {
        $status = Password::broker()->reset(
            [...$request->validated(), 'is_active' => true],
            function (User $user, string $password): void {
                // The person chose this password, so a pending forced change is satisfied.
                // The new hash also signs out their other sessions (Sanctum's AuthenticateSession).
                $user->forceFill([
                    'password' => $password,
                    'must_change_password' => false,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        // An unknown or deactivated address gets the same answer as a wrong or expired token.
        if ($status !== PasswordBroker::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => [__('passwords.token')],
            ]);
        }

        return response()->noContent();
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Mail\UserInvitationMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AdminUserInvitationController
{
    public function __invoke(Request $request, User $user): Response
    {
        abort_if($request->user()->id === $user->id, 403);
        abort_if($user->hasRole('superadmin'), 403, 'Superadmin accounts cannot receive invitations.');
        abort_if(! $user->is_active, 409, 'Inactive users cannot receive invitations; reactivate the account first.');

        // The original password is not recoverable, so the invitation carries a
        // fresh one and the user picks their own at first sign-in.
        $password = Str::password(12);

        $user->forceFill([
            'password' => $password,
            'must_change_password' => true,
        ])->save();

        Mail::to($user)->send(new UserInvitationMail($user, $password));

        return response()->noContent();
    }
}

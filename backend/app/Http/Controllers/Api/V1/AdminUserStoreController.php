<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\User\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Mail\UserInvitationMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class AdminUserStoreController
{
    public function __invoke(StoreUserRequest $request): JsonResponse
    {
        $payload = $request->validated();

        // An email that belongs to a deleted account revives that account instead of failing
        // on the unique index; the new details replace the old ones.
        $user = User::withTrashed()->firstOrNew(['email' => $payload['email']]);
        if ($user->trashed()) {
            $user->restore();
        }

        $user->fill($payload);
        // The admin vouches for the address (mirrors TeamUserSeeder); the column is guarded.
        $user->email_verified_at ??= now();

        if ($payload['send_invitation'] ?? false) {
            $user->must_change_password = true;
        }

        $user->save();
        $user->syncRoles([$payload['role']]);
        $user->refresh();

        if ($payload['send_invitation'] ?? false) {
            Mail::to($user)->send(new UserInvitationMail($user, $payload['password']));
        }

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }
}

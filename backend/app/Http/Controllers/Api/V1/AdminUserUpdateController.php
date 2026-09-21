<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminUserUpdateController
{
    public function __invoke(UpdateUserRequest $request, User $user): JsonResponse
    {
        abort_if($request->user()->id === $user->id, 403);

        $payload = $request->validated();

        if (($payload['password'] ?? null) === null) {
            unset($payload['password']);
        }
        if (isset($payload['role'])) {
            $user->syncRoles([$payload['role']]);
            unset($payload['role']);
        }
        $user->fill($payload);
        $user->save();

        return (new UserResource($user))->response();
    }
}

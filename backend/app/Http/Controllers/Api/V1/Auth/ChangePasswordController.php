<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

class ChangePasswordController
{
    public function __invoke(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->password = $request->validated()['password'];
        $user->must_change_password = false;
        $user->save();

        return response()->json(['data' => new UserResource($user)]);
    }
}

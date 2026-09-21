<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminUserShowController
{
    public function __invoke(User $user): JsonResponse
    {
        return (new UserResource($user))->response();
    }
}

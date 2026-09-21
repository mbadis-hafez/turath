<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\User\UserIndexRequest;
use App\Http\Resources\UserListResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminUserIndexController
{
    public function __invoke(UserIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $query = User::query()->with('roles')->orderBy('id')
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(
                fn ($inner) => $inner->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")
            ))
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->role($role));

        return UserListResource::collection(
            $query->paginate((int) ($filters['per_page'] ?? 24))
        )->response();
    }
}

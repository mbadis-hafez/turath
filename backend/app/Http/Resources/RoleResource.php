<?php

namespace App\Http\Resources;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Role $role */
        $role = $this->resource;

        return [
            'id' => $role->id,
            'name' => $role->name,
            'display_name' => ['ar' => $role->name_ar, 'en' => $role->name_en],
            'description' => ['ar' => $role->description_ar, 'en' => $role->description_en],
            'is_built_in' => $role->is_built_in,
            'permissions_editable' => $role->name !== 'superadmin',
            'users_count' => $this->whenCounted('users'),
            'permissions_count' => $this->whenCounted('permissions'),
            'updated_at' => $role->updated_at?->toIso8601String(),
            'permissions' => $this->when(
                $role->relationLoaded('permissions'),
                fn () => $role->permissions->pluck('name')->values()->all(),
            ),
        ];
    }
}

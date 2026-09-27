<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;

/**
 * All fields optional. `name` and `is_built_in` are never accepted — a
 * role's identifier is immutable (invariant I1) and its built-in status is
 * stamped only by the seeder. `updated_at`, when present, is the concurrency
 * precondition (FR-011): the controller compares it against the stored
 * value before applying anything.
 */
class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name_ar' => ['sometimes', 'string', 'max:80'],
            'name_en' => ['sometimes', 'string', 'max:80'],
            'description_ar' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description_en' => ['sometimes', 'nullable', 'string', 'max:255'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
            'updated_at' => ['sometimes', 'date'],
        ];
    }
}

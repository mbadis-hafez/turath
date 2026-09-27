<?php

namespace App\Http\Requests\Role;

use App\Support\Access\RoleSlugGenerator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * `name` (the immutable identifier) and `is_built_in` are never accepted from
 * the client — the name is derived from name_en and frozen, and every role
 * created here is a custom role by definition.
 */
class StoreRoleRequest extends FormRequest
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
            'name_ar' => ['required', 'string', 'max:80'],
            'name_en' => ['required', 'string', 'max:80'],
            'description_ar' => ['nullable', 'string', 'max:255'],
            'description_en' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $nameEn = $this->input('name_en');
            if (! is_string($nameEn) || $nameEn === '') {
                return;
            }

            if (RoleSlugGenerator::exists(RoleSlugGenerator::generate($nameEn))) {
                $validator->errors()->add('name_en', 'A role with this name already exists.');
            }
        });
    }
}

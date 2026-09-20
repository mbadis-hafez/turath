<?php

namespace App\Http\Requests\Holder;

use App\Enums\HolderType;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateHolderRequest extends HolderPayloadRequest
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
        $holderId = $this->route('holder');

        return array_merge(
            $this->nameRules(partial: true),
            $this->placeRules('city', partial: true),
            $this->placeRules('country', partial: true),
            [
                'legacy_code' => [
                    'sometimes', 'nullable', 'string', 'regex:/^[A-Z]{2,4}\d{3}$/',
                    Rule::unique('holders', 'legacy_code')->ignore($holderId),
                ],
                'type' => ['sometimes', new Enum(HolderType::class)],
                'is_public_name' => ['sometimes', 'nullable', 'boolean'],
                'is_estate' => ['sometimes', 'nullable', 'boolean'],
                'internal_notes' => ['sometimes', 'nullable', 'string', 'max:20000'],
                'edit_summary' => ['nullable', 'string', 'max:255'],
            ],
        );
    }
}

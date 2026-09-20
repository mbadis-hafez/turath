<?php

namespace App\Http\Requests\Holder;

use App\Enums\HolderType;
use Illuminate\Validation\Rules\Enum;

class StoreHolderRequest extends HolderPayloadRequest
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
        return array_merge(
            $this->nameRules(partial: false),
            $this->placeRules('city', partial: false),
            $this->placeRules('country', partial: false),
            [
                'legacy_code' => ['nullable', 'string', 'regex:/^[A-Z]{2,4}\d{3}$/', 'unique:holders,legacy_code'],
                'type' => ['required', new Enum(HolderType::class)],
                'is_public_name' => ['nullable', 'boolean'],
                'is_estate' => ['nullable', 'boolean'],
                'internal_notes' => ['nullable', 'string', 'max:20000'],
                'edit_summary' => ['nullable', 'string', 'max:255'],
            ],
        );
    }
}

<?php

namespace App\Http\Requests\Artist;

use App\Enums\NameVariantType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateArtistVariantRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'language' => ['sometimes', 'string', 'in:ar,en,und'],
            'type' => ['sometimes', new Enum(NameVariantType::class)],
            'source_note' => ['nullable', 'string', 'max:255'],
        ];
    }
}

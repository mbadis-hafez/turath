<?php

namespace App\Http\Requests\Artist;

use App\Enums\LivingStatus;
use App\Enums\VerifiedStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ArtistIndexRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:100'],
            'verified_status' => ['nullable', new Enum(VerifiedStatus::class)],
            'living_status' => ['nullable', new Enum(LivingStatus::class)],
            'status' => ['nullable', 'string', 'in:all'],
            'sort' => ['nullable', 'string', 'in:name_ar,-name_ar,name_en,-name_en,created_at,-created_at'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

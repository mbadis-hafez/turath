<?php

namespace App\Http\Requests\Artist;

use App\Enums\LivingStatus;
use App\Enums\RightsStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Live completeness preview for the artist create page: every field is
 * nullable, nothing is persisted — the controller builds an unsaved
 * Artist and evaluates the rules against it.
 */
class ArtistCompletenessPreviewRequest extends FormRequest
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
            'legacy_code' => ['nullable', 'string', 'max:100'],
            'name' => ['nullable', 'array'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'array'],
            'bio.ar' => ['nullable', 'string', 'max:20000'],
            'bio.en' => ['nullable', 'string', 'max:20000'],
            'living_status' => ['nullable', new Enum(LivingStatus::class)],
            'birth' => ['nullable', 'array'],
            'birth.year_from' => ['nullable', 'integer'],
            'death' => ['nullable', 'array'],
            'death.year_from' => ['nullable', 'integer'],
            'birth_place' => ['nullable', 'array'],
            'birth_place.ar' => ['nullable', 'string', 'max:255'],
            'birth_place.en' => ['nullable', 'string', 'max:255'],
            'nationality' => ['nullable', 'array'],
            'nationality.ar' => ['nullable', 'string', 'max:120'],
            'nationality.en' => ['nullable', 'string', 'max:120'],
            'portrait_uploaded' => ['nullable', 'boolean'],
            'portrait_rights_status' => ['nullable', new Enum(RightsStatus::class)],
        ];
    }
}

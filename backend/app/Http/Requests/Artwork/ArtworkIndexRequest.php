<?php

namespace App\Http\Requests\Artwork;

use App\Enums\ArtworkCategory;
use App\Enums\AttributionCertainty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ArtworkIndexRequest extends FormRequest
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
            'artist_id' => ['nullable', 'integer', 'exists:artists,id'],
            'artist_slug' => ['nullable', 'string', 'max:160'],
            'category' => ['nullable', new Enum(ArtworkCategory::class)],
            'holder_id' => ['nullable', 'integer', 'exists:holders,id'],
            'year_from' => ['nullable', 'integer'],
            'year_to' => ['nullable', 'integer'],
            'attribution_certainty' => ['nullable', new Enum(AttributionCertainty::class)],
            'status' => ['nullable', 'string', 'in:all'],
            'sort' => ['nullable', 'string', 'in:-created_at,created_at,title_ar,title_en,creation_year_from,-creation_year_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

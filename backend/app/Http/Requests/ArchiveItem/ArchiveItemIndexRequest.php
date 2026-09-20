<?php

namespace App\Http\Requests\ArchiveItem;

use App\Enums\ArchiveItemType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ArchiveItemIndexRequest extends FormRequest
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
            'item_type' => ['nullable', new Enum(ArchiveItemType::class)],
            'artist_id' => ['nullable', 'integer', 'exists:artists,id'],
            'artwork_id' => ['nullable', 'integer', 'exists:artworks,id'],
            'year_from' => ['nullable', 'integer'],
            'year_to' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'in:all'],
            'sort' => ['nullable', 'string', 'in:-created_at,created_at,content_year_from,-content_year_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

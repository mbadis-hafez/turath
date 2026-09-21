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
    /** A single `item_type=x` still works: it is read as a one-item list. */
    protected function prepareForValidation(): void
    {
        foreach (['item_type', 'place', 'theme_id', 'access'] as $key) {
            if ($this->has($key) && ! is_array($this->input($key))) {
                $this->merge([$key => [$this->input($key)]]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'item_type' => ['nullable', 'array'],
            'item_type.*' => [new Enum(ArchiveItemType::class)],
            'place' => ['nullable', 'array', 'max:20'],
            'place.*' => ['string', 'max:120'],
            'theme_id' => ['nullable', 'array', 'max:20'],
            'theme_id.*' => ['integer'],
            'access' => ['nullable', 'array', 'max:2'],
            'access.*' => ['in:full,preview'],
            'include_facets' => ['nullable', 'boolean'],
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

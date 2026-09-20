<?php

namespace App\Http\Requests\Completeness;

use App\Enums\SourceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreFieldCitationRequest extends FormRequest
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
            'field_key' => ['required', 'string', 'max:60'],
            'source_id' => ['required_without:new_source', 'nullable', 'uuid', 'exists:sources,id'],
            'new_source' => ['required_without:source_id', 'nullable', 'array'],
            'new_source.source_type' => ['required_with:new_source', new Enum(SourceType::class)],
            'new_source.title_ar' => ['nullable', 'string', 'max:255'],
            'new_source.title_en' => ['nullable', 'string', 'max:255'],
            'new_source.publisher_or_outlet' => ['nullable', 'string', 'max:255'],
            'new_source.reference_note' => ['nullable', 'string', 'max:255'],
            'new_source.url' => ['nullable', 'string', 'max:500'],
            'new_source.year' => ['nullable', 'integer'],
            'claimed_value' => ['required'],
        ];
    }
}

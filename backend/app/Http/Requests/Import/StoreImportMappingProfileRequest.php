<?php

namespace App\Http\Requests\Import;

use Illuminate\Foundation\Http\FormRequest;

class StoreImportMappingProfileRequest extends FormRequest
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
            'entity_type' => ['required', 'string', 'in:artist,artwork,holder,archive_item'],
            'name' => ['required', 'string', 'max:255'],
            'column_map' => ['required', 'array'],
        ];
    }
}

<?php

namespace App\Http\Requests\Import;

use Illuminate\Foundation\Http\FormRequest;

class StoreImportBatchRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:51200'],
            'entity_type' => ['required', 'string', 'in:artist,artwork,holder,archive_item'],
            'mapping_profile_id' => ['nullable', 'uuid', 'exists:import_mapping_profiles,id'],
            'column_map' => ['required_without:mapping_profile_id', 'array'],
        ];
    }
}

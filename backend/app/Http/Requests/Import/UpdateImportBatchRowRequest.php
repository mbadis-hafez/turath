<?php

namespace App\Http\Requests\Import;

use App\Enums\ImportRowResolution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateImportBatchRowRequest extends FormRequest
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
            'resolution' => ['sometimes', new Enum(ImportRowResolution::class)],
            'matched_entity_id' => ['nullable', 'integer'],
            'mapped_data' => ['sometimes', 'array'],
        ];
    }
}

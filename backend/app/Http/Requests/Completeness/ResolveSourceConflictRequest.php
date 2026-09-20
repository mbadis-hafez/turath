<?php

namespace App\Http\Requests\Completeness;

use Illuminate\Foundation\Http\FormRequest;

class ResolveSourceConflictRequest extends FormRequest
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
            'resolved_source_id' => ['required', 'uuid', 'exists:sources,id'],
            'resolution_note' => ['nullable', 'string', 'max:2000'],
            'edit_summary' => ['nullable', 'string', 'max:255'],
        ];
    }
}

<?php

namespace App\Http\Requests\ArchiveItem;

use Illuminate\Foundation\Http\FormRequest;

class PublishArchiveItemRequest extends FormRequest
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
            'edit_summary' => ['nullable', 'string', 'max:255'],
        ];
    }
}

<?php

namespace App\Http\Requests\Artist;

use App\Enums\VerifiedStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyArtistRequest extends FormRequest
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
            'status' => ['required', Rule::in([VerifiedStatus::Verified->value, VerifiedStatus::Disputed->value])],
            'edit_summary' => ['nullable', 'string', 'max:255'],
        ];
    }
}

<?php

namespace App\Http\Requests\Completeness;

use App\Enums\ContentPermissionRequestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreContentPermissionRequestRequest extends FormRequest
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
            'request_type' => ['required', new Enum(ContentPermissionRequestType::class)],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}

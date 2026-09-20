<?php

namespace App\Http\Requests\ArchiveItem;

use App\Enums\ArchiveLinkRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreArchiveItemLinkRequest extends FormRequest
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
            'linkable_type' => ['required', 'string', 'in:artist,artwork'],
            'linkable_id' => ['required', 'integer'],
            'role' => ['required', new Enum(ArchiveLinkRole::class)],
        ];
    }
}

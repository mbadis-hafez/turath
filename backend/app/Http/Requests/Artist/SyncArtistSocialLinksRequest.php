<?php

namespace App\Http\Requests\Artist;

use App\Models\ArtistSocialLink;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncArtistSocialLinksRequest extends FormRequest
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
            'links' => ['present', 'array', 'max:30'],
            'links.*.id' => ['nullable', 'integer'],
            'links.*.platform' => ['required', Rule::in(ArtistSocialLink::PLATFORMS)],
            'links.*.url' => ['required', 'url', 'max:500'],
            'links.*.is_public' => ['sometimes', 'boolean'],
            'edit_summary' => ['nullable', 'string', 'max:255'],
        ];
    }
}

<?php

namespace App\Http\Requests\Artist;

use Illuminate\Validation\Rule;

class UpdateArtistRequest extends ArtistPayloadRequest
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
        $artistId = $this->route('artist');

        return array_merge(
            $this->nameRules(partial: true),
            $this->bioRules(partial: true),
            $this->dateRules('birth', partial: true),
            $this->dateRules('death', partial: true),
            $this->statusRules(partial: true),
            [
                'legacy_code' => [
                    'sometimes', 'nullable', 'string', 'regex:/^[A-Z]{2}\d{3}$/',
                    Rule::unique('artists', 'legacy_code')->ignore($artistId),
                ],
                'edit_summary' => ['nullable', 'string', 'max:255'],
            ],
        );
    }
}

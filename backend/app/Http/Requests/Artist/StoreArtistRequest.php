<?php

namespace App\Http\Requests\Artist;

class StoreArtistRequest extends ArtistPayloadRequest
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
        return array_merge(
            $this->nameRules(partial: false),
            $this->bioRules(partial: false),
            $this->dateRules('birth', partial: false),
            $this->dateRules('death', partial: false),
            $this->statusRules(partial: false),
            [
                'legacy_code' => ['nullable', 'string', 'regex:/^[A-Z]{2}\d{3}$/', 'unique:artists,legacy_code'],
                'edit_summary' => ['nullable', 'string', 'max:255'],
            ],
        );
    }
}

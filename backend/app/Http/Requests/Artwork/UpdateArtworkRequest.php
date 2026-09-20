<?php

namespace App\Http\Requests\Artwork;

use App\Enums\ArtworkCategory;
use App\Enums\AttributionCertainty;
use App\Enums\PublicationStatus;
use App\Enums\SignedStatus;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateArtworkRequest extends ArtworkPayloadRequest
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
        $artworkId = $this->route('artwork');

        return array_merge(
            $this->titleRules(partial: true),
            $this->dimensionsRules('dimensions', partial: true),
            $this->dimensionsRules('frame_dimensions', partial: true),
            $this->creationRules(partial: true),
            [
                'legacy_ref' => [
                    'sometimes', 'nullable', 'string', 'max:40',
                    Rule::unique('artworks', 'legacy_ref')->ignore($artworkId),
                ],
                'artist_id' => ['sometimes', 'nullable', 'integer', 'exists:artists,id'],
                'attribution_certainty' => ['sometimes', new Enum(AttributionCertainty::class)],
                'category' => ['sometimes', new Enum(ArtworkCategory::class)],
                'medium' => ['sometimes', 'nullable', 'array'],
                'medium.ar' => ['nullable', 'string', 'max:255'],
                'medium.en' => ['nullable', 'string', 'max:255'],
                'edition_number' => ['sometimes', 'nullable', 'string', 'max:20'],
                'edition_size' => ['sometimes', 'nullable', 'integer', 'min:0'],
                'weight_kg' => ['sometimes', 'nullable', 'numeric', 'min:0'],
                'signed' => ['sometimes', new Enum(SignedStatus::class)],
                'holder_id' => ['sometimes', 'nullable', 'integer', 'exists:holders,id'],
                'holder_inventory_no' => ['sometimes', 'nullable', 'string', 'max:60'],
                'notes' => ['sometimes', 'nullable', 'array'],
                'notes.ar' => ['nullable', 'string', 'max:20000'],
                'notes.en' => ['nullable', 'string', 'max:20000'],
                'publication_status' => ['sometimes', new Enum(PublicationStatus::class)],
                'edit_summary' => ['nullable', 'string', 'max:255'],
            ],
        );
    }
}

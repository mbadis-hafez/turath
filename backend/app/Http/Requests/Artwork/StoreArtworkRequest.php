<?php

namespace App\Http\Requests\Artwork;

use App\Enums\ArtworkCategory;
use App\Enums\AttributionCertainty;
use App\Enums\PublicationStatus;
use App\Enums\SignedStatus;
use Illuminate\Validation\Rules\Enum;

class StoreArtworkRequest extends ArtworkPayloadRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function requiresTitleWhenNotUntitled(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(
            $this->titleRules(partial: false),
            $this->dimensionsRules('dimensions', partial: false),
            $this->dimensionsRules('frame_dimensions', partial: false),
            $this->creationRules(partial: false),
            [
                'legacy_ref' => ['nullable', 'string', 'max:40', 'unique:artworks,legacy_ref'],
                'artist_id' => ['nullable', 'integer', 'exists:artists,id'],
                'attribution_certainty' => ['required', new Enum(AttributionCertainty::class)],
                'category' => ['required', new Enum(ArtworkCategory::class)],
                'medium' => ['nullable', 'array'],
                'medium.ar' => ['nullable', 'string', 'max:255'],
                'medium.en' => ['nullable', 'string', 'max:255'],
                'edition_number' => ['nullable', 'string', 'max:20'],
                'edition_size' => ['nullable', 'integer', 'min:0'],
                'weight_kg' => ['nullable', 'numeric', 'min:0'],
                'signed' => ['nullable', new Enum(SignedStatus::class)],
                'holder_id' => ['nullable', 'integer', 'exists:holders,id'],
                'holder_inventory_no' => ['nullable', 'string', 'max:60'],
                'notes' => ['nullable', 'array'],
                'notes.ar' => ['nullable', 'string', 'max:20000'],
                'notes.en' => ['nullable', 'string', 'max:20000'],
                'publication_status' => ['nullable', new Enum(PublicationStatus::class)],
                'condition_report_link' => ['nullable', 'string', 'max:500'],
                'condition_report_status' => ['nullable', 'in:not_available,pending,available'],
                'image_quality' => ['nullable', 'in:low_resolution,high_resolution,archive_source'],
                'editing_status' => ['nullable', 'string', 'max:20'],
                'inventory_by_owner' => ['nullable', 'string', 'max:120'],
                'edit_summary' => ['nullable', 'string', 'max:255'],
            ],
        );
    }
}

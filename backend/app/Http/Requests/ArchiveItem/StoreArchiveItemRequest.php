<?php

namespace App\Http\Requests\ArchiveItem;

use App\Enums\AccessLevel;
use App\Enums\ArchiveItemType;
use App\Enums\ConsentStatus;
use App\Enums\OriginalFormat;
use App\Enums\QualityFlag;
use App\Enums\RightsStatus;
use Illuminate\Validation\Rules\Enum;

class StoreArchiveItemRequest extends ArchiveItemPayloadRequest
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
            $this->titleRules(partial: false),
            $this->publicationRules(partial: false),
            $this->rightsHolderRules(partial: false),
            $this->contentRules(partial: false),
            [
                'legacy_ref' => ['nullable', 'string', 'max:80', 'unique:archive_items,legacy_ref'],
                'parent_id' => ['nullable', 'integer', 'exists:archive_items,id'],
                'item_type' => ['required', new Enum(ArchiveItemType::class)],
                'internal_notes' => ['nullable', 'string', 'max:20000'],
                'creator_name' => ['nullable', 'string', 'max:255'],
                'language' => ['nullable', 'string', 'in:ar,en,und'],
                'original_format' => ['nullable', new Enum(OriginalFormat::class)],
                'source_filename' => ['nullable', 'string', 'max:255'],
                'quality_flag' => ['nullable', new Enum(QualityFlag::class)],
                'digitized_at' => ['nullable', 'date'],
                'access_level' => ['nullable', new Enum(AccessLevel::class)],
                'embargo_until' => ['nullable', 'date'],
                'post_embargo_access_level' => ['nullable', new Enum(AccessLevel::class)],
                'rights_status' => ['nullable', new Enum(RightsStatus::class)],
                'license' => ['nullable', 'string', 'max:120'],
                'consent_status' => ['nullable', new Enum(ConsentStatus::class)],
                'publication_status' => ['nullable', 'string', 'in:draft,hidden'],
                'edit_summary' => ['nullable', 'string', 'max:255'],
            ],
        );
    }
}

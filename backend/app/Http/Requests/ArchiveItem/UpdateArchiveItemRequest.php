<?php

namespace App\Http\Requests\ArchiveItem;

use App\Enums\AccessLevel;
use App\Enums\ArchiveItemType;
use App\Enums\ConsentStatus;
use App\Enums\OriginalFormat;
use App\Enums\QualityFlag;
use App\Enums\RightsStatus;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateArchiveItemRequest extends ArchiveItemPayloadRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function isPartialUpdate(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $itemId = $this->route('archive_item');

        return array_merge(
            $this->titleRules(partial: true),
            $this->publicationRules(partial: true),
            $this->rightsHolderRules(partial: true),
            $this->contentRules(partial: true),
            $this->profileRules(partial: true),
            [
                'legacy_ref' => [
                    'sometimes', 'nullable', 'string', 'max:80',
                    Rule::unique('archive_items', 'legacy_ref')->ignore($itemId),
                ],
                'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:archive_items,id'],
                'item_type' => ['sometimes', new Enum(ArchiveItemType::class)],
                'internal_notes' => ['sometimes', 'nullable', 'string', 'max:20000'],
                'creator_name' => ['sometimes', 'nullable', 'string', 'max:255'],
                'language' => ['sometimes', 'nullable', 'string', 'in:ar,en,und'],
                'original_format' => ['sometimes', 'nullable', new Enum(OriginalFormat::class)],
                'source_filename' => ['sometimes', 'nullable', 'string', 'max:255'],
                'quality_flag' => ['sometimes', new Enum(QualityFlag::class)],
                'digitized_at' => ['sometimes', 'nullable', 'date'],
                'access_level' => ['sometimes', new Enum(AccessLevel::class)],
                'embargo_until' => ['sometimes', 'nullable', 'date'],
                'post_embargo_access_level' => ['sometimes', new Enum(AccessLevel::class)],
                'rights_status' => ['sometimes', new Enum(RightsStatus::class)],
                'license' => ['sometimes', 'nullable', 'string', 'max:120'],
                'consent_status' => ['sometimes', new Enum(ConsentStatus::class)],
                'publication_status' => ['sometimes', 'string', 'in:draft,hidden'],
                'edit_summary' => ['nullable', 'string', 'max:255'],
            ],
        );
    }
}

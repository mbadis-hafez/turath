<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AccessLevel;
use App\Enums\PublicationStatus;
use App\Enums\RightsStatus;
use App\Http\Requests\ArchiveItem\PublishArchiveItemRequest;
use App\Http\Resources\ArchiveItemResource;
use App\Models\ArchiveItem;
use App\Support\Completeness\CompletenessCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ArchiveItemPublishController
{
    public function __invoke(PublishArchiveItemRequest $request, ArchiveItem $archiveItem): JsonResponse
    {
        $this->assertPublishable($archiveItem);

        $archiveItem->publication_status = PublicationStatus::Published->value;
        $archiveItem->save();
        $archiveItem->load(['links.linkable', 'files']);

        return response()->json(['data' => new ArchiveItemResource($archiveItem)]);
    }

    /**
     * Concrete enforcement of "no auto-publish without review": legally
     * unclear rights can never go public, and an embargo needs a lift date.
     */
    private function assertPublishable(ArchiveItem $archiveItem): void
    {
        $errors = [];

        if (blank($archiveItem->item_type)) {
            $errors['item_type'] = ['An item type is required before publishing.'];
        }

        $hasTitle = $archiveItem->title_ar !== null || $archiveItem->title_en !== null;
        $hasDescription = $archiveItem->description_ar !== null || $archiveItem->description_en !== null;

        if (! $hasTitle && ! $hasDescription) {
            $errors['title'] = ['At least a title or a description is required before publishing.'];
        }

        if ($archiveItem->rights_status === RightsStatus::Unknown->value && $archiveItem->access_level === AccessLevel::Public->value) {
            $errors['rights_status'] = ['Material with unknown rights cannot be published at public access level.'];
        }

        if ($archiveItem->access_level === AccessLevel::Embargoed->value && $archiveItem->embargo_until === null) {
            $errors['embargo_until'] = ['An embargo_until date is required when access_level is embargoed.'];
        }

        // F10 D48: extends this gate with the shared completeness engine
        // rather than duplicating its own separate check.
        $calculator = new CompletenessCalculator;
        $result = $calculator->evaluate($archiveItem);
        $rules = $calculator->rulesFor(ArchiveItem::class);

        foreach ($result['blocking'] as $fieldKey) {
            $label = $rules->fieldLabel($fieldKey);
            $errors["completeness.{$fieldKey}"] = ["Missing required field: {$label['en']}."];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}

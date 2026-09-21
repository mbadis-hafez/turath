<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Event;
use App\Models\ReviewQueueItem;
use App\Support\Curation\ArchiveItemChecklist;
use App\ValueObjects\PartialDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ArchiveItemEditController
{
    public function show(ArchiveItem $archiveItem): JsonResponse
    {
        return response()->json(['data' => self::bundle($archiveItem)]);
    }

    public function submitReview(Request $request, ArchiveItem $archiveItem): JsonResponse
    {
        $missing = collect(ArchiveItemChecklist::evaluate($archiveItem))->where('met', false)->pluck('key')->all();

        if ($missing !== []) {
            throw ValidationException::withMessages(collect($missing)->mapWithKeys(fn ($k) => ["checklist.{$k}" => ['This publishing requirement is not met yet.']])->all());
        }

        $pending = ReviewQueueItem::where('citable_type', ArchiveItem::class)->where('citable_id', $archiveItem->id)->where('status', 'pending')->exists();
        if (! $pending) {
            ReviewQueueItem::create([
                'citable_type' => ArchiveItem::class,
                'citable_id' => $archiveItem->id,
                'review_type' => 'archivist_review',
                'submitted_by_user_id' => $request->user()?->id,
            ]);
        }

        return response()->json(['data' => self::bundle($archiveItem->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function bundle(ArchiveItem $item): array
    {
        $item->load('links.linkable');
        $date = $item->getAttribute('content');

        return [
            'id' => $item->id,
            'legacy_ref' => $item->legacy_ref,
            'item_type' => $item->item_type,
            'title' => ['ar' => $item->title_ar, 'en' => $item->title_en],
            'description' => ['ar' => $item->description_ar, 'en' => $item->description_en],
            'place' => ['ar' => $item->getAttribute('place_ar'), 'en' => $item->getAttribute('place_en')],
            'content' => $date instanceof PartialDate ? $date->toArray() : null,
            'theme_ids' => $item->themes()->pluck('themes.id')->all(),
            'date_note' => $item->getAttribute('content_date_note'),
            'people_names' => $item->people_names ?? [],
            'keywords' => $item->keywords ?? [],
            'source_name' => $item->getAttribute('source_name'),
            'rights_holder' => ['ar' => $item->rights_holder_ar, 'en' => $item->rights_holder_en],
            'rights_status' => $item->rights_status,
            'license' => $item->license,
            'verification_reference' => $item->getAttribute('verification_reference'),
            'access_level' => $item->access_level,
            'publication_status' => $item->publication_status,
            'under_review' => ReviewQueueItem::where('citable_type', ArchiveItem::class)->where('citable_id', $item->id)->where('status', 'pending')->exists(),
            'updated_at' => $item->updated_at?->toIso8601String(),
            'file' => ArchiveItemFileController::present($item),
            'checklist' => ArchiveItemChecklist::evaluate($item),
            'completeness_pct' => ArchiveItemChecklist::percent($item),
            'links' => $item->links->map(function ($l) {
                $e = $l->linkable;

                return match (true) {
                    $e instanceof Artist => ['id' => $l->id, 'role' => $l->role, 'kind' => 'artist', 'entity_id' => $e->id, 'label' => ['ar' => $e->name_ar, 'en' => $e->name_en]],
                    $e instanceof Event => ['id' => $l->id, 'role' => $l->role, 'kind' => 'event', 'entity_id' => $e->id, 'label' => ['ar' => $e->title_ar, 'en' => $e->title_en]],
                    $e instanceof Artwork => ['id' => $l->id, 'role' => $l->role, 'kind' => 'artwork', 'entity_id' => $e->id, 'label' => ['ar' => $e->title_ar, 'en' => $e->title_en]],
                    default => null,
                };
            })->filter()->values()->all(),
        ];
    }
}

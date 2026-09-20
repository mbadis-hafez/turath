<?php

namespace App\Http\Resources;

use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\ReviewQueueItem;
use App\Support\Completeness\CitableTypeResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewQueueItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ReviewQueueItem $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'citable_type' => CitableTypeResolver::segmentFor($item->citable_type) ?? $item->citable_type,
            'citable_id' => $item->citable_id,
            'review_type' => $item->review_type,
            'title' => $this->title($item),
            'note' => $item->note,
            'status' => $item->status,
            'submitted_by' => $item->relationLoaded('submittedBy') && $item->submittedBy
                ? ['id' => $item->submittedBy->id, 'name' => $item->submittedBy->name]
                : null,
            'submitted_at' => $item->submitted_at->toIso8601String(),
        ];
    }

    /**
     * @return array{ar: string|null, en: string|null}|null
     */
    private function title(ReviewQueueItem $item): ?array
    {
        $record = $item->citable;

        return match (true) {
            $record instanceof Artist => ['ar' => $record->name_ar, 'en' => $record->name_en],
            $record instanceof Artwork, $record instanceof ArchiveItem => ['ar' => $record->title_ar, 'en' => $record->title_en],
            default => null,
        };
    }
}

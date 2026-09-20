<?php

namespace App\Http\Resources;

use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\Artwork;
use App\Support\ArchiveAccessResolver;
use App\ValueObjects\PartialDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArchiveItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ArchiveItem $item */
        $item = $this->resource;

        $user = $request->user();

        if (! ArchiveAccessResolver::canViewFull($user, $item)) {
            return $this->restrictedShape($item);
        }

        return $this->fullShape($item);
    }

    /**
     * @return array<string, mixed>
     */
    private function fullShape(ArchiveItem $item): array
    {
        return [
            'id' => $item->id,
            'legacy_ref' => $item->legacy_ref,
            'item_type' => $item->item_type,
            'title' => [
                'ar' => $item->title_ar,
                'en' => $item->title_en,
            ],
            'description' => [
                'ar' => $item->description_ar,
                'en' => $item->description_en,
            ],
            'creator_name' => $item->creator_name,
            'publication' => [
                'name' => [
                    'ar' => $item->publication_name_ar,
                    'en' => $item->publication_name_en,
                ],
                'issue_no' => $item->issue_no,
                'page' => $item->page,
            ],
            'content' => self::date($item->content),
            'digitized_at' => $item->digitized_at?->toDateString(),
            'original_format' => $item->original_format,
            'access_level' => $item->access_level,
            'rights_status' => $item->rights_status,
            'rights_holder' => [
                'ar' => $item->rights_holder_ar,
                'en' => $item->rights_holder_en,
            ],
            'license' => $item->license,
            'publication_status' => $item->publication_status,
            'restricted' => false,
            'files' => $item->relationLoaded('files')
                ? $item->files->map(fn ($file) => [
                    'id' => $file->id,
                    'role' => $file->role,
                    'url' => "/api/v1/archive-items/{$item->id}/files/{$file->id}/download",
                    'mime_type' => $file->mime_type,
                ])->values()->all()
                : [],
            'links' => $item->relationLoaded('links')
                ? $item->links->map(fn ($link) => self::linkShape($link))->filter()->values()->all()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function restrictedShape(ArchiveItem $item): array
    {
        return [
            'id' => $item->id,
            'legacy_ref' => $item->legacy_ref,
            'item_type' => $item->item_type,
            'title' => [
                'ar' => $item->title_ar,
                'en' => $item->title_en,
            ],
            'content' => self::date($item->content),
            'access_level' => $item->access_level,
            'publication_status' => $item->publication_status,
            'restricted' => true,
            'restricted_reason' => 'access_level',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function linkShape(mixed $link): ?array
    {
        $linkable = $link->linkable;

        if ($linkable instanceof Artist) {
            return [
                'role' => $link->role,
                'artist' => [
                    'id' => $linkable->id,
                    'slug' => $linkable->slug,
                    'name' => ['ar' => $linkable->name_ar, 'en' => $linkable->name_en],
                ],
            ];
        }

        if ($linkable instanceof Artwork) {
            return [
                'role' => $link->role,
                'artwork' => [
                    'id' => $linkable->id,
                    'title' => ['ar' => $linkable->title_ar, 'en' => $linkable->title_en],
                ],
            ];
        }

        return null;
    }

    /**
     * @return array{display: string|null, year_from: int|null, year_to: int|null, calendar: string|null, certainty: string|null}|null
     */
    private static function date(?PartialDate $date): ?array
    {
        if ($date === null) {
            return null;
        }

        return [
            'display' => $date->display,
            'year_from' => $date->yearFrom,
            'year_to' => $date->yearTo,
            'calendar' => $date->calendar?->value,
            'certainty' => $date->certainty?->value,
        ];
    }
}

<?php

namespace App\Support\Completeness;

use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\RecordCompleteness;
use Illuminate\Support\Collection;

/**
 * Shared "my records" query logic behind both the dashboard record list and
 * the gaps-report export (D54) — the export must be scoped to exactly the
 * same filters/access as what's on screen.
 */
class DashboardRecordsCollector
{
    /**
     * @var array<string, class-string>
     */
    public const ENTITY_TYPES = [
        'artist' => Artist::class,
        'artwork' => Artwork::class,
        'archive_item' => ArchiveItem::class,
    ];

    /**
     * @param  array<int, string>  $entityTypes
     * @param  array<int, string>  $severities
     * @return Collection<int, array<string, mixed>>
     */
    public function collect(int $userId, array $entityTypes, array $severities): Collection
    {
        $rows = collect();

        foreach (self::ENTITY_TYPES as $key => $modelClass) {
            if (! in_array($key, $entityTypes, true)) {
                continue;
            }

            $records = $modelClass::query()->where('created_by_user_id', $userId)->get();
            $completenessById = RecordCompleteness::query()
                ->where('citable_type', $modelClass)
                ->whereIn('citable_id', $records->pluck('id'))
                ->get()
                ->keyBy('citable_id');

            foreach ($records as $record) {
                $completeness = $completenessById->get($record->id);
                $severity = $completeness->severity ?? 'blocking';

                if ($severities !== [] && ! in_array($severity, $severities, true)) {
                    continue;
                }

                $rows->push([
                    'entity_type' => $key,
                    'id' => $record->id,
                    'slug' => $record instanceof Artist ? $record->slug : null,
                    'title' => $this->titleFor($key, $record),
                    'completeness_pct' => $completeness->completeness_pct ?? 0,
                    'severity' => $severity,
                    'blocking_gaps' => $completeness->blocking_gap_field_keys ?? [],
                    'minor_gaps' => $completeness->minor_gap_field_keys ?? [],
                    'open_conflict_count' => $completeness->open_conflict_count ?? 0,
                ]);
            }
        }

        return $rows->sortBy(fn (array $row) => match ($row['severity']) {
            'blocking' => 0,
            'conflict' => 1,
            'minor' => 2,
            'pending_review' => 3,
            default => 4,
        })->values();
    }

    /**
     * @return array{ar: string|null, en: string|null}
     */
    private function titleFor(string $entityType, Artist|Artwork|ArchiveItem $record): array
    {
        return match ($entityType) {
            'artist' => ['ar' => $record->name_ar, 'en' => $record->name_en],
            'artwork' => ['ar' => $record->title_ar, 'en' => $record->title_en],
            default => ['ar' => $record->title_ar, 'en' => $record->title_en],
        };
    }
}

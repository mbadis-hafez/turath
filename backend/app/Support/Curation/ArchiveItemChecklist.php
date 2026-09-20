<?php

namespace App\Support\Curation;

use App\Models\ArchiveItem;
use App\ValueObjects\PartialDate;

/** The publishing checklist shown on the archive edit page. */
class ArchiveItemChecklist
{
    /**
     * @return array<int, array{key: string, met: bool}>
     */
    public static function evaluate(ArchiveItem $item): array
    {
        $date = $item->getAttribute('content');
        // An approximate date (circa/range) is accepted once the reason for the approximation is stated.
        $dated = $date instanceof PartialDate && $date->yearFrom !== null
            && ($date->certainty?->value === 'exact' || filled($item->getAttribute('content_date_note')));
        $peopleNeeded = $item->item_type === 'image';

        $items = [
            ['title_ar', $item->title_ar !== null],
            ['type_and_file', filled($item->item_type) && $item->files()->where('role', 'original')->exists()],
            ['date', $dated],
            ['rights_holder_license', ($item->rights_holder_ar !== null || $item->rights_holder_en !== null) && filled($item->license)],
            ['people_names', ! $peopleNeeded || count($item->people_names ?? []) > 0],
        ];

        return array_map(fn (array $i) => ['key' => $i[0], 'met' => $i[1]], $items);
    }

    public static function percent(ArchiveItem $item): int
    {
        $list = self::evaluate($item);

        return (int) round(count(array_filter($list, fn ($i) => $i['met'])) / count($list) * 100);
    }
}

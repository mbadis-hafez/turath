<?php

namespace App\Support\Curation;

use App\Support\Proposals\FieldValues;

/**
 * Tells whether a submitted child section would actually change the live rows,
 * with ChildSync semantics: items with a known id update in place, items
 * without an id are created, live rows missing from the list are deleted, and
 * a pure reorder is a change too (the sync rewrites `sort`).
 */
class ChildDiffers
{
    /**
     * @param  array<int, array<string, mixed>>  $live  normalized live rows, each carrying `id`
     * @param  array<int, array<string, mixed>>  $incoming  payload items in the same normalized shape
     */
    public static function check(array $live, array $incoming): bool
    {
        $liveById = [];
        foreach (array_values($live) as $position => $row) {
            $row['_position'] = $position;
            $liveById[$row['id']] = $row;
        }

        $seen = [];
        foreach (array_values($incoming) as $position => $item) {
            $id = $item['id'] ?? null;
            if ($id === null || ! isset($liveById[$id])) {
                return true; // a create
            }
            $seen[] = $id;
            $row = $liveById[$id];

            if ($row['_position'] !== $position) {
                return true; // reorder
            }
            unset($item['id']);
            foreach ($item as $column => $value) {
                if (FieldValues::differ($row[$column] ?? null, $value)) {
                    return true;
                }
            }
        }

        return count($seen) !== count($liveById); // a delete
    }
}

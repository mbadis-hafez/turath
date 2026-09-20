<?php

namespace App\Support\Import;

/**
 * Applies an import_mapping_profiles.column_map ({"spreadsheet header":
 * "system_field_key"}) to a raw parsed row. Unmapped spreadsheet columns
 * are dropped from mapped_data but never silently lost — the caller can
 * always recover them from the row's untouched raw_data, and the API
 * surfaces them as "not imported" so nothing disappears without the user
 * seeing it.
 */
class ColumnMapper
{
    /**
     * @param  array<string, string|null>  $rawRow
     * @param  array<string, string|null>  $columnMap
     * @return array<string, string|null>
     */
    public static function map(array $rawRow, array $columnMap): array
    {
        $mapped = [];

        foreach ($columnMap as $sourceColumn => $systemField) {
            if ($systemField === null || $systemField === '') {
                continue;
            }

            $mapped[$systemField] = $rawRow[$sourceColumn] ?? null;
        }

        return $mapped;
    }

    /**
     * Spreadsheet headers present in the raw row but absent from the
     * mapping — surfaced to the reviewer as "not imported".
     *
     * @param  array<string, string|null>  $rawRow
     * @param  array<string, string>  $columnMap
     * @return array<int, string>
     */
    public static function unmappedColumns(array $rawRow, array $columnMap): array
    {
        return array_values(array_diff(array_keys($rawRow), array_keys($columnMap)));
    }
}

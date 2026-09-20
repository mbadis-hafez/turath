<?php

namespace App\Support\Import;

use League\Csv\Reader;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Reads a CSV or XLSX file into rows of raw string values keyed by header.
 * Values are read as displayed strings, never as live-evaluated formulas.
 * Handles a UTF-8 BOM, blank trailing rows, and blank trailing columns.
 */
class SpreadsheetParser
{
    /**
     * @return array<int, array<string, string|null>> rows keyed by 1-based
     *                                                row number (data rows only, header excluded)
     */
    public static function parse(string $path): array
    {
        return str_ends_with(strtolower($path), '.csv')
            ? self::parseCsv($path)
            : self::parseSpreadsheet($path);
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private static function parseCsv(string $path): array
    {
        $csv = Reader::from($path, 'r');
        $csv->setHeaderOffset(0);

        $header = array_map(fn ($h) => trim((string) $h, "\xEF\xBB\xBF "), $csv->getHeader());

        $rows = [];
        $rowNumber = 1;

        foreach ($csv->getRecords($header) as $record) {
            $rowNumber++;

            $row = self::normalizeRow($record);

            if ($row !== null) {
                $rows[$rowNumber] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private static function parseSpreadsheet(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();

        $data = $sheet->toArray(null, true, true, false);

        if ($data === []) {
            return [];
        }

        $header = array_map(fn ($h) => trim((string) ($h ?? '')), array_shift($data));

        $rows = [];
        $rowNumber = 1;

        foreach ($data as $line) {
            $rowNumber++;

            $record = [];
            foreach ($header as $i => $key) {
                if ($key === '') {
                    continue;
                }
                $record[$key] = isset($line[$i]) ? trim((string) $line[$i]) : null;
            }

            $row = self::normalizeRow($record);

            if ($row !== null) {
                $rows[$rowNumber] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, string|null>|null null when the row is entirely blank
     */
    private static function normalizeRow(array $record): ?array
    {
        $row = [];
        $hasValue = false;

        foreach ($record as $key => $value) {
            if ($key === '') {
                continue;
            }

            $value = $value === null ? null : trim((string) $value);
            $value = $value === '' ? null : $value;

            if ($value !== null) {
                $hasValue = true;
            }

            $row[$key] = $value;
        }

        return $hasValue ? $row : null;
    }
}

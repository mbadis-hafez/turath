<?php

namespace App\Support\Completeness;

use App\Models\Artist;

/**
 * Enriches an artist's live completeness evaluation with counts, a
 * per-section breakdown and the labelled missing-field list. Shared by
 * the records completeness endpoint and the create-page preview
 * endpoint so both speak exactly the same shape.
 */
class ArtistCompletenessPresenter
{
    /**
     * @return array{percentage: int, met_count: int, total_count: int, complete: bool, sections: array<string, array{met: int, total: int, percentage: int}>, missing: array<int, array{key: string, label: array{ar: string, en: string}, section: string}>}
     */
    public function present(Artist $artist): array
    {
        $calculator = new CompletenessCalculator;
        /** @var ArtistCompletenessRules $rules */
        $rules = $calculator->rulesFor(Artist::class);
        $result = $calculator->evaluate($artist);

        $fieldKeys = array_merge(array_keys($rules->coreFields()), $rules->importantFields());
        $missingKeys = [...$result['blocking'], ...$result['minor']];
        $missingLookup = array_fill_keys($missingKeys, true);

        $sections = [];
        foreach (ArtistCompletenessRules::SECTIONS as $section) {
            $keys = array_values(array_filter($fieldKeys, fn (string $key) => $rules->fieldSection($key) === $section));
            $total = count($keys);
            $met = count(array_filter($keys, fn (string $key) => ! isset($missingLookup[$key])));

            $sections[$section] = [
                'met' => $met,
                'total' => $total,
                'percentage' => $total > 0 ? (int) round(($met / $total) * 100) : 100,
            ];
        }

        return [
            'percentage' => $result['completeness_pct'],
            'met_count' => count($fieldKeys) - count($missingKeys),
            'total_count' => count($fieldKeys),
            'complete' => $missingKeys === [],
            'sections' => $sections,
            'missing' => array_map(fn (string $key) => [
                'key' => $key,
                'label' => $rules->fieldLabel($key),
                'section' => $rules->fieldSection($key),
            ], $missingKeys),
        ];
    }
}

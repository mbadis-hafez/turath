<?php

namespace App\Support\Ocr;

use App\Enums\OcrRegionType;

/**
 * Pairs each `form_label` region (e.g. "اسم الفنان/ة:") with the nearest
 * plausible value region on the same page — same row first (values are
 * commonly written to the left of a label in RTL layouts), then directly
 * below within a small distance. A label with no candidate nearby produces a
 * field with no value region at all rather than a guessed one — that's the
 * "no machine extraction, needs manual entry" case surfaced in the review UI.
 */
class FormFieldDetector
{
    /** Multiples of the label's own height, used as the search radius for a value below it. */
    private const VERTICAL_SEARCH_MULTIPLE = 3.5;

    /** Regions of these types are never a form's value, even if geometrically close. */
    private const EXCLUDED_VALUE_TYPES = [OcrRegionType::FormLabel, OcrRegionType::Logo, OcrRegionType::Photograph, OcrRegionType::Noise, OcrRegionType::Footer];

    /**
     * @param  array<int, array{id: int, region_type: OcrRegionType, bbox: array{x: int, y: int, width: int, height: int}, source_text: ?string}>  $regions  all regions detected on one page, already persisted (carrying DB ids)
     * @return array<int, array{field_label: string, label_region_id: int, value_region_id: ?int, value_type: ?string, machine_value: ?string, requires_manual_transcription: bool}>
     */
    public function pair(array $regions): array
    {
        $labels = array_values(array_filter($regions, fn ($r) => $r['region_type'] === OcrRegionType::FormLabel));
        $candidates = array_values(array_filter($regions, fn ($r) => ! in_array($r['region_type'], self::EXCLUDED_VALUE_TYPES, true)));

        // Reading order: top to bottom, then right to left (RTL).
        usort($labels, fn ($a, $b) => $a['bbox']['y'] <=> $b['bbox']['y'] ?: $b['bbox']['x'] <=> $a['bbox']['x']);

        $claimed = [];
        $fields = [];

        foreach ($labels as $label) {
            $value = $this->nearestCandidate($label, $candidates, $claimed);
            $fieldLabel = trim(preg_replace('/[:：]\s*$/u', '', trim((string) $label['source_text'])) ?? '');

            if ($value === null) {
                $fields[] = [
                    'field_label' => $fieldLabel,
                    'label_region_id' => $label['id'],
                    'value_region_id' => null,
                    'value_type' => null,
                    'machine_value' => null,
                    'requires_manual_transcription' => true,
                ];

                continue;
            }

            $claimed[$value['id']] = true;
            $fields[] = [
                'field_label' => $fieldLabel,
                'label_region_id' => $label['id'],
                'value_region_id' => $value['id'],
                'value_type' => $value['region_type']->value,
                'machine_value' => $value['region_type']->ocrAllowed() ? $value['source_text'] : null,
                'requires_manual_transcription' => ! $value['region_type']->ocrAllowed(),
            ];
        }

        return $fields;
    }

    /**
     * @param  array{id: int, region_type: OcrRegionType, bbox: array{x: int, y: int, width: int, height: int}, source_text: ?string}  $label
     * @param  array<int, array{id: int, region_type: OcrRegionType, bbox: array{x: int, y: int, width: int, height: int}, source_text: ?string}>  $candidates
     * @param  array<int, bool>  $claimed
     * @return array{id: int, region_type: OcrRegionType, bbox: array{x: int, y: int, width: int, height: int}, source_text: ?string}|null
     */
    private function nearestCandidate(array $label, array $candidates, array $claimed): ?array
    {
        $labelBbox = $label['bbox'];
        $labelHeight = max(1, $labelBbox['height']);
        $best = null;
        $bestScore = null;

        foreach ($candidates as $candidate) {
            if (isset($claimed[$candidate['id']])) {
                continue;
            }
            $box = $candidate['bbox'];

            $verticalOverlap = min($labelBbox['y'] + $labelBbox['height'], $box['y'] + $box['height']) - max($labelBbox['y'], $box['y']);
            $sameRow = $verticalOverlap > $labelHeight * 0.4;

            if ($sameRow) {
                // Same row: prefer a candidate to the label's left (RTL "value  :label" reading order).
                $horizontalGap = $labelBbox['x'] - ($box['x'] + $box['width']);
                if ($horizontalGap < -$labelBbox['width']) {
                    continue; // candidate is far to the right of the label, not its value
                }
                $score = abs($horizontalGap);
            } else {
                $verticalGap = $box['y'] - ($labelBbox['y'] + $labelBbox['height']);
                if ($verticalGap < 0 || $verticalGap > $labelHeight * self::VERTICAL_SEARCH_MULTIPLE) {
                    continue;
                }
                $score = $verticalGap + 10000; // same-row candidates always win over below-row ones
            }

            if ($bestScore === null || $score < $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        return $best;
    }
}

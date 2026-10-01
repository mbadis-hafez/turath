<?php

namespace App\Support\Ocr\Correction;

use App\Models\OcrCorrection;

/**
 * A region's AI correction narrowed to one value read from that region: only
 * the changes that fall inside the value are applied to it. A change touching
 * a masked span (an email, a number) was never the model's to make and is
 * left out. Used wherever a value's correction is shown, recorded or measured,
 * so all three agree.
 */
final class ValueCorrection
{
    /**
     * @return array{suggested_value: ?string, changes: list<array{original: string, corrected: string, type: mixed}>, name_candidates: list<array<mixed>>}
     */
    public static function narrow(OcrCorrection $correction, ?string $value): array
    {
        $suggested = $value;
        $applied = [];
        foreach ($correction->changes ?? [] as $change) {
            $original = is_string($change['original'] ?? null) ? $change['original'] : '';
            $replacement = is_string($change['corrected'] ?? null) ? $change['corrected'] : '';
            if ($suggested === null || $original === '' || str_contains($original.$replacement, '⟦') || ! str_contains($suggested, $original)) {
                continue;
            }
            $suggested = str_replace($original, $replacement, $suggested);
            $applied[] = ['original' => $original, 'corrected' => $replacement, 'type' => $change['type'] ?? null];
        }

        $names = array_values(array_filter(
            $correction->name_candidates ?? [],
            fn (array $n) => $value !== null && is_string($n['ocr_text'] ?? null) && $n['ocr_text'] !== '' && str_contains($value, $n['ocr_text']),
        ));

        return [
            'suggested_value' => $applied === [] || $suggested === $value ? null : $suggested,
            'changes' => $applied,
            'name_candidates' => $names,
        ];
    }
}

<?php

namespace App\Support\Ocr\Benchmark;

use App\Support\Ocr\Evaluation\TextMetrics;

/**
 * The suite's totals: every rate micro-averaged over all documents, then the
 * same per category, and which categories the suite doesn't cover yet. Built
 * from scores only, so it carries no document text either.
 */
final class BenchmarkReport
{
    /**
     * @param  list<array<string, mixed>>  $scores  BenchmarkScorer::score() results
     * @return array<string, mixed>
     */
    public static function summarize(array $scores, BenchmarkManifest $manifest): array
    {
        $byCategory = [];
        foreach ($scores as $score) {
            $byCategory[(string) $score['category']][] = $score;
        }

        return [
            'documents' => count($scores),
            'coverage' => [
                'categories' => count(BenchmarkManifest::CATEGORIES),
                'covered' => count(BenchmarkManifest::CATEGORIES) - count($manifest->missingCategories()),
                'missing' => $manifest->missingCategories(),
            ],
            'overall' => self::totals($scores),
            'by_category' => array_map(fn (array $group) => self::totals($group), $byCategory),
            'documents_detail' => $scores,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $scores
     * @return array<string, mixed>
     */
    private static function totals(array $scores): array
    {
        $sum = fn (string $part, string $key) => array_sum(array_map(fn (array $s) => is_array($s[$part] ?? null) ? (int) ($s[$part][$key] ?? 0) : 0, $scores));
        $text = fn (string $kind) => array_reduce($scores, function (array $carry, array $s) use ($kind) {
            $t = $s['pages']['totals'][$kind] ?? [0, 0];

            return [$carry[0] + $t[0], $carry[1] + $t[1]];
        }, [0, 0]);
        $typed = array_values(array_filter($scores, fn (array $s) => is_array($s['document_type'] ?? null)));
        $ratio = fn (int $a, int $b) => $b === 0 ? null : round($a / $b, 4);

        [$tp, $fp, $fn] = [$sum('fields', 'tp'), $sum('fields', 'fp'), $sum('fields', 'fn')];
        [$dtp, $dfp, $dfn] = [$sum('dates', 'tp'), $sum('dates', 'fp'), $sum('dates', 'fn')];

        return [
            'document_type_accuracy' => $ratio(count(array_filter($typed, fn (array $s) => $s['document_type']['correct'] === true)), count($typed)),
            'cer' => TextMetrics::rate(...$text('char')),
            'wer' => TextMetrics::rate(...$text('word')),
            'cer_normalized' => TextMetrics::rate(...$text('char_normalized')),
            'wer_normalized' => TextMetrics::rate(...$text('word_normalized')),
            'field_precision' => $ratio($tp, $tp + $fp),
            'field_recall' => $ratio($tp, $tp + $fn),
            'date_precision' => $ratio($dtp, $dtp + $dfp),
            'date_recall' => $ratio($dtp, $dtp + $dfn),
            'correct_match_rate' => $ratio($sum('matches', 'correct'), $sum('matches', 'checked')),
            'false_match_rate' => $ratio($sum('matches', 'strong_wrong'), $sum('matches', 'strong')),
            'correction_accuracy' => $ratio($sum('correction', 'correct'), $sum('correction', 'proposed')),
            'false_correction_rate' => $ratio($sum('correction', 'false'), $sum('correction', 'proposed')),
            'harmful_correction_rate' => $ratio($sum('correction', 'harmful'), $sum('correction', 'values')),
            'abstention_rate' => $ratio($sum('correction', 'abstained'), $sum('correction', 'values')),
        ];
    }
}

<?php

namespace App\Support\Ocr\Evaluation;

use App\Models\OcrReviewExample;
use Illuminate\Support\Collection;

/**
 * Per-stage metrics from reviewer decisions (the latest decision per thing).
 * A reviewer's final value is the reference; where there is none (a rejected
 * value, an uncertain one) the example only counts where a reference isn't
 * needed.
 *
 * Correction is measured on what the AI did to each value:
 * - proposed: the AI changed the value;
 * - correction accuracy: of those, how many became exactly the reviewer's value;
 * - false correction rate: of those, how many didn't — the safety metric: a
 *   fluent wrong value is worse than an untouched OCR error a person still sees;
 * - harmful correction rate: values OCR had right that the AI changed;
 * - abstention rate: values whose line the AI flagged for a person instead.
 *
 * Extraction recall from production data is only "observed": it counts the
 * values reviewers had to add by hand, not ones nobody noticed were missing.
 * The benchmark suite measures true recall against known answers.
 */
class ExampleEvaluator
{
    /**
     * @param  Collection<int, OcrReviewExample>  $examples
     * @return array<string, mixed>
     */
    public function evaluate(Collection $examples): array
    {
        return [
            'examples' => $examples->countBy('kind')->all(),
            'ocr' => $this->ocr($examples),
            'correction' => $this->correction($examples),
            'extraction' => $this->extraction($examples),
            'matching' => $this->matching($examples),
            'workflow' => $this->workflow($examples),
        ];
    }

    /**
     * Machine value against the reviewer's value, for values a reviewer
     * accepted (so the reference is theirs) and the machine actually read.
     *
     * @param  Collection<int, OcrReviewExample>  $examples
     * @return array<string, mixed>
     */
    private function ocr(Collection $examples): array
    {
        $values = 0;
        $totals = ['c' => [0, 0], 'w' => [0, 0], 'cn' => [0, 0], 'wn' => [0, 0], 'exact' => 0];
        foreach ($examples as $e) {
            $read = $e->ocr_text;
            $truth = $e->human_correction;
            if ($read === null || $truth === null || ! in_array($e->kind, [OcrReviewExample::KIND_FIELD, OcrReviewExample::KIND_DATE, OcrReviewExample::KIND_FORM_FIELD], true)) {
                continue;
            }
            $values++;
            foreach (['c' => [false, 'char'], 'w' => [false, 'word'], 'cn' => [true, 'char'], 'wn' => [true, 'word']] as $k => [$normalized, $unit]) {
                $r = $unit === 'char' ? TextMetrics::charErrors($read, $truth, $normalized) : TextMetrics::wordErrors($read, $truth, $normalized);
                $totals[$k][0] += $r['errors'];
                $totals[$k][1] += $r['length'];
            }
            $totals['exact'] += TextMetrics::same($read, $truth) ? 1 : 0;
        }

        return [
            'values' => $values,
            'cer' => TextMetrics::rate(...$totals['c']),
            'wer' => TextMetrics::rate(...$totals['w']),
            'cer_normalized' => TextMetrics::rate(...$totals['cn']),
            'wer_normalized' => TextMetrics::rate(...$totals['wn']),
            'exact_rate' => $this->ratio($totals['exact'], $values),
        ];
    }

    /**
     * @param  Collection<int, OcrReviewExample>  $examples
     * @return array<string, mixed>
     */
    private function correction(Collection $examples): array
    {
        $evaluated = $examples->filter(fn (OcrReviewExample $e) => $e->kind === OcrReviewExample::KIND_FIELD
            && ($e->ai_meta['kind'] ?? null) === 'correction' && $e->ocr_text !== null && $e->human_correction !== null);

        $overall = $this->correctionCounts($evaluated);
        $byModel = $evaluated
            ->groupBy(fn (OcrReviewExample $e) => implode(' · ', array_filter([$e->ai_meta['provider'] ?? null, $e->ai_meta['model'] ?? null, $e->ai_meta['prompt_version'] ?? null])))
            ->map(fn (Collection $group) => $this->correctionCounts($group))
            ->all();

        return [...$overall, 'by_model' => $byModel];
    }

    /**
     * @param  Collection<int, OcrReviewExample>  $evaluated
     * @return array<string, mixed>
     */
    private function correctionCounts(Collection $evaluated): array
    {
        $proposed = $correct = $false = $harmful = $missed = $abstained = 0;
        foreach ($evaluated as $e) {
            $meta = $e->ai_meta ?? [];
            $changed = ($meta['changed'] ?? false) === true;
            $aiRight = TextMetrics::same($e->ai_correction, $e->human_correction);
            $ocrRight = TextMetrics::same($e->ocr_text, $e->human_correction);

            $proposed += $changed ? 1 : 0;
            $correct += $changed && $aiRight ? 1 : 0;
            $false += $changed && ! $aiRight ? 1 : 0;
            $harmful += $changed && ! $aiRight && $ocrRight ? 1 : 0;
            $missed += ! $changed && ! $ocrRight ? 1 : 0;
            $abstained += ($meta['needs_review'] ?? false) === true || in_array($meta['status'] ?? null, ['needs_review', 'rejected'], true) ? 1 : 0;
        }
        $n = $evaluated->count();

        return [
            'values' => $n,
            'proposed' => $proposed,
            'correction_accuracy' => $this->ratio($correct, $proposed),
            'false_correction_rate' => $this->ratio($false, $proposed),
            'harmful_correction_rate' => $this->ratio($harmful, $n),
            'abstention_rate' => $this->ratio($abstained, $n),
            'missed_rate' => $this->ratio($missed, $n),
        ];
    }

    /**
     * @param  Collection<int, OcrReviewExample>  $examples
     * @return array<string, mixed>
     */
    private function extraction(Collection $examples): array
    {
        $fields = $examples->where('kind', OcrReviewExample::KIND_FIELD);
        $machine = $fields->filter(fn (OcrReviewExample $e) => $e->ocr_text !== null && $e->accepted !== null);
        $found = $machine->where('accepted', true)->count();
        $unchanged = $machine->where('reviewer_action', 'accepted')->count();
        $addedByHand = $fields->where('reviewer_action', 'transcribed')->count();

        return [
            'decided' => $machine->count(),
            'field_precision' => $this->ratio($found, $machine->count()),
            'exact_value_rate' => $this->ratio($unchanged, $found),
            'observed_recall' => $this->ratio($found, $found + $addedByHand),
            'added_by_hand' => $addedByHand,
            'by_document_type' => $machine->groupBy(fn (OcrReviewExample $e) => $e->document_type ?? 'item')
                ->map(fn (Collection $g) => ['decided' => $g->count(), 'field_precision' => $this->ratio($g->where('accepted', true)->count(), $g->count())])
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, OcrReviewExample>  $examples
     * @return array<string, mixed>
     */
    private function matching(Collection $examples): array
    {
        $decided = $examples->where('kind', OcrReviewExample::KIND_MATCH);
        $withCandidates = $decided->filter(fn (OcrReviewExample $e) => ($e->match['top_id'] ?? null) !== null);
        $strong = $decided->filter(fn (OcrReviewExample $e) => ($e->match['top_strength'] ?? null) === 'high');

        return [
            'decided' => $decided->count(),
            'correct_match_rate' => $this->ratio($withCandidates->where('reviewer_action', 'confirmed_top')->count(), $withCandidates->count()),
            // A strong top candidate the reviewer didn't take: the matcher was confidently wrong.
            'false_match_rate' => $this->ratio($strong->where('reviewer_action', '!=', 'confirmed_top')->count(), $strong->count()),
            'linked_outside_candidates' => $decided->where('reviewer_action', 'linked_other')->count(),
            'no_match' => $decided->where('reviewer_action', 'no_match')->count(),
        ];
    }

    /**
     * @param  Collection<int, OcrReviewExample>  $examples
     * @return array<string, mixed>
     */
    private function workflow(Collection $examples): array
    {
        $decisions = $examples->where('kind', '!=', OcrReviewExample::KIND_MATCH);
        $n = $decisions->count();
        $count = fn (array $actions) => $decisions->whereIn('reviewer_action', $actions)->count();
        $typed = $decisions->filter(fn (OcrReviewExample $e) => $e->reviewer_action === 'transcribed'
            || ($e->kind === OcrReviewExample::KIND_HANDWRITING && $e->reviewer_action === 'edited'))->count();

        return [
            'decisions' => $n,
            'acceptance_rate' => $this->ratio($count(['accepted']), $n),
            'correction_rate' => $this->ratio($count(['corrected', 'edited']), $n),
            'rejection_rate' => $this->ratio($count(['rejected']), $n),
            'uncertain_rate' => $this->ratio($count(['uncertain']), $n),
            'manual_transcription_rate' => $this->ratio($typed, $n),
        ];
    }

    private function ratio(int $part, int $whole): ?float
    {
        return $whole === 0 ? null : round($part / $whole, 4);
    }
}

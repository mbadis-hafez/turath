<?php

namespace App\Support\Ocr\Benchmark;

use App\Support\ArabicNormalizer;
use App\Support\Ocr\Evaluation\TextMetrics;

/**
 * Scores what the pipeline produced for a benchmark document against its
 * known answers. The score holds counts, rates and field keys — never a value
 * from the document, so a report is safe to print and keep even when the
 * document carries personal data.
 *
 * Values are compared after Arabic normalization and whitespace folding:
 * "found" means the right text for the right field, spelling variants aside.
 */
final class BenchmarkScorer
{
    /**
     * @param  array{document_type: ?string, fields: array<string, list<string>>, dates: list<array{value: string, calendar: string, date_type: string}>, pages: array<string, array<int, string>>, matches: list<array{entity_type: string, source_text: string, top_id: mixed, top_strength: mixed}>, corrections: list<array{field_key: string, ocr: string, ai: ?string, changed: bool, needs_review: bool}>, stages: array<string, ?string>}  $observed
     * @return array<string, mixed>
     */
    public function score(BenchmarkDocument $document, array $observed): array
    {
        $expected = $document->expected;

        return [
            'id' => $document->id,
            'category' => $document->category,
            'document_type' => is_string($expected['document_type'] ?? null)
                ? ['expected' => $expected['document_type'], 'observed' => $observed['document_type'], 'correct' => $expected['document_type'] === $observed['document_type']]
                : null,
            'fields' => is_array($expected['fields'] ?? null) ? $this->fields($expected['fields'], $observed['fields']) : null,
            'dates' => is_array($expected['dates'] ?? null) ? $this->dates($expected['dates'], $observed['dates']) : null,
            'pages' => is_array($expected['pages'] ?? null) ? $this->pages($expected['pages'], $observed['pages']) : null,
            'matches' => is_array($expected['matches'] ?? null) ? $this->matches($expected['matches'], $observed['matches']) : null,
            'correction' => is_array($expected['fields'] ?? null) && $observed['corrections'] !== [] ? $this->correction($expected['fields'], $observed['corrections']) : null,
            'stages' => $observed['stages'],
        ];
    }

    /**
     * @param  array<mixed>  $expected  key => value or list of values
     * @param  array<string, list<string>>  $observed
     * @return array<string, mixed>
     */
    private function fields(array $expected, array $observed): array
    {
        $tp = $fp = $fn = 0;
        $byKey = [];
        foreach ($expected as $key => $values) {
            $want = array_map(fn ($v) => $this->norm($v), array_values(array_filter(is_array($values) ? $values : [$values], 'is_string')));
            $got = array_map(fn (string $v) => $this->norm($v), $observed[(string) $key] ?? []);
            [$hit, $missed, $extra] = $this->multiset($want, $got);
            $tp += $hit;
            $fn += $missed;
            $fp += $extra;
            $byKey[(string) $key] = match (true) {
                $missed === 0 && $extra === 0 => 'found',
                $got === [] => 'missing',
                $hit === 0 => 'wrong',
                default => 'partial',
            };
        }
        // A value read for a field the answer doesn't list is a false positive.
        foreach ($observed as $key => $values) {
            if (! array_key_exists($key, $expected)) {
                $fp += count($values);
                $byKey[$key] = 'unexpected';
            }
        }

        return ['tp' => $tp, 'fp' => $fp, 'fn' => $fn, 'precision' => $this->ratio($tp, $tp + $fp), 'recall' => $this->ratio($tp, $tp + $fn), 'by_key' => $byKey];
    }

    /**
     * @param  array<mixed>  $expected
     * @param  list<array{value: string, calendar: string, date_type: string}>  $observed
     * @return array<string, mixed>
     */
    private function dates(array $expected, array $observed): array
    {
        $want = array_values(array_filter($expected, fn ($d) => is_array($d) && is_string($d['value'] ?? null) && is_string($d['calendar'] ?? null)));
        $unused = $observed;
        $hit = 0;
        foreach ($want as $d) {
            foreach ($unused as $i => $o) {
                // Same text in the same calendar; the role too, when the answer names one.
                if ($this->norm($o['value']) === $this->norm($d['value']) && $o['calendar'] === $d['calendar']
                    && (! is_string($d['date_type'] ?? null) || $o['date_type'] === $d['date_type'])) {
                    $hit++;
                    unset($unused[$i]);

                    break;
                }
            }
        }

        return ['tp' => $hit, 'fp' => count($observed) - $hit, 'fn' => count($want) - $hit, 'precision' => $this->ratio($hit, count($observed)), 'recall' => $this->ratio($hit, count($want))];
    }

    /**
     * @param  array<mixed>  $expected
     * @param  array<string, array<int, string>>  $observed  language => page => text
     * @return array<string, mixed>
     */
    private function pages(array $expected, array $observed): array
    {
        $sum = ['c' => [0, 0], 'w' => [0, 0], 'cn' => [0, 0], 'wn' => [0, 0]];
        $perPage = [];
        foreach ($expected as $p) {
            if (! is_array($p) || ! is_int($p['page'] ?? null) || ! is_string($p['text'] ?? null)) {
                continue;
            }
            $language = is_string($p['language'] ?? null) ? $p['language'] : 'ar';
            $read = $observed[$language][$p['page']] ?? '';
            $c = TextMetrics::charErrors($read, $p['text']);
            $w = TextMetrics::wordErrors($read, $p['text']);
            $cn = TextMetrics::charErrors($read, $p['text'], true);
            $wn = TextMetrics::wordErrors($read, $p['text'], true);
            foreach (['c' => $c, 'w' => $w, 'cn' => $cn, 'wn' => $wn] as $k => $r) {
                $sum[$k][0] += $r['errors'];
                $sum[$k][1] += $r['length'];
            }
            $perPage[] = ['page' => $p['page'], 'language' => $language, 'cer' => TextMetrics::rate(...array_values($c)), 'wer' => TextMetrics::rate(...array_values($w))];
        }

        return [
            'cer' => TextMetrics::rate(...$sum['c']), 'wer' => TextMetrics::rate(...$sum['w']),
            'cer_normalized' => TextMetrics::rate(...$sum['cn']), 'wer_normalized' => TextMetrics::rate(...$sum['wn']),
            // Raw totals, so a report can micro-average across documents.
            'totals' => ['char' => $sum['c'], 'word' => $sum['w'], 'char_normalized' => $sum['cn'], 'word_normalized' => $sum['wn']],
            'pages' => $perPage,
        ];
    }

    /**
     * @param  array<mixed>  $expected
     * @param  list<array{entity_type: string, source_text: string, top_id: mixed, top_strength: mixed}>  $observed
     * @return array<string, mixed>
     */
    private function matches(array $expected, array $observed): array
    {
        $correct = $strongWrong = $strong = $notMatched = 0;
        foreach ($expected as $m) {
            if (! is_array($m) || ! is_string($m['entity_type'] ?? null) || ! is_string($m['source_text'] ?? null)) {
                continue;
            }
            $found = collect($observed)->first(fn (array $o) => $o['entity_type'] === $m['entity_type'] && $this->norm($o['source_text']) === $this->norm($m['source_text']));
            if ($found === null) {
                $notMatched++;

                continue;
            }
            $want = $m['entity_id'] ?? null;
            $right = $want === null ? $found['top_id'] === null : (string) $found['top_id'] === (string) $want;
            $correct += $right ? 1 : 0;
            if ($found['top_strength'] === 'high') {
                $strong++;
                $strongWrong += $right ? 0 : 1;
            }
        }
        $checked = count($expected) - $notMatched;

        return [
            'checked' => $checked, 'not_matched' => $notMatched, 'correct' => $correct, 'strong' => $strong, 'strong_wrong' => $strongWrong,
            'correct_match_rate' => $this->ratio($correct, $checked), 'false_match_rate' => $this->ratio($strongWrong, $strong),
        ];
    }

    /**
     * The AI correction against the known answer, value by value.
     *
     * @param  array<mixed>  $expected
     * @param  list<array{field_key: string, ocr: string, ai: ?string, changed: bool, needs_review: bool}>  $corrections
     * @return array<string, mixed>
     */
    private function correction(array $expected, array $corrections): array
    {
        $n = $proposed = $correct = $false = $harmful = $abstained = 0;
        foreach ($corrections as $c) {
            $answers = $expected[$c['field_key']] ?? null;
            $answers = array_values(array_filter(is_array($answers) ? $answers : [$answers], 'is_string'));
            // The answer this value is meant to be: the one it is closest to.
            $truth = collect($answers)->sortBy(fn (string $a) => TextMetrics::charErrors($c['ocr'], $a)['errors'])->first();
            if ($truth === null) {
                continue;
            }
            $n++;
            $aiRight = TextMetrics::same($c['ai'], $truth);
            $proposed += $c['changed'] ? 1 : 0;
            $correct += $c['changed'] && $aiRight ? 1 : 0;
            $false += $c['changed'] && ! $aiRight ? 1 : 0;
            $harmful += $c['changed'] && ! $aiRight && TextMetrics::same($c['ocr'], $truth) ? 1 : 0;
            $abstained += $c['needs_review'] ? 1 : 0;
        }

        return [
            'values' => $n, 'proposed' => $proposed, 'correct' => $correct, 'false' => $false, 'harmful' => $harmful, 'abstained' => $abstained,
            'correction_accuracy' => $this->ratio($correct, $proposed),
            'false_correction_rate' => $this->ratio($false, $proposed),
            'harmful_correction_rate' => $this->ratio($harmful, $n),
            'abstention_rate' => $this->ratio($abstained, $n),
        ];
    }

    /**
     * Matches two lists of values, each value used once.
     *
     * @param  list<string>  $want
     * @param  list<string>  $got
     * @return array{0: int, 1: int, 2: int} hits, missed, extra
     */
    private function multiset(array $want, array $got): array
    {
        $hit = 0;
        foreach ($want as $w) {
            $i = array_search($w, $got, true);
            if ($i !== false) {
                $hit++;
                unset($got[$i]);
            }
        }

        return [$hit, count($want) - $hit, count($got)];
    }

    private function norm(mixed $value): string
    {
        return is_string($value) ? trim((string) preg_replace('/\s+/u', ' ', ArabicNormalizer::normalize($value))) : '';
    }

    private function ratio(int $part, int $whole): ?float
    {
        return $whole === 0 ? null : round($part / $whole, 4);
    }
}

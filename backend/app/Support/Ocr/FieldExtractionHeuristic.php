<?php

namespace App\Support\Ocr;

/**
 * A deliberately simple, regex-based first pass at turning OCR text into
 * candidate archive-item fields — good enough to seed the review UI and prove
 * the pipeline end to end, not a real NLP/LLM extraction. Replacing this class
 * with a proper model later should not require changing its call site, the
 * `file_extracted_fields` schema, or the review API.
 */
class FieldExtractionHeuristic
{
    /**
     * @param  array<string, array<int, array{text: string, confidence: int}>>  $pagesByLanguage  language => [page_number => ['text' => ..., 'confidence' => ...]]
     * @return array<int, array{field_key: string, extracted_value: string, confidence: int, source_page: int|null}>
     */
    public function extract(array $pagesByLanguage): array
    {
        $candidates = [];

        foreach (['ar', 'en'] as $lang) {
            $pages = $pagesByLanguage[$lang] ?? [];
            if ($pages === []) {
                continue;
            }
            ksort($pages);
            $firstPage = array_key_first($pages);
            $titleLine = $this->firstMeaningfulLine($pages[$firstPage]['text']);
            if ($titleLine !== null) {
                $candidates[] = [
                    'field_key' => "title_{$lang}",
                    'extracted_value' => $titleLine,
                    'confidence' => min(80, $pages[$firstPage]['confidence']),
                    'source_page' => $firstPage,
                ];
            }
        }

        $combined = implode("\n", array_merge(
            array_column($pagesByLanguage['ar'] ?? [], 'text'),
            array_column($pagesByLanguage['en'] ?? [], 'text'),
        ));
        if (preg_match('/\b(1[6-9]\d{2}|20\d{2})\b/', $combined, $m, PREG_OFFSET_CAPTURE)) {
            $page = $this->pageContaining($pagesByLanguage, (int) $m[1][1]);
            $candidates[] = [
                'field_key' => 'date_display',
                'extracted_value' => $m[1][0],
                'confidence' => 65,
                'source_page' => $page,
            ];
        }

        return $candidates;
    }

    /**
     * A single glued-together token with almost no letters is almost always OCR noise from a
     * logo, stamp, or signature mark rather than a real heading — real titles are made of
     * multiple words. This filters that out without pretending to understand the document.
     */
    private function firstMeaningfulLine(string $text): ?string
    {
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim($line);
            $len = mb_strlen($line);
            if ($len < 3 || $len > 150) {
                continue;
            }
            if (preg_match_all('/\S+/u', $line) < 2) {
                continue;
            }
            if (preg_match_all('/\p{L}/u', $line) < 4) {
                continue;
            }

            return $line;
        }

        return null;
    }

    /**
     * @param  array<string, array<int, array{text: string, confidence: int}>>  $pagesByLanguage
     */
    private function pageContaining(array $pagesByLanguage, int $offset): ?int
    {
        foreach ($pagesByLanguage as $pages) {
            $running = 0;
            foreach ($pages as $pageNumber => $page) {
                $len = mb_strlen($page['text']) + 1;
                if ($offset < $running + $len) {
                    return $pageNumber;
                }
                $running += $len;
            }
        }

        return null;
    }
}

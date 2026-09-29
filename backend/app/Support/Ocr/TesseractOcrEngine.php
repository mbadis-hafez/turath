<?php

namespace App\Support\Ocr;

use thiagoalessio\TesseractOCR\TesseractOCR;
use thiagoalessio\TesseractOCR\UnsuccessfulCommandException;

/**
 * Shells out to a self-hosted `tesseract` binary. Requires the binary and its
 * language data (both `eng` and `ara`) on the host running the queue worker —
 * this is a deploy prerequisite, not something this class can install.
 */
class TesseractOcrEngine implements OcrEngine
{
    /** Our two-letter language codes to tesseract's three-letter ones. */
    private const LANG_MAP = ['ar' => 'ara', 'en' => 'eng'];

    /** TSV column indices, per tesseract's --tsv output (word rows are level 5). */
    private const WORD_LEVEL = 5;

    private const COL_LEVEL = 0;

    private const COL_BLOCK = 2;

    private const COL_PAR = 3;

    private const COL_LINE = 4;

    private const COL_CONF = 10;

    private const COL_TEXT = 11;

    public function recognize(string $imagePath, string $language): array
    {
        $tesseractLang = self::LANG_MAP[$language] ?? $language;

        try {
            $tsv = (new TesseractOCR($imagePath))->lang($tesseractLang)->format('tsv')->run();
        } catch (UnsuccessfulCommandException $e) {
            throw new OcrEngineException("Tesseract failed for language [{$language}]: {$e->getMessage()}", previous: $e);
        }

        return $this->parseTsv($tsv);
    }

    /**
     * @return array{text: string, confidence: int, segments: array<int, array{text: string, confidence: int}>}
     */
    private function parseTsv(string $tsv): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($tsv)) ?: [];
        array_shift($lines); // header row

        $segments = [];
        $confidences = [];
        $textParts = [];
        $lastLineKey = null;

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            $cols = explode("\t", $line);
            if ((int) $cols[self::COL_LEVEL] !== self::WORD_LEVEL) {
                continue;
            }
            $text = trim($cols[self::COL_TEXT] ?? '');
            $conf = (float) ($cols[self::COL_CONF] ?? -1);
            if ($text === '' || $conf < 0) {
                continue;
            }
            $confidence = (int) round($conf);
            $segments[] = ['text' => $text, 'confidence' => $confidence];
            $confidences[] = $confidence;

            // Tesseract's TSV has no line breaks of its own — without tracking block/paragraph/line
            // here, every word on the page would get joined into one giant single-line string,
            // which silently broke the field-extraction heuristic's "first line is the title" rule.
            $lineKey = "{$cols[self::COL_BLOCK]}.{$cols[self::COL_PAR]}.{$cols[self::COL_LINE]}";
            if ($lastLineKey !== null) {
                $textParts[] = $lineKey === $lastLineKey ? ' ' : "\n";
            }
            $textParts[] = $text;
            $lastLineKey = $lineKey;
        }

        return [
            'text' => implode('', $textParts),
            'confidence' => $confidences === [] ? 0 : (int) round(array_sum($confidences) / count($confidences)),
            'segments' => $segments,
        ];
    }
}

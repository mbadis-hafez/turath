<?php

namespace App\Support\Ocr;

use thiagoalessio\TesseractOCR\TesseractOCR;
use thiagoalessio\TesseractOCR\UnsuccessfulCommandException;

/**
 * Runs tesseract once per page with a combined Arabic+English language pack to
 * recover word geometry (bounding boxes), grouped into blocks by tesseract's
 * own layout analysis. This is a geometry pass, separate from TesseractOcrEngine's
 * per-language text pass — it answers "where are the text regions and how
 * confident is each one", which RegionClassifier then turns into a decision
 * about whether each region may be OCR'd, corrected, or must go to a human.
 */
class TesseractPageLayoutAnalyzer implements PageLayoutAnalyzer
{
    private const WORD_LEVEL = 5;

    private const COL_LEVEL = 0;

    private const COL_BLOCK = 2;

    private const COL_LEFT = 6;

    private const COL_TOP = 7;

    private const COL_WIDTH = 8;

    private const COL_HEIGHT = 9;

    private const COL_CONF = 10;

    private const COL_TEXT = 11;

    public function analyze(string $imagePath): array
    {
        try {
            $tsv = (new TesseractOCR($imagePath))->lang('ara+eng')->format('tsv')->run();
        } catch (UnsuccessfulCommandException $e) {
            throw new OcrEngineException("Tesseract layout analysis failed: {$e->getMessage()}", previous: $e);
        }

        return $this->groupIntoBlocks($tsv);
    }

    /**
     * @return array<int, array{block_id: string, text: string, confidence: int, bbox: array{x: int, y: int, width: int, height: int}, word_count: int, avg_word_length: float, words: array<int, array{text: string, confidence: int, bbox: array{x: int, y: int, width: int, height: int}}>}>
     */
    private function groupIntoBlocks(string $tsv): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($tsv)) ?: [];
        array_shift($lines); // header row

        /** @var array<string, array{words: array<int, array{text: string, confidence: int, x1: int, y1: int, x2: int, y2: int}>}> $blocks */
        $blocks = [];

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

            $blockId = (string) $cols[self::COL_BLOCK];
            $left = (int) $cols[self::COL_LEFT];
            $top = (int) $cols[self::COL_TOP];
            $width = (int) $cols[self::COL_WIDTH];
            $height = (int) $cols[self::COL_HEIGHT];

            $blocks[$blockId]['words'][] = [
                'text' => $text,
                'confidence' => (int) round($conf),
                'x1' => $left,
                'y1' => $top,
                'x2' => $left + $width,
                'y2' => $top + $height,
            ];
        }

        $result = [];
        foreach ($blocks as $blockId => $block) {
            $words = $block['words'];
            $confidences = array_column($words, 'confidence');
            $x1 = min(array_column($words, 'x1'));
            $y1 = min(array_column($words, 'y1'));
            $x2 = max(array_column($words, 'x2'));
            $y2 = max(array_column($words, 'y2'));
            $texts = array_column($words, 'text');

            $result[] = [
                'block_id' => $blockId,
                'text' => implode(' ', $texts),
                'confidence' => (int) round(array_sum($confidences) / count($confidences)),
                'bbox' => ['x' => $x1, 'y' => $y1, 'width' => $x2 - $x1, 'height' => $y2 - $y1],
                'word_count' => count($words),
                'avg_word_length' => array_sum(array_map('mb_strlen', $texts)) / count($texts),
                // Preserves tesseract's own reading order (rows arrive block/par/line/word-ordered in the TSV).
                'words' => array_map(fn ($w) => [
                    'text' => $w['text'],
                    'confidence' => $w['confidence'],
                    'bbox' => ['x' => $w['x1'], 'y' => $w['y1'], 'width' => $w['x2'] - $w['x1'], 'height' => $w['y2'] - $w['y1']],
                ], $words),
            ];
        }

        return $result;
    }
}

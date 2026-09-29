<?php

namespace App\Support\Ocr;

interface OcrEngine
{
    /**
     * Recognize text in a single image, in a single language.
     *
     * @return array{text: string, confidence: int, segments: array<int, array{text: string, confidence: int}>}
     */
    public function recognize(string $imagePath, string $language): array;
}

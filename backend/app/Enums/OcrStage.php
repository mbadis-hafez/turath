<?php

namespace App\Enums;

/**
 * The OCR pipeline's stages, in the order they run. Each is a separate,
 * separately retryable queued job (see App\Support\Ocr\Pipeline\OcrPipeline).
 *
 * Rendering, OCR and region detection stay one stage: they share the page
 * images rendered to local disk, and splitting them would mean rendering
 * every page again per stage — possibly on another worker's disk.
 */
enum OcrStage: string
{
    /** Render pages, OCR them, classify regions, detect correction marks, crop, pair form fields. */
    case Recognize = 'recognize';

    /** Dates, candidate record fields and the document type, from the stored OCR text. */
    case Extract = 'extract';

    /** Candidate records (artists, artworks, exhibitions, institutions, sources, places) for the names extracted. */
    case MatchEntities = 'match';

    /** Optional AI correction of printed-text regions. */
    case Correct = 'correct';

    /**
     * Core stages decide the file's OCR status: while one is running the file
     * is processing, and if one fails the file has failed. An optional stage
     * failing never fails the file — OCR text and extraction stand on their own.
     */
    public function isCore(): bool
    {
        return $this === self::Recognize || $this === self::Extract;
    }

    public function next(): ?self
    {
        $stages = self::cases();
        $index = array_search($this, $stages, true);

        return $stages[$index + 1] ?? null;
    }

    /**
     * This stage and every stage after it.
     *
     * @return list<self>
     */
    public function andAfter(): array
    {
        return array_slice(self::cases(), (int) array_search($this, self::cases(), true));
    }

    /**
     * Every stage before this one.
     *
     * @return list<self>
     */
    public function before(): array
    {
        return array_slice(self::cases(), 0, (int) array_search($this, self::cases(), true));
    }
}

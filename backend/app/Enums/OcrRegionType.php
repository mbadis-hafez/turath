<?php

namespace App\Enums;

/** What a detected region on an OCR'd page actually is, before any text is trusted from it. */
enum OcrRegionType: string
{
    case PrintedText = 'printed_text';
    case Handwriting = 'handwriting';
    case Logo = 'logo';
    case Photograph = 'photograph';
    case Signature = 'signature';
    case FormLabel = 'form_label';
    case FormValue = 'form_value';
    case Footer = 'footer';
    case Noise = 'noise';
    case Unknown = 'unknown';

    /** Whether this region's text may be safely OCR'd and treated as machine-readable. */
    public function ocrAllowed(): bool
    {
        return match ($this) {
            self::PrintedText, self::FormLabel, self::Footer => true,
            default => false,
        };
    }

    /** Whether an AI correction pass may run on this region's OCR text. */
    public function aiCorrectionAllowed(): bool
    {
        return $this->ocrAllowed();
    }

    public function requiresHumanReview(): bool
    {
        return match ($this) {
            self::Handwriting, self::Signature, self::FormValue, self::Unknown => true,
            default => false,
        };
    }

    public function defaultReviewReason(): ?string
    {
        return match ($this) {
            self::Handwriting => 'Handwritten content requires manual transcription.',
            self::Signature => 'Signature — not transcribable text.',
            self::FormValue => 'Form value could not be confidently classified as printed or handwritten.',
            self::Unknown => 'Unable to confidently classify this region.',
            default => null,
        };
    }
}

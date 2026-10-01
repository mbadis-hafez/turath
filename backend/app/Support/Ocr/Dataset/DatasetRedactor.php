<?php

namespace App\Support\Ocr\Dataset;

/**
 * Takes contact details out of text before it is kept as an example: emails,
 * links, and numbers long enough to be a phone or ID number (nine digits or
 * more, in any digit script, separators allowed). Shorter numbers — years,
 * dates, dimensions, inventory numbers — are exactly what OCR has to learn to
 * read, so they stay. Unlike the correction masker, placeholders aren't
 * numbered: an example never needs the original back.
 */
final class DatasetRedactor
{
    private const DIGIT = '[0-9\x{0660}-\x{0669}\x{06F0}-\x{06F9}]';

    private const MIN_DIGITS = 9;

    /**
     * @return array{text: ?string, redacted: bool}
     */
    public function redact(?string $text): array
    {
        if ($text === null || $text === '') {
            return ['text' => $text, 'redacted' => false];
        }

        $digit = self::DIGIT;
        $pattern = '~(?<U>(?:https?://|www\.)[^\s<>"]+)'
            .'|(?<E>[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,})'
            ."|(?<N>\\+?{$digit}(?:{$digit}|[\\s\\-/.()](?={$digit}))*)~iu";

        $redacted = false;
        $out = preg_replace_callback($pattern, function (array $m) use (&$redacted, $digit) {
            $replacement = match (true) {
                $m['U'] !== '' => '⟦LINK⟧',
                $m['E'] !== '' => '⟦EMAIL⟧',
                preg_match_all("~{$digit}~u", $m[0]) >= self::MIN_DIGITS => '⟦NUMBER⟧',
                default => $m[0],
            };
            $redacted = $redacted || $replacement !== $m[0];

            return $replacement;
        }, $text);

        return ['text' => $out ?? $text, 'redacted' => $redacted];
    }
}

<?php

namespace App\Support\Ocr\Correction;

/**
 * Replaces emails, links and every number in OCR text with numbered
 * placeholders (⟦E1⟧, ⟦U1⟧, ⟦N1⟧) before the text goes to an AI provider,
 * and restores them afterwards. Two reasons:
 *
 * - privacy: contact details (the benchmark letter's footer has an email and
 *   a mobile number) never leave our infrastructure;
 * - safety: numbers are facts — dates, phone numbers, years, dimensions —
 *   and an OCR correction pass has no business "fixing" any of them. Digit
 *   scripts (ASCII, Arabic-Indic, Persian) are preserved exactly as scanned;
 *   normalizing them is a deterministic later step, not an AI judgement.
 *
 * Masking is deterministic (numbered in order of appearance), so the same
 * text always masks the same way and identical masked text is one cache entry
 * no matter whose contact details it hid.
 */
class SensitiveTokenMasker
{
    /**
     * One pass, so a placeholder already inserted is never re-scanned (the
     * "1" in ⟦E1⟧ is not a number to mask), and alternatives are tried in
     * order at each position: a link or email wins over the digits inside it.
     * A number is a run of digits in any script, with the separators a phone
     * number, date or measurement uses between them — "+966 554711681",
     * "1445/08/02", "٢٠٢٤".
     */
    private const PATTERN = '~(?<U>(?:https?://|www\.)[^\s<>"⟦⟧]+)'
        .'|(?<E>[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,})'
        .'|(?<N>\+?[0-9\x{0660}-\x{0669}\x{06F0}-\x{06F9}](?:[0-9\x{0660}-\x{0669}\x{06F0}-\x{06F9}]|[\s\-/.,()](?=[0-9\x{0660}-\x{0669}\x{06F0}-\x{06F9}]))*)~iu';

    private const PLACEHOLDER = '~⟦[EUN]\d+⟧~u';

    /**
     * @return array{text: string, tokens: array<string, string>} tokens: placeholder => original
     */
    public function mask(string $text): array
    {
        $tokens = [];
        $counters = ['E' => 0, 'U' => 0, 'N' => 0];

        $masked = (string) preg_replace_callback(self::PATTERN, function (array $m) use (&$tokens, &$counters) {
            $kind = $m['U'] !== '' ? 'U' : ($m['E'] !== '' ? 'E' : 'N');
            $placeholder = '⟦'.$kind.(++$counters[$kind]).'⟧';
            $tokens[$placeholder] = $m[0];

            return $placeholder;
        }, $text);

        return ['text' => $masked, 'tokens' => $tokens];
    }

    /**
     * @param  array<string, string>  $tokens
     */
    public function restore(string $text, array $tokens): string
    {
        return strtr($text, $tokens);
    }

    /**
     * Every placeholder in the text, with how often it occurs.
     *
     * @return array<string, int>
     */
    public function placeholderCounts(string $text): array
    {
        preg_match_all(self::PLACEHOLDER, $text, $matches);

        return array_count_values($matches[0]);
    }
}

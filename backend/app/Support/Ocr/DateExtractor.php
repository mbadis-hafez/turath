<?php

namespace App\Support\Ocr;

use App\Enums\DateCalendar;
use App\Enums\ExtractedDateType;

/**
 * Finds `dd/mm/yyyy`-shaped dates and classifies each by calendar from its
 * year alone — Hijri years currently fall roughly 1440-1460, Gregorian in the
 * 1900s-2000s, so the two ranges don't collide. Deliberately does NOT convert
 * between calendars or collapse a Hijri/Gregorian pair into one date: a
 * Saudi official letter's dual-dated header is two distinct facts about the
 * same document, not one fact expressed twice.
 */
class DateExtractor
{
    /** Matches both d/m/Y (this document's header dates) and Y/m/d (its handwritten signature date) — the year is whichever outer group is 3-4 digits. */
    private const DATE_PATTERN = '/\b(\d{1,4})\/(\d{1,2})\/(\d{1,4})\b/';

    private const HIJRI_YEAR_MIN = 1300;

    private const HIJRI_YEAR_MAX = 1500;

    private const GREGORIAN_YEAR_MIN = 1900;

    private const GREGORIAN_YEAR_MAX = 2099;

    /** Arabic words that, found shortly before a date, mean that date is when something was signed rather than when the document was issued. */
    private const SIGNATURE_CONTEXT_WORDS = ['توقيع', 'التوقيع'];

    /**
     * @param  array<string, array<int, array{text: string, confidence: int}>>  $pagesByLanguage  language => [page_number => ['text' => ..., ...]]
     * @return array<int, array{value: string, calendar: DateCalendar, date_type: ExtractedDateType, source_page: ?int}>
     */
    public function extract(array $pagesByLanguage): array
    {
        $seen = [];
        $dates = [];

        foreach (['ar', 'en'] as $lang) {
            foreach ($pagesByLanguage[$lang] ?? [] as $pageNumber => $page) {
                $text = $page['text'];
                if (! preg_match_all(self::DATE_PATTERN, $text, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                    continue;
                }

                foreach ($matches as $match) {
                    $value = $match[0][0];
                    $year = $this->yearFrom($match[1][0], $match[3][0]);
                    if ($year === null) {
                        continue;
                    }
                    $calendar = $this->calendarFor($year);
                    if ($calendar === null) {
                        continue;
                    }

                    $dedupeKey = "{$pageNumber}|{$value}";
                    if (isset($seen[$dedupeKey])) {
                        continue;
                    }
                    $seen[$dedupeKey] = true;

                    // preg_match_all's offset is a byte offset; mb_substr needs a character offset, which
                    // differ for multibyte Arabic text — converting wrong here would silently pull context
                    // from the wrong part of the string (or throw it away) on every Arabic-preceded date.
                    $charOffset = mb_strlen(substr($text, 0, $match[0][1]));
                    $context = mb_substr($text, max(0, $charOffset - 30), 30);
                    $dates[] = [
                        'value' => $value,
                        'calendar' => $calendar,
                        'date_type' => $this->typeFor($context),
                        'source_page' => (int) $pageNumber,
                    ];
                }
            }
        }

        return $dates;
    }

    private function yearFrom(string $firstGroup, string $lastGroup): ?int
    {
        if (strlen($firstGroup) >= 3) {
            return (int) $firstGroup;
        }
        if (strlen($lastGroup) >= 3) {
            return (int) $lastGroup;
        }

        return null;
    }

    private function calendarFor(int $year): ?DateCalendar
    {
        if ($year >= self::HIJRI_YEAR_MIN && $year <= self::HIJRI_YEAR_MAX) {
            return DateCalendar::Hijri;
        }
        if ($year >= self::GREGORIAN_YEAR_MIN && $year <= self::GREGORIAN_YEAR_MAX) {
            return DateCalendar::Gregorian;
        }

        return null;
    }

    private function typeFor(string $precedingContext): ExtractedDateType
    {
        foreach (self::SIGNATURE_CONTEXT_WORDS as $word) {
            if (mb_stripos($precedingContext, $word) !== false) {
                return ExtractedDateType::SignatureDate;
            }
        }

        return ExtractedDateType::DocumentIssueDate;
    }
}

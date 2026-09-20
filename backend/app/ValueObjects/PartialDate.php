<?php

namespace App\ValueObjects;

use App\Enums\CalendarType;
use App\Enums\DateCertainty;

readonly class PartialDate
{
    public function __construct(
        public ?string $display = null,
        public ?int $yearFrom = null,
        public ?int $yearTo = null,
        public ?CalendarType $calendar = null,
        public ?DateCertainty $certainty = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->display === null
            && $this->yearFrom === null
            && $this->yearTo === null;
    }

    /**
     * @return array{display: string|null, year_from: int|null, year_to: int|null, calendar: string|null, certainty: string|null}
     */
    public function toArray(): array
    {
        return [
            'display' => $this->display,
            'year_from' => $this->yearFrom,
            'year_to' => $this->yearTo,
            'calendar' => $this->calendar?->value,
            'certainty' => $this->certainty?->value,
        ];
    }

    /**
     * Parses a free-text date-ish spreadsheet cell (e.g. "1975", "c. 1939",
     * "1970-1975", "1409 hijri; 1984/1985", "tbc") into a structured
     * PartialDate. Used by F4's importers only — F1 editors fill the
     * structured fields directly rather than typing a string to be parsed.
     * Never throws; unparseable input keeps the raw text as `display` with
     * null years and certainty `unknown`, exactly like DimensionParser's
     * "never guess" rule for dimensions.
     */
    public static function fromString(string $raw): ?self
    {
        $trimmed = trim($raw);

        if ($trimmed === '') {
            return null;
        }

        $isCirca = (bool) preg_match('/\b(circa|ca\.?|c\.)\s*\d/i', $trimmed) || str_contains($trimmed, 'نحو');

        if (preg_match('/(\d{3,4})\s*(?:hijri|h\b|هـ)/iu', $trimmed, $m)) {
            $year = (int) $m[1];

            return new self(
                display: $trimmed,
                yearFrom: $year,
                yearTo: $year,
                calendar: CalendarType::Hijri,
                certainty: $isCirca ? DateCertainty::Circa : DateCertainty::Exact,
            );
        }

        if (preg_match('/(\d{4})\s*[-–\/]\s*(\d{4})/u', $trimmed, $m)) {
            $from = (int) $m[1];
            $to = (int) $m[2];

            if ($from <= $to) {
                return new self(
                    display: $trimmed,
                    yearFrom: $from,
                    yearTo: $to,
                    calendar: CalendarType::Gregorian,
                    certainty: DateCertainty::Range,
                );
            }
        }

        if (preg_match('/\b(\d{4})\b/', $trimmed, $m)) {
            $year = (int) $m[1];

            return new self(
                display: $trimmed,
                yearFrom: $year,
                yearTo: $year,
                calendar: CalendarType::Gregorian,
                certainty: $isCirca ? DateCertainty::Circa : DateCertainty::Exact,
            );
        }

        return self::fromArabicDecade($trimmed) ?? new self(
            display: $trimmed,
            calendar: CalendarType::Gregorian,
            certainty: DateCertainty::Unknown,
        );
    }

    /**
     * "أوائل الثمانينيات الميلادية" → circa 1980–1983. A bare decade is a
     * range; a qualified one (early/mid/late) is circa. Hijri phrases are
     * left unparsed rather than guessed.
     */
    private static function fromArabicDecade(string $text): ?self
    {
        $clean = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $text) ?? $text;

        if (preg_match('/هـ|هجري/u', $clean)) {
            return null;
        }

        $decades = [
            'العشرين' => 1920, 'الثلاثين' => 1930, 'الأربعين' => 1940, 'الاربعين' => 1940, 'الخمسين' => 1950,
            'الستين' => 1960, 'السبعين' => 1970, 'الثمانين' => 1980, 'التسعين' => 1990,
        ];

        foreach ($decades as $stem => $start) {
            if (! preg_match('/'.$stem.'(?:ي)?ات/u', $clean)) {
                continue;
            }

            [$from, $to, $certainty] = match (true) {
                (bool) preg_match('/أوائل|اوائل|مطلع|بداية|بدايات/u', $clean) => [$start, $start + 3, DateCertainty::Circa],
                (bool) preg_match('/منتصف|أواسط|اواسط|وسط/u', $clean) => [$start + 4, $start + 6, DateCertainty::Circa],
                (bool) preg_match('/أواخر|اواخر|نهاية|نهايات/u', $clean) => [$start + 7, $start + 9, DateCertainty::Circa],
                default => [$start, $start + 9, DateCertainty::Range],
            };

            return new self(display: $text, yearFrom: $from, yearTo: $to, calendar: CalendarType::Gregorian, certainty: $certainty);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function fromArray(?array $data): ?self
    {
        if ($data === null) {
            return null;
        }

        $date = new self(
            display: $data['display'] ?? null,
            yearFrom: isset($data['year_from']) ? (int) $data['year_from'] : null,
            yearTo: isset($data['year_to']) ? (int) $data['year_to'] : null,
            calendar: isset($data['calendar']) ? CalendarType::tryFrom((string) $data['calendar']) : null,
            certainty: isset($data['certainty']) ? DateCertainty::tryFrom((string) $data['certainty']) : null,
        );

        return $date->isEmpty() ? null : $date;
    }
}

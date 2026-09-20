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

        return new self(
            display: $trimmed,
            calendar: CalendarType::Gregorian,
            certainty: DateCertainty::Unknown,
        );
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

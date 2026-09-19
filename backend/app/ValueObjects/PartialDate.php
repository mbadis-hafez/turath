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

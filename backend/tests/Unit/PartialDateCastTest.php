<?php

use App\Enums\CalendarType;
use App\Enums\DateCertainty;
use App\Models\Artist;
use App\ValueObjects\PartialDate;

it('round-trips a partial date through the cast', function () {
    $artist = Artist::factory()->create([
        'birth' => [
            'display' => 'c. 1939',
            'year_from' => 1939,
            'year_to' => 1941,
            'calendar' => 'gregorian',
            'certainty' => 'circa',
        ],
    ]);

    $artist->refresh();

    expect($artist->birth)->toBeInstanceOf(PartialDate::class)
        ->and($artist->birth->display)->toBe('c. 1939')
        ->and($artist->birth->yearFrom)->toBe(1939)
        ->and($artist->birth->yearTo)->toBe(1941)
        ->and($artist->birth->calendar)->toBe(CalendarType::Gregorian)
        ->and($artist->birth->certainty)->toBe(DateCertainty::Circa)
        ->and($artist->birth->isEmpty())->toBeFalse();
});

it('returns null for an all-null partial date', function () {
    $artist = Artist::factory()->create();

    expect($artist->birth)->toBeNull()
        ->and($artist->death)->toBeNull();
});

it('clears the columns when set to null', function () {
    $artist = Artist::factory()->create([
        'death' => ['display' => '2005', 'year_from' => 2005, 'year_to' => 2005, 'calendar' => 'hijri', 'certainty' => 'exact'],
    ]);

    $artist->update(['death' => null]);

    expect($artist->refresh()->death)->toBeNull()
        ->and($artist->death_date_display)->toBeNull()
        ->and($artist->death_year_from)->toBeNull()
        ->and($artist->death_calendar)->toBe('gregorian')
        ->and($artist->death_certainty)->toBe('unknown');
});

it('builds from array and array', function () {
    $date = PartialDate::fromArray(['display' => '1900', 'year_from' => 1900, 'year_to' => 1900, 'calendar' => 'gregorian', 'certainty' => 'exact']);

    expect($date->toArray())->toBe([
        'display' => '1900',
        'year_from' => 1900,
        'year_to' => 1900,
        'calendar' => 'gregorian',
        'certainty' => 'exact',
    ])->and(PartialDate::fromArray(null))->toBeNull()
        ->and(PartialDate::fromArray(['calendar' => 'gregorian', 'certainty' => 'unknown']))->toBeNull();
});

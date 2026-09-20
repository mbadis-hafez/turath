<?php

use App\Enums\CalendarType;
use App\Enums\DateCertainty;
use App\ValueObjects\PartialDate;

it('parses free-text date strings from spreadsheet cells', function (
    string $input,
    ?int $yearFrom,
    ?int $yearTo,
    ?CalendarType $calendar,
    ?DateCertainty $certainty,
) {
    $date = PartialDate::fromString($input);

    expect($date)->not->toBeNull()
        ->and($date->yearFrom)->toBe($yearFrom)
        ->and($date->yearTo)->toBe($yearTo)
        ->and($date->calendar)->toBe($calendar)
        ->and($date->certainty)->toBe($certainty)
        ->and($date->display)->toBe($input);
})->with([
    ['1975', 1975, 1975, CalendarType::Gregorian, DateCertainty::Exact],
    ['c. 1939', 1939, 1939, CalendarType::Gregorian, DateCertainty::Circa],
    ['circa 1939', 1939, 1939, CalendarType::Gregorian, DateCertainty::Circa],
    ['1970-1975', 1970, 1975, CalendarType::Gregorian, DateCertainty::Range],
    ['1970 – 1975', 1970, 1975, CalendarType::Gregorian, DateCertainty::Range],
    ['1409 hijri; 1984/1985', 1409, 1409, CalendarType::Hijri, DateCertainty::Exact],
    ['Not available', null, null, CalendarType::Gregorian, DateCertainty::Unknown],
    ['tbc', null, null, CalendarType::Gregorian, DateCertainty::Unknown],
    // Real values from the documentation cards (Bugis and Alshalti).
    ['أوائل الثمانينيات الميلادية', 1980, 1983, CalendarType::Gregorian, DateCertainty::Circa],
    ['1984م', 1984, 1984, CalendarType::Gregorian, DateCertainty::Exact],
    ['منتصف السبعينات', 1974, 1976, CalendarType::Gregorian, DateCertainty::Circa],
    ['أواخر الستينيات', 1967, 1969, CalendarType::Gregorian, DateCertainty::Circa],
    ['الثمانينيات', 1980, 1989, CalendarType::Gregorian, DateCertainty::Range],
    ['السبعينات الهجرية', null, null, CalendarType::Gregorian, DateCertainty::Unknown],
]);

it('returns null for an empty string', function () {
    expect(PartialDate::fromString(''))->toBeNull();
    expect(PartialDate::fromString('   '))->toBeNull();
});

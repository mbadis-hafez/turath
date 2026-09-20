<?php

use App\Support\DimensionParser;

it('parses real messy dimension strings from the source spreadsheets', function (
    string $input,
    ?float $height,
    ?float $width,
    ?float $depth,
    string $confidence,
) {
    $parsed = DimensionParser::parse($input);

    expect($parsed->heightCm)->toBe($height)
        ->and($parsed->widthCm)->toBe($width)
        ->and($parsed->depthCm)->toBe($depth)
        ->and($parsed->confidence)->toBe($confidence);
})->with([
    ['60H x 45W cm', 60.0, 45.0, null, 'high'],
    ['61H x 91W x 2D cm', 61.0, 91.0, 2.0, 'high'],
    ['94x122x3.6cm', 94.0, 122.0, 3.6, 'low'],
    ['91,5H x 33W cm', 91.5, 33.0, null, 'high'],
    ['90.5 x 121 cm', 90.5, 121.0, null, 'low'],
    ['44H x 35 x 1/2W cm', 44.0, null, null, 'low'],
    ['Not available - tbc', null, null, null, 'low'],
    ['tbc', null, null, null, 'low'],
    ['', null, null, null, 'low'],
    ['130H x 210W x 140D cm', 130.0, 210.0, 140.0, 'high'],
]);

it('never throws on unparseable input and always keeps the original string', function () {
    $parsed = DimensionParser::parse('¯\\_(ツ)_/¯ dimensions unknown');

    expect($parsed->heightCm)->toBeNull()
        ->and($parsed->confidence)->toBe('low')
        ->and($parsed->raw)->toBe('¯\\_(ツ)_/¯ dimensions unknown');
});

it('returns a null raw value for empty input', function () {
    expect(DimensionParser::parse('')->raw)->toBeNull();
});

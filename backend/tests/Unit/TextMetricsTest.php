<?php

use App\Support\Ocr\Evaluation\TextMetrics;

it('counts Arabic errors per letter, not per byte', function () {
    // One letter misread (ة read as ه): one error in twelve characters.
    expect(TextMetrics::charErrors('مدينه الرياض', 'مدينة الرياض'))->toBe(['errors' => 1, 'length' => 12])
        ->and(TextMetrics::wordErrors('مدينه الرياض', 'مدينة الرياض'))->toBe(['errors' => 1, 'length' => 2]);
});

it('separates misreadings from spelling variants when normalized', function () {
    expect(TextMetrics::charErrors('مدينه الرياض', 'مدينة الرياض', normalized: true)['errors'])->toBe(0)
        ->and(TextMetrics::charErrors('أحمد', 'احمد', normalized: true)['errors'])->toBe(0)
        ->and(TextMetrics::charErrors('محمد', 'احمد', normalized: true)['errors'])->toBe(1);
});

it('measures insertions, deletions and word order', function () {
    expect(TextMetrics::distance(['a', 'b', 'c'], ['a', 'c']))->toBe(1)
        ->and(TextMetrics::distance([], ['x', 'y']))->toBe(2)
        ->and(TextMetrics::wordErrors('opening of the exhibition', 'the exhibition opening')['errors'])->toBe(3);
});

it('ignores whitespace differences, and has no rate for an empty reference', function () {
    expect(TextMetrics::charErrors("  معرض \n الفنون ", 'معرض الفنون')['errors'])->toBe(0)
        ->and(TextMetrics::same(' a  b ', 'a b'))->toBeTrue()
        ->and(TextMetrics::rate(3, 0))->toBeNull()
        ->and(TextMetrics::rate(1, 8))->toBe(0.125);
});

<?php

use App\Support\ArabicNormalizer;

it('normalizes arabic and latin input', function (string $input, string $expected) {
    expect(ArabicNormalizer::normalize($input))->toBe($expected);
})->with([
    'alef hamza' => ['أحمد', 'احمد'],
    'alef hamza below' => ['إبراهيم', 'ابراهيم'],
    'diacritics' => ['مُحَمَّد', 'محمد'],
    'teh marbuta' => ['فاطمة', 'فاطمه'],
    'two tokens' => ['عبد الله', 'عبد الله'],
    'latin hyphen' => ['Al-Nawawi', 'al nawawi'],
    'arabic indic digits' => ['١٩٧٥', '1975'],
    'whitespace collapse' => ['  ضياء   عزيز ', 'ضياء عزيز'],
]);

it('compacts whitespace away', function (string $input, string $expected) {
    expect(ArabicNormalizer::compact($input))->toBe($expected);
})->with([
    'abdullah' => ['عبد الله', 'عبدالله'],
    'al nawawi' => ['Al-Nawawi', 'alnawawi'],
    'diaa aziz' => ['ضياء عزيز', 'ضياءعزيز'],
]);

it('is idempotent', function (string $input) {
    $once = ArabicNormalizer::normalize($input);

    expect(ArabicNormalizer::normalize($once))->toBe($once)
        ->and(ArabicNormalizer::compact(ArabicNormalizer::compact($input)))->toBe(ArabicNormalizer::compact($input));
})->with([
    'mixed' => ['مُحَمَّد Al-Nawawi ١٩٧٥'],
    'arabic' => ['عبد الحليم رضوي'],
    'punctuation' => ['Ahmad, Almaghlouth (1939)'],
]);

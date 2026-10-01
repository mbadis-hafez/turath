<?php

use App\Support\Ocr\Dataset\DatasetRedactor;

it('takes emails, links and phone-length numbers out of example text', function (string $text, string $expected) {
    $result = (new DatasetRedactor)->redact($text);

    expect($result['text'])->toBe($expected)->and($result['redacted'])->toBeTrue();
})->with([
    'email' => ['للتواصل: someone@example.test', 'للتواصل: ⟦EMAIL⟧'],
    'link' => ['https://example.test/profile شخصي', '⟦LINK⟧ شخصي'],
    'mobile' => ['الجوال 0500000001', 'الجوال ⟦NUMBER⟧'],
    'spaced international' => ['+966 50 000 0001', '⟦NUMBER⟧'],
    'Arabic-Indic digits' => ['جوال ٠٥٠٠٠٠٠٠٠١', 'جوال ⟦NUMBER⟧'],
]);

it('keeps the numbers OCR has to learn: years, dates, dimensions, references', function (string $text) {
    $result = (new DatasetRedactor)->redact($text);

    expect($result['text'])->toBe($text)->and($result['redacted'])->toBeFalse();
})->with([
    'hijri date' => ['١٤٤٥/٠٨/٠٢ هـ'],
    'gregorian date' => ['2024-03-14'],
    'year' => ['ولد عام 1950'],
    'dimensions' => ['120 × 80 سم'],
    'reference' => ['AR036-0012'],
]);

it('leaves null and empty text alone', function () {
    expect((new DatasetRedactor)->redact(null))->toBe(['text' => null, 'redacted' => false])
        ->and((new DatasetRedactor)->redact(''))->toBe(['text' => '', 'redacted' => false]);
});

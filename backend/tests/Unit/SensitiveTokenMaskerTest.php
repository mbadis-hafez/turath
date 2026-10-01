<?php

use App\Support\Ocr\Correction\SensitiveTokenMasker;

it('masks the email and mobile number in a letterhead footer', function () {
    // Synthetic stand-in for the benchmark letter's footer line.
    $masked = (new SensitiveTokenMasker)->mask('info@gallery.example +966500000000');

    expect($masked['text'])->toBe('⟦E1⟧ ⟦N1⟧')
        ->and($masked['tokens'])->toBe(['⟦E1⟧' => 'info@gallery.example', '⟦N1⟧' => '+966500000000']);
});

it('keeps a spaced phone number, and each date, as one protected token', function () {
    $masked = (new SensitiveTokenMasker)->mask('على الرقم +966 500000000 بتاريخ 1445/08/02 الموافق 2024/12/02');

    expect($masked['text'])->toBe('على الرقم ⟦N1⟧ بتاريخ ⟦N2⟧ الموافق ⟦N3⟧')
        ->and($masked['tokens']['⟦N2⟧'])->toBe('1445/08/02');
});

it('protects Arabic-Indic and Persian digits exactly as scanned', function () {
    $masker = new SensitiveTokenMasker;
    $source = 'سنة ٢٠٢٤ وسنة ۱۴۴۵';
    $masked = $masker->mask($source);

    expect($masked['text'])->toBe('سنة ⟦N1⟧ وسنة ⟦N2⟧')
        ->and($masker->restore($masked['text'], $masked['tokens']))->toBe($source);
});

it('masks a link whole rather than the numbers inside it, and never re-masks a placeholder', function () {
    $masked = (new SensitiveTokenMasker)->mask('انظر https://example.org/items/12?page=3 و a1@example.org');

    expect($masked['text'])->toBe('انظر ⟦U1⟧ و ⟦E1⟧')
        ->and($masked['tokens'])->toHaveCount(2);
});

it('does not swallow the full stop after a number', function () {
    expect((new SensitiveTokenMasker)->mask('تأسست في عام 1980.')['text'])->toBe('تأسست في عام ⟦N1⟧.');
});

it('round-trips mixed Arabic and English text unchanged', function () {
    $masker = new SensitiveTokenMasker;
    $source = "Visual Arts Commission هيئة الفنون البصرية\nTel: 011 000 0000 — contact@example.org";
    $masked = $masker->mask($source);

    expect($masker->restore($masked['text'], $masked['tokens']))->toBe($source);
});

it('counts placeholders by occurrence', function () {
    expect((new SensitiveTokenMasker)->placeholderCounts('⟦N1⟧ و ⟦N1⟧ ثم ⟦E1⟧'))->toBe(['⟦N1⟧' => 2, '⟦E1⟧' => 1]);
});

it('leaves text with nothing sensitive untouched', function () {
    expect((new SensitiveTokenMasker)->mask('المملكة العربية السعودية'))->toBe(['text' => 'المملكة العربية السعودية', 'tokens' => []]);
});

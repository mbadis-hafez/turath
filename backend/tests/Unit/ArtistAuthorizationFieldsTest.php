<?php

use App\Models\FileOcrFormField;
use App\Support\Ocr\ArtistAuthorizationFields;

function formField(int $id, string $label, array $attributes = []): FileOcrFormField
{
    return (new FileOcrFormField)->forceFill([
        'id' => $id, 'field_label' => $label, 'machine_value' => null, 'manual_value' => null,
        'requires_manual_transcription' => true, ...$attributes,
    ]);
}

it('maps the garbled labels tesseract actually produced on the benchmark letter', function () {
    $fields = new ArtistAuthorizationFields;

    // Real OCR of "اسم الفنان/ة:" and "رقم الجوال:" on AR036 page 2.
    expect($fields->keyFor('3 الفنان/ة:'))->toBe('artist_name')
        ->and($fields->keyFor('دك الجوال'))->toBe('phone')
        ->and($fields->keyFor('البريد الإلكتروني:'))->toBe('email')
        ->and($fields->keyFor('العنوان:'))->toBe('address')
        ->and($fields->keyFor('E-mail'))->toBe('email')
        ->and($fields->keyFor('الموضوع'))->toBeNull();
});

it('reads "عنوان البريد الإلكتروني" as an email label, not an address', function () {
    expect((new ArtistAuthorizationFields)->keyFor('عنوان البريد الإلكتروني'))->toBe('email');
});

it('prefers the reviewer transcription, offers printed OCR text, and never offers handwriting OCR text', function () {
    $extracted = (new ArtistAuthorizationFields)->extract([
        formField(1, 'اسم الفنان/ة', ['manual_value' => 'أحمد المغلوث', 'machine_value' => 'garbled']),
        formField(2, 'رقم الجوال', ['machine_value' => '031/4410', 'requires_manual_transcription' => true]),
        formField(3, 'العنوان', ['machine_value' => 'الرياض', 'requires_manual_transcription' => false]),
    ]);

    expect($extracted['artist_name'])->toMatchArray(['form_field_id' => 1, 'value' => 'أحمد المغلوث', 'method' => 'manually_transcribed', 'needs_transcription' => false])
        ->and($extracted['phone'])->toMatchArray(['form_field_id' => 2, 'value' => null, 'method' => null, 'needs_transcription' => true])
        ->and($extracted['address'])->toMatchArray(['form_field_id' => 3, 'value' => 'الرياض', 'method' => 'ocr_derived'])
        ->and($extracted['email'])->toMatchArray(['form_field_id' => null, 'value' => null, 'needs_transcription' => false]);
});

it('keeps the first field in document order when two labels map to the same key', function () {
    $extracted = (new ArtistAuthorizationFields)->extract([
        formField(4, 'رقم الجوال', ['manual_value' => '0500000001']),
        formField(9, 'الجوال', ['manual_value' => '0500000002']),
    ]);

    expect($extracted['phone']['form_field_id'])->toBe(4);
});

<?php

use App\Enums\OcrRegionType;
use App\Support\Ocr\FormFieldDetector;

function region(int $id, OcrRegionType $type, array $bbox, ?string $text = null): array
{
    return ['id' => $id, 'region_type' => $type, 'bbox' => $bbox, 'source_text' => $text];
}

it('pairs a form label with a handwritten value on the same row, to its left (RTL)', function () {
    $label = region(1, OcrRegionType::FormLabel, ['x' => 400, 'y' => 100, 'width' => 120, 'height' => 30], 'اسم الفنان/ة:');
    $value = region(2, OcrRegionType::Handwriting, ['x' => 100, 'y' => 98, 'width' => 200, 'height' => 34]);
    $decoy = region(3, OcrRegionType::PrintedText, ['x' => 100, 'y' => 500, 'width' => 200, 'height' => 30], 'unrelated body text');

    $fields = (new FormFieldDetector)->pair([$label, $value, $decoy]);

    expect($fields)->toHaveCount(1)
        ->and($fields[0]['field_label'])->toBe('اسم الفنان/ة')
        ->and($fields[0]['value_region_id'])->toBe(2)
        ->and($fields[0]['value_type'])->toBe('handwriting')
        ->and($fields[0]['machine_value'])->toBeNull() // handwriting is never auto-transcribed
        ->and($fields[0]['requires_manual_transcription'])->toBeTrue();
});

it('pairs a form label with a printed value directly below it when nothing is on the same row', function () {
    $label = region(1, OcrRegionType::FormLabel, ['x' => 100, 'y' => 100, 'width' => 100, 'height' => 30], 'المرجع:');
    $value = region(2, OcrRegionType::PrintedText, ['x' => 100, 'y' => 140, 'width' => 300, 'height' => 30], 'AR036');

    $fields = (new FormFieldDetector)->pair([$label, $value]);

    expect($fields[0]['value_region_id'])->toBe(2)
        ->and($fields[0]['value_type'])->toBe('printed_text')
        ->and($fields[0]['machine_value'])->toBe('AR036')
        ->and($fields[0]['requires_manual_transcription'])->toBeFalse();
});

it('leaves a label with no field_label text and no nearby candidate as manual-entry-required with no value region', function () {
    $label = region(1, OcrRegionType::FormLabel, ['x' => 100, 'y' => 100, 'width' => 100, 'height' => 30], 'البريد الإلكتروني:');

    $fields = (new FormFieldDetector)->pair([$label]);

    expect($fields[0]['value_region_id'])->toBeNull()
        ->and($fields[0]['value_type'])->toBeNull()
        ->and($fields[0]['requires_manual_transcription'])->toBeTrue();
});

it('never assigns the same value region to two different labels', function () {
    $labelA = region(1, OcrRegionType::FormLabel, ['x' => 400, 'y' => 100, 'width' => 100, 'height' => 30], 'العنوان:');
    $labelB = region(2, OcrRegionType::FormLabel, ['x' => 400, 'y' => 160, 'width' => 100, 'height' => 30], 'رقم الجوال:');
    $onlyValue = region(3, OcrRegionType::Handwriting, ['x' => 100, 'y' => 98, 'width' => 200, 'height' => 34]);

    $fields = (new FormFieldDetector)->pair([$labelA, $labelB, $onlyValue]);

    $claimedIds = array_filter(array_column($fields, 'value_region_id'));
    expect($claimedIds)->toHaveCount(1);
});

it('excludes logos, photographs, noise, and footers from ever being treated as a form value', function () {
    $label = region(1, OcrRegionType::FormLabel, ['x' => 400, 'y' => 100, 'width' => 100, 'height' => 30], 'التاريخ:');
    $logo = region(2, OcrRegionType::Logo, ['x' => 100, 'y' => 98, 'width' => 200, 'height' => 34]);

    $fields = (new FormFieldDetector)->pair([$label, $logo]);

    expect($fields[0]['value_region_id'])->toBeNull();
});

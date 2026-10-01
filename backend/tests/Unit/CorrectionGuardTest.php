<?php

use App\Support\Ocr\Correction\CorrectionGuard;
use App\Support\Ocr\Correction\ProviderResponse;

function guardPayload(string $corrected, array $changes = [], array $overrides = []): array
{
    return [
        'corrected_text' => $corrected,
        'changes' => $changes,
        'name_candidates' => [],
        'confidence' => 0.95,
        'needs_review' => false,
        'reason' => null,
        ...$overrides,
    ];
}

function inspectCorrection(string $source, ?array $payload, ?string $problem = null)
{
    return (new CorrectionGuard)->inspect($source, new ProviderResponse($payload, [], 'model-x', 10, 10, $problem));
}

it('accepts text returned unchanged with no changes', function () {
    $result = inspectCorrection('المملكة العربية السعودية', guardPayload('المملكة العربية السعودية'));

    expect($result->usable)->toBeTrue()->and($result->flags)->toBe([]);
});

it('accepts a correction that is exactly the declared changes applied to the source', function () {
    $result = inspectCorrection('المملكه العربيه السعوديه', guardPayload('المملكة العربية السعودية', [
        ['original' => 'المملكه', 'corrected' => 'المملكة', 'type' => 'orthographic_normalization'],
        ['original' => 'العربيه', 'corrected' => 'العربية', 'type' => 'orthographic_normalization'],
        ['original' => 'السعوديه', 'corrected' => 'السعودية', 'type' => 'orthographic_normalization'],
    ]));

    expect($result->usable)->toBeTrue()
        ->and($result->flags)->toBe([])
        ->and($result->correctedText)->toBe('المملكة العربية السعودية');
});

it('flags a correction that changed text it did not declare', function () {
    $result = inspectCorrection('هيئة الفنون البصرية', guardPayload('هيئة الفنون التشكيلية'));

    expect($result->usable)->toBeTrue()->and($result->flags)->toContain('undeclared_change');
});

it('flags a declared change whose original is not in the source, including a pure insertion', function () {
    $missing = inspectCorrection('هيئة الفنون', guardPayload('هيئة الفنون', [['original' => 'غير موجود', 'corrected' => 'x', 'type' => 'other']]));
    $insertion = inspectCorrection('هيئة الفنون', guardPayload('هيئة الفنون البصرية', [['original' => '', 'corrected' => ' البصرية', 'type' => 'other']]));

    expect($missing->flags)->toContain('change_not_in_source')
        ->and($insertion->flags)->toContain('change_not_in_source')
        ->and($insertion->flags)->toContain('undeclared_change');
});

it('rejects output that drops, duplicates or invents a protected placeholder', function (string $corrected) {
    $result = inspectCorrection('اتصل على ⟦N1⟧ أو ⟦E1⟧', guardPayload($corrected));

    expect($result->usable)->toBeFalse()->and($result->flags)->toContain('placeholder_mismatch');
})->with([
    'dropped' => ['اتصل على ⟦N1⟧ أو'],
    'duplicated' => ['اتصل على ⟦N1⟧ ⟦N1⟧ أو ⟦E1⟧'],
    'invented' => ['اتصل على ⟦N1⟧ أو ⟦E1⟧ ⟦N2⟧'],
    'altered' => ['اتصل على ⟦N7⟧ أو ⟦E1⟧'],
]);

it('flags a rewrite that changes too much, or adds content', function () {
    $source = 'تماشيا مع المعايير المعتمدة في الهيئة نرجو التكرم بالموافقة';
    $rewrite = inspectCorrection($source, guardPayload('وفقاً للمعايير لدى الهيئة نأمل موافقتكم الكريمة', [
        ['original' => 'تماشيا مع المعايير المعتمدة في', 'corrected' => 'وفقاً للمعايير لدى', 'type' => 'other'],
        ['original' => 'نرجو التكرم بالموافقة', 'corrected' => 'نأمل موافقتكم الكريمة', 'type' => 'other'],
    ]));
    $padded = inspectCorrection('هيئة الفنون', guardPayload('هيئة الفنون البصرية في مدينة الرياض بالمملكة', [
        ['original' => 'الفنون', 'corrected' => 'الفنون البصرية في مدينة الرياض بالمملكة', 'type' => 'other'],
    ]));

    expect($rewrite->flags)->toContain('excessive_change')
        ->and($padded->flags)->toContain('length_change');
});

it('flags a change labelled as orthographic that actually changed the letters', function () {
    // "البصرية" → "التشكيلية" is a different word, not an alef/hamza/taa-marbuta fix.
    $result = inspectCorrection('هيئة الفنون البصرية', guardPayload('هيئة الفنون التشكيلية', [
        ['original' => 'البصرية', 'corrected' => 'التشكيلية', 'type' => 'orthographic_normalization'],
    ]));

    expect($result->flags)->toBe(['mislabeled_change']);
});

it('verifies surface fixes rather than trusting their label: hamza, alef maqsura, spacing and direction marks', function () {
    $result = inspectCorrection("الى مدرسه الفنون\u{200E} الجميله", guardPayload('إلى مدرسة الفنون الجميلة', [
        ['original' => 'الى', 'corrected' => 'إلى', 'type' => 'orthographic_normalization'],
        ['original' => 'مدرسه', 'corrected' => 'مدرسة', 'type' => 'orthographic_normalization'],
        ['original' => "الفنون\u{200E}", 'corrected' => 'الفنون', 'type' => 'bidi_cleanup'],
        ['original' => 'الجميله', 'corrected' => 'الجميلة', 'type' => 'orthographic_normalization'],
    ]));

    expect($result->usable)->toBeTrue()->and($result->flags)->toBe([]);
});

it('flags suspected misread names for review, leaving them out of the corrected text', function () {
    $result = inspectCorrection('أحمد المغلوت', guardPayload('أحمد المغلوت', [], [
        'name_candidates' => [['ocr_text' => 'المغلوت', 'candidate' => 'المغلوث', 'kind' => 'person']],
    ]));

    expect($result->usable)->toBeTrue()
        ->and($result->correctedText)->toBe('أحمد المغلوت')
        ->and($result->flags)->toBe(['name_candidates']);
});

it('keeps the model\'s own request for review', function () {
    $result = inspectCorrection('نص غير واضح', guardPayload('نص غير واضح', [], ['needs_review' => true, 'reason' => 'OCR ambiguity', 'confidence' => 0.55]));

    expect($result->flags)->toBe(['model_flagged'])
        ->and($result->modelReason)->toBe('OCR ambiguity')
        ->and($result->modelConfidence)->toBe(0.55);
});

it('rejects output that only nearly matches the schema', function (array $payload) {
    $result = inspectCorrection('نص', $payload);

    expect($result->usable)->toBeFalse()->and($result->flags)->toBe(['schema_invalid']);
})->with([
    'missing corrected_text' => [array_diff_key(guardPayload('نص'), ['corrected_text' => true])],
    'confidence above 1' => [guardPayload('نص', [], ['confidence' => 1.5])],
    'confidence as a string' => [guardPayload('نص', [], ['confidence' => '0.9'])],
    'unknown change type' => [guardPayload('نص', [['original' => 'a', 'corrected' => 'b', 'type' => 'rewrite']])],
    'needs_review not boolean' => [guardPayload('نص', [], ['needs_review' => 'no'])],
    'changes not a list' => [guardPayload('نص', ['first' => ['original' => 'a', 'corrected' => 'b', 'type' => 'other']])],
]);

it('rejects a response the provider could not complete', function () {
    $result = inspectCorrection('نص', null, 'truncated');

    expect($result->usable)->toBeFalse()->and($result->flags)->toBe(['provider_truncated']);
});

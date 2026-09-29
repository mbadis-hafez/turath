<?php

use App\Enums\DateCalendar;
use App\Enums\ExtractedDateType;
use App\Support\Ocr\DateExtractor;

it('keeps a dual Hijri/Gregorian header pair as two distinct dates, not one converted date', function () {
    $pagesByLanguage = [
        'ar' => [1 => ['text' => "تاريخ: 02/08/1445\nالموافق: 02/12/2024\n\nالموضوع: بدايات الحركة الفنية", 'confidence' => 90]],
        'en' => [1 => ['text' => '', 'confidence' => 0]],
    ];

    $dates = (new DateExtractor)->extract($pagesByLanguage);

    expect($dates)->toHaveCount(2);
    $hijri = collect($dates)->firstWhere('calendar', DateCalendar::Hijri);
    $gregorian = collect($dates)->firstWhere('calendar', DateCalendar::Gregorian);

    expect($hijri['value'])->toBe('02/08/1445')
        ->and($hijri['date_type'])->toBe(ExtractedDateType::DocumentIssueDate)
        ->and($gregorian['value'])->toBe('02/12/2024')
        ->and($gregorian['source_page'])->toBe(1);
});

it('tags a date found near "التوقيع" as a signature date, not a document issue date', function () {
    $pagesByLanguage = [
        'ar' => [2 => ['text' => 'التوقيع: ..........  التاريخ: 2024/12/7', 'confidence' => 20]],
        'en' => [],
    ];

    $dates = (new DateExtractor)->extract($pagesByLanguage);

    expect($dates)->toHaveCount(1)
        ->and($dates[0]['date_type'])->toBe(ExtractedDateType::SignatureDate)
        ->and($dates[0]['source_page'])->toBe(2);
});

it('does not classify an out-of-range year as either calendar', function () {
    $pagesByLanguage = ['ar' => [1 => ['text' => 'الرقم 07/45/9999 ليس تاريخا', 'confidence' => 50]], 'en' => []];

    expect((new DateExtractor)->extract($pagesByLanguage))->toBe([]);
});

it('does not duplicate the same date seen in both the Arabic and English OCR pass of the same page', function () {
    $pagesByLanguage = [
        'ar' => [1 => ['text' => '02/12/2024', 'confidence' => 90]],
        'en' => [1 => ['text' => '02/12/2024', 'confidence' => 85]],
    ];

    expect((new DateExtractor)->extract($pagesByLanguage))->toHaveCount(1);
});

<?php

use App\Enums\DateCalendar;
use App\Enums\DocumentType;
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

describe('semantic roles', function () {
    function datesOf(string $text, DocumentType $type): array
    {
        return (new DateExtractor)->extract(['ar' => [1 => ['text' => $text, 'confidence' => 90]], 'en' => []], $type);
    }

    it('reads birth and death years anchored by their words, in Arabic-Indic digits and with an attached "و"', function () {
        $dates = datesOf('ولد الفنان في مدينة الرياض عام ١٩٤٥م وتوفي سنة 1405هـ.', DocumentType::ArtistBiography);

        expect($dates)->toHaveCount(2)
            ->and($dates[0])->toMatchArray(['value' => '١٩٤٥م', 'normalized' => '1945', 'calendar' => DateCalendar::Gregorian, 'date_type' => ExtractedDateType::BirthDate, 'field_key' => 'birth_date'])
            ->and($dates[1])->toMatchArray(['value' => '1405هـ', 'normalized' => '1405', 'calendar' => DateCalendar::Hijri, 'date_type' => ExtractedDateType::DeathDate, 'field_key' => 'death_date']);
    });

    it("gives an invitation's dates opening and closing roles by the word nearest each", function () {
        $dates = datesOf('يسرنا دعوتكم لحضور افتتاح المعرض يوم 12/03/2024 ويستمر حتى 20/03/2024', DocumentType::ExhibitionDocument);

        expect(array_column($dates, 'field_key'))->toBe(['opening_date', 'closing_date'])
            ->and(array_column($dates, 'normalized'))->toBe(['2024-03-12', '2024-03-20'])
            ->and($dates[0]['date_type'])->toBe(ExtractedDateType::EventDate);
    });

    it('does not read "حتى" as a closing date outside an exhibition document', function () {
        expect(datesOf('يسري التفويض حتى 20/03/2024', DocumentType::ArtistAuthorization)[0]['date_type'])->toBe(ExtractedDateType::DocumentIssueDate);
    });

    it('reads month-name dates in their own calendar and never converts a dual date into one', function () {
        $dates = datesOf('تاريخ: ١٢ رجب ١٤٤٥هـ الموافق 24 January 2024', DocumentType::ArtistAuthorization);

        expect($dates)->toHaveCount(2)
            ->and($dates[0])->toMatchArray(['value' => '١٢ رجب ١٤٤٥', 'calendar' => DateCalendar::Hijri, 'normalized' => '1445-07-12'])
            ->and($dates[1])->toMatchArray(['value' => '24 January 2024', 'calendar' => DateCalendar::Gregorian, 'normalized' => '2024-01-24']);
    });

    it('keeps a date whose month cannot be right, without a normalized form', function () {
        expect(datesOf('التاريخ 31/14/2024', DocumentType::Unknown)[0]['normalized'])->toBeNull();
    });

    it("names the schema field only when the document type has one: a condition report's undated header is its report date", function () {
        expect(datesOf('14/05/2023', DocumentType::ArtworkConditionReport)[0]['field_key'])->toBe('report_date')
            ->and(datesOf('14/05/2023', DocumentType::Unknown)[0]['field_key'])->toBeNull()
            ->and(datesOf('14/05/2023', DocumentType::ArtistBiography)[0]['date_type'])->toBe(ExtractedDateType::Other);
    });
});

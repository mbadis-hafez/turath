<?php

use App\Enums\DocumentType;
use App\Support\Ocr\Extraction\DocumentFieldExtractor;

/** Printed lines, all on page 1; each string is one line of the given region. */
function printedLines(array $regions): array
{
    $lines = [];
    foreach ($regions as $regionId => $text) {
        foreach (explode("\n", $text) as $line) {
            $lines[] = ['page' => 1, 'region_id' => $regionId, 'text' => $line, 'confidence' => 88];
        }
    }

    return $lines;
}

function formPair(int $id, string $label, ?string $value, ?string $method = null, array $overrides = []): array
{
    return ['id' => $id, 'label' => $label, 'value' => $value, 'method' => $method, 'raw' => null, 'page' => 2, 'region_id' => $id + 100, 'crop_path' => "crops/{$id}.png", 'confidence' => 70, ...$overrides];
}

/** field key => list of values, in the order returned. */
function extracted(DocumentType $type, array $lines, array $formFields = []): array
{
    $values = [];
    foreach ((new DocumentFieldExtractor)->extract($type, $lines, $formFields) as $candidate) {
        $values[$candidate['field_key']][] = $candidate['value'];
    }

    return $values;
}

it('extracts nothing for a document of unknown type — there is no schema to fill', function () {
    expect((new DocumentFieldExtractor)->extract(DocumentType::Unknown, printedLines([1 => 'البريد الإلكتروني: a@b.example']), []))->toBe([]);
});

describe('artist authorization letter', function () {
    it('reads contact fields from their form labels, even OCR-mangled ones, preferring a transcription', function () {
        $found = (new DocumentFieldExtractor)->extract(DocumentType::ArtistAuthorization, [], [
            formPair(1, '3 الفنان/ة', 'سارة المثال', 'manually_transcribed'),
            formPair(2, 'دك الجوال', null),
            formPair(3, 'عنوان البريد الإلكتروني', 'artist@example.test', 'ocr_derived'),
        ]);
        $byKey = collect($found)->keyBy('field_key');

        expect($byKey['artist_name']['value'])->toBe('سارة المثال')
            ->and($byKey['artist_name']['method'])->toBe('manually_transcribed')
            ->and($byKey['artist_name']['confidence'])->toBe(100)
            ->and($byKey['email']['value'])->toBe('artist@example.test')
            ->and($byKey['email']['form_field_id'])->toBe(3)
            ->and($byKey['email']['crop_path'])->toBe('crops/3.png')
            // Handwriting nobody has transcribed yet: a field with no value, pointing at its crop.
            ->and($byKey['phone']['value'])->toBeNull()
            ->and($byKey['phone']['confidence'])->toBe(0)
            ->and($byKey['phone']['region_id'])->toBe(102)
            ->and($byKey->has('address'))->toBeFalse();
    });

    it("never takes the organisation's footer email or phone as the artist's", function () {
        $values = extracted(DocumentType::ArtistAuthorization, printedLines([
            1 => 'جمعية الثقافة والفنون',
            9 => 'للتواصل: info@gallery.example هاتف 0112345678',
        ]));

        expect($values)->not->toHaveKey('email')->and($values)->not->toHaveKey('phone');
    });

    it('records the authorization and consent statements as evidence', function () {
        $values = extracted(DocumentType::ArtistAuthorization, printedLines([
            1 => 'خطاب تفويض الفنانين',
            2 => "أنا الموقع أدناه أفوض الجمعية باستخدام أعمالي الفنية\nوأوافق على نشر الأعمال في المنصات الرقمية",
            3 => 'مع تسجيل حقوق الطبع والنشر باسم الفنان',
        ]));

        expect($values['authorization_statement'][0])->toContain('أفوض الجمعية')
            ->and($values['publication_consent'][0])->toContain('وأوافق على نشر')
            ->and($values['copyright_consent'][0])->toContain('حقوق الطبع');
    });
});

describe('artist biography', function () {
    it('takes lists under their headings, one item each, without bullets, numbering or duplicates', function () {
        $values = extracted(DocumentType::ArtistBiography, printedLines([
            1 => "التعليم:\n- معهد التربية الفنية، الرياض 1965\n- أكاديمية الفنون الجميلة، روما 1970",
            2 => "المعارض الفردية\n1. معرض الرياض الأول 1972\n٢) معرض جدة 1975\n1. معرض الرياض الأول 1972",
            3 => "Collections\n• King Fahd Museum",
        ]));

        expect($values['education'])->toBe(['معهد التربية الفنية، الرياض 1965', 'أكاديمية الفنون الجميلة، روما 1970'])
            ->and($values['exhibitions'])->toBe(['معرض الرياض الأول 1972', 'معرض جدة 1975'])
            ->and($values['collections'])->toBe(['King Fahd Museum']);
    });

    it('finds the artist, the birth place and the narrative biography', function () {
        $values = extracted(DocumentType::ArtistBiography, printedLines([
            1 => 'السيرة الذاتية للفنان محمد عبدالله السليم',
            2 => 'ولد الفنان في مدينة الرياض عام 1945م وتلقى تعليمه في معهد التربية الفنية، ثم سافر إلى إيطاليا لدراسة الفن وعاد ليؤسس أول صالة عرض في المملكة.',
        ]));

        expect($values['artist'])->toBe(['محمد عبدالله السليم'])
            ->and($values['birth_place'])->toBe(['الرياض'])
            ->and($values['biography'][0])->toStartWith('ولد الفنان في مدينة الرياض');
    });

    it('reads an English birth place', function () {
        expect(extracted(DocumentType::ArtistBiography, printedLines([1 => 'He was born in 1945 in Jeddah, where he later taught.']))['birth_place'])->toBe(['Jeddah']);
    });
});

describe('artwork condition report', function () {
    it('reads labelled values, with "العنوان" as the title rather than an address, and digits as printed', function () {
        $values = extracted(DocumentType::ArtworkConditionReport, printedLines([1 => "اسم الفنان: عبدالحليم رضوي\nالعنوان: صورة زيتية\nالخامة: زيت على قماش\nالأبعاد: ٥٠×٧٠ سم\nرقم الجرد: INV-204\nالحالة: جيدة مع تشققات بسيطة"]));

        expect($values)->toMatchArray([
            'artist' => ['عبدالحليم رضوي'], 'artwork' => ['INV-204'], 'artwork_title' => ['صورة زيتية'],
            'dimensions' => ['٥٠×٧٠ سم'], 'medium' => ['زيت على قماش'], 'condition' => ['جيدة مع تشققات بسيطة'],
        ]);
    });

    it('finds unlabelled dimensions by their shape, keeping the digits as written', function () {
        expect(extracted(DocumentType::ArtworkConditionReport, printedLines([1 => 'لوحة زيتية مقاسها ٦٠ × ٩٠ سم في إطار خشبي']))['dimensions'])->toBe(['٦٠ × ٩٠ سم']);
    });

    it('does not read a sentence that happens to contain a colon as a label', function () {
        expect(extracted(DocumentType::ArtworkConditionReport, printedLines([1 => 'لاحظ الفريق أثناء الفحص الدقيق للعمل الفني ما يلي: الخامة متشققة'])))->not->toHaveKey('medium');
    });
});

describe('exhibition document', function () {
    it('reads the title, venue, city and participating artists of an invitation', function () {
        $values = extracted(DocumentType::ExhibitionDocument, printedLines([
            1 => 'معرض الفن السعودي المعاصر',
            2 => 'يسر الجمعية دعوتكم لحضور الافتتاح في قاعة الأمير فيصل بمدينة الرياض',
            3 => "الفنانون المشاركون\nمحمد السليم\nصفية بن زقر",
        ]));

        expect($values['exhibition_title'])->toBe(['معرض الفن السعودي المعاصر'])
            ->and($values['venue'])->toBe(['قاعة الأمير فيصل'])
            ->and($values['city'])->toBe(['الرياض'])
            ->and($values['participating_artists'])->toBe(['محمد السليم', 'صفية بن زقر'])
            ->and($values)->not->toHaveKey('exhibition'); // the Event record itself is chosen by matching, not read
    });

    it('lets an empty form reading give way to a printed one', function () {
        $values = extracted(DocumentType::ExhibitionDocument, printedLines([1 => 'المدينة: جدة']), [formPair(1, 'المدينة', null)]);

        expect($values['city'])->toBe(['جدة']);
    });
});

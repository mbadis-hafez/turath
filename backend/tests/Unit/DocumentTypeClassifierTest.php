<?php

use App\Enums\DocumentType;
use App\Support\Ocr\DocumentTypeClassifier;

it('classifies an authorization letter from its own keywords', function () {
    $pagesByLanguage = [
        'ar' => [
            1 => ['text' => 'خطاب تفويض الفنانين. نطلب منك التكرم بتفويضنا باستخدام أعمالك الفنية.', 'confidence' => 90],
            2 => ['text' => 'أنا الموقع أدناه أفوض وزارة الثقافة. توصية بتسجيل حقوق الطبع والنشر.', 'confidence' => 85],
        ],
        'en' => [],
    ];

    expect((new DocumentTypeClassifier)->classify($pagesByLanguage))->toBe(DocumentType::ArtistAuthorization);
});

it('classifies a condition report from its own keywords', function () {
    $pagesByLanguage = ['ar' => [1 => ['text' => 'تقرير حالة العمل الفني. الأبعاد: ٥٠×٧٠ سم. الخامة: زيت على قماش. تمت أعمال الترميم.', 'confidence' => 80]], 'en' => []];

    expect((new DocumentTypeClassifier)->classify($pagesByLanguage))->toBe(DocumentType::ArtworkConditionReport);
});

it('falls back to unknown rather than guessing when too few keywords match', function () {
    $pagesByLanguage = ['ar' => [1 => ['text' => 'نص عام لا يحمل أي دلالة على نوع الوثيقة.', 'confidence' => 80]], 'en' => []];

    expect((new DocumentTypeClassifier)->classify($pagesByLanguage))->toBe(DocumentType::Unknown);
});

it('exposes a different field schema per document type', function () {
    expect(array_keys(DocumentType::ArtistAuthorization->fieldSchema()))->toContain('artist_email')
        ->and(array_keys(DocumentType::ArtworkConditionReport->fieldSchema()))->toContain('dimensions')
        ->and(DocumentType::Unknown->fieldSchema())->toBe([]);
});

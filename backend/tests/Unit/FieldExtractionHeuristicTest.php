<?php

use App\Support\Ocr\FieldExtractionHeuristic;

it('skips single-token garbage lines (logo/stamp misreads) when picking a title', function () {
    $heuristic = new FieldExtractionHeuristic;

    // "اللاالاك" is a real misread this heuristic produced from a decorative logo mark —
    // one glued token with no spaces is never a real title.
    $pages = ['ar' => [1 => ['text' => "اللاالاك\nالموضوع: بدايات الحركة الفنية في المملكة العربية السعودية", 'confidence' => 90]]];

    $fields = $heuristic->extract($pages);

    $title = collect($fields)->firstWhere('field_key', 'title_ar');
    expect($title['extracted_value'])->toBe('الموضوع: بدايات الحركة الفنية في المملكة العربية السعودية');
});

it('skips lines with almost no real letters, like bare dates or page numbers', function () {
    $heuristic = new FieldExtractionHeuristic;

    $pages = ['en' => [1 => ['text' => "m0)\n02 / 08 / 1445\nExhibition Opening 1979", 'confidence' => 90]]];

    $fields = $heuristic->extract($pages);

    $title = collect($fields)->firstWhere('field_key', 'title_en');
    expect($title['extracted_value'])->toBe('Exhibition Opening 1979');
});

it('yields no title when every line looks like noise', function () {
    $heuristic = new FieldExtractionHeuristic;

    $pages = ['ar' => [1 => ['text' => "اللاالاك\nم0)", 'confidence' => 90]]];

    $fields = $heuristic->extract($pages);

    expect(collect($fields)->firstWhere('field_key', 'title_ar'))->toBeNull();
});

<?php

use App\Support\Ocr\RegionClassifier;
use App\Support\Ocr\TesseractPageLayoutAnalyzer;

/** Invokes the private TSV grouping directly — analyze() itself needs a real tesseract binary. */
function layoutBlocksFromTsv(string $tsv): array
{
    $analyzer = new TesseractPageLayoutAnalyzer;
    $method = new ReflectionMethod($analyzer, 'groupIntoBlocks');
    $method->setAccessible(true);

    return $method->invoke($analyzer, $tsv);
}

it('strips the invisible bidi marks tesseract appends to Arabic words, so a trailing colon still reads as a label', function () {
    // Real tesseract output from the benchmark letter: an LRM (U+200E) right after the colon.
    $tsv = implode("\n", [
        "level\tpage_num\tblock_num\tpar_num\tline_num\tword_num\tleft\ttop\twidth\theight\tconf\ttext",
        "5\t1\t1\t1\t1\t1\t400\t100\t40\t30\t90\tاسم",
        "5\t1\t1\t1\t1\t2\t300\t100\t90\t30\t91\tالفنان/ة:\u{200E}",
        "5\t1\t1\t1\t1\t3\t100\t100\t150\t30\t30\tعبد",
    ]);

    $blocks = layoutBlocksFromTsv($tsv);

    expect($blocks[0]['words'][1]['text'])->toBe('الفنان/ة:')
        ->and($blocks[0]['text'])->not->toContain("\u{200E}");

    $regions = (new RegionClassifier)->classify($blocks, [], ['width' => 1000, 'height' => 1400]);
    expect(array_map(fn ($r) => $r['region_type']->value, $regions))->toBe(['form_label', 'handwriting']);
});

it('drops a word that is nothing but a bidi mark', function () {
    $tsv = implode("\n", [
        "level\tpage_num\tblock_num\tpar_num\tline_num\tword_num\tleft\ttop\twidth\theight\tconf\ttext",
        "5\t1\t1\t1\t1\t1\t0\t0\t10\t10\t90\t\u{200F}",
        "5\t1\t1\t1\t1\t2\t20\t0\t10\t10\t90\tنص",
    ]);

    expect(layoutBlocksFromTsv($tsv)[0]['word_count'])->toBe(1);
});

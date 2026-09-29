<?php

use App\Support\Ocr\TesseractOcrEngine;

/** Invokes the private TSV parser directly — recognize() itself needs a real tesseract binary. */
function parseTesseractTsv(string $tsv): array
{
    $engine = new TesseractOcrEngine;
    $method = new ReflectionMethod($engine, 'parseTsv');
    $method->setAccessible(true);

    return $method->invoke($engine, $tsv);
}

it('joins words on the same tesseract line with spaces, and separates lines with newlines', function () {
    // Columns: level  page_num  block_num  par_num  line_num  word_num  left  top  width  height  conf  text
    $tsv = implode("\n", [
        "level\tpage_num\tblock_num\tpar_num\tline_num\tword_num\tleft\ttop\twidth\theight\tconf\ttext",
        "5\t1\t1\t1\t1\t1\t0\t0\t10\t10\t90\tHello",
        "5\t1\t1\t1\t1\t2\t20\t0\t10\t10\t92\tworld",
        "5\t1\t1\t1\t2\t1\t0\t20\t10\t10\t88\tSecond",
        "5\t1\t1\t1\t2\t2\t20\t20\t10\t10\t85\tline",
    ]);

    $result = parseTesseractTsv($tsv);

    expect($result['text'])->toBe("Hello world\nSecond line");
    expect($result['segments'])->toHaveCount(4);
    expect($result['confidence'])->toBe((int) round((90 + 92 + 88 + 85) / 4));
});

it('skips non-word rows and words with no confidence', function () {
    $tsv = implode("\n", [
        "level\tpage_num\tblock_num\tpar_num\tline_num\tword_num\tleft\ttop\twidth\theight\tconf\ttext",
        "1\t1\t0\t0\t0\t0\t0\t0\t100\t100\t-1\t",
        "5\t1\t1\t1\t1\t1\t0\t0\t10\t10\t-1\t",
        "5\t1\t1\t1\t1\t2\t20\t0\t10\t10\t90\tOnly",
    ]);

    $result = parseTesseractTsv($tsv);

    expect($result['text'])->toBe('Only');
    expect($result['segments'])->toHaveCount(1);
});

<?php

use App\Support\Ocr\GdCorrectionMarkDetector;

/*
 * Synthetic pages drawn with GD — the real benchmark letter holds personal
 * data and stays out of the repo. Words are handwriting-like: a baseline
 * stroke with loops and ascenders, pen width 3, one text line ~48px tall
 * (the benchmark's handwriting at 200 dpi is ~52px).
 */

const MARK_WORD_HEIGHT = 48;

function markCanvas(int $width = 900, int $height = 200): GdImage
{
    $im = imagecreatetruecolor($width, $height);
    imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
    imagesetthickness($im, 3);

    return $im;
}

function markInk(GdImage $im): int
{
    return imagecolorallocate($im, 30, 30, 90);
}

/** A cursive-like word: baseline, three loops sitting on it, two ascenders. */
function drawHandWord(GdImage $im, int $x, int $y, int $width): void
{
    $ink = markInk($im);
    $base = $y + (int) (MARK_WORD_HEIGHT * 0.75);
    imageline($im, $x, $base, $x + $width, $base, $ink);
    foreach ([0.2, 0.5, 0.8] as $at) {
        imageellipse($im, $x + (int) ($width * $at), $base - 7, 14, 14, $ink);
    }
    foreach ([0.35, 0.65] as $at) {
        imageline($im, $x + (int) ($width * $at), $y, $x + (int) ($width * $at), $base, $ink);
    }
}

/** Crossed out the way the benchmark's email was: several stacked passes of the pen over the word. */
function drawScribble(GdImage $im, int $x, int $y, int $width): void
{
    $ink = markInk($im);
    imagesetthickness($im, 2);
    for ($pass = 0; $pass < 7; $pass++) {
        $py = $y + 8 + $pass * 5;
        imageline($im, $x - 4, $py + 3, $x + $width + 4, $py - 2, $ink);
    }
    imagesetthickness($im, 3);
}

/** @param  array<int, array{0: int, 1: int}>  $words  [x, width] pairs on one line at y=60 */
function lineOfWords(GdImage $im, array $words): void
{
    foreach ($words as [$x, $width]) {
        drawHandWord($im, $x, 60, $width);
    }
}

function savePage(GdImage $im): string
{
    $path = tempnam(sys_get_temp_dir(), 'marks').'.png';
    imagepng($im, $path);

    return $path;
}

function marksOn(GdImage $im, array $bbox = ['x' => 40, 'y' => 40, 'width' => 820, 'height' => 90]): array
{
    return (new GdCorrectionMarkDetector)->detect(savePage($im), [7 => $bbox]);
}

const MARK_WORDS = [[100, 110], [250, 140], [430, 90], [560, 160], [760, 80]];

it('finds nothing in ordinary handwriting', function () {
    $im = markCanvas();
    lineOfWords($im, MARK_WORDS);

    expect(marksOn($im))->toBe([]);
});

it('finds a crossed-out word, keyed to its region, and locates it on that word', function () {
    $im = markCanvas();
    lineOfWords($im, MARK_WORDS);
    drawScribble($im, 430, 60, 90);

    $marks = marksOn($im);

    expect($marks)->toHaveKey(7)
        ->and($marks[7])->toHaveCount(1)
        ->and($marks[7][0]['kind'])->toBe('scribble');
    $box = $marks[7][0]['bbox'];
    // Overlaps the struck word (x 430–520), not its neighbours' centres.
    expect($box['x'])->toBeLessThan(520)
        ->and($box['x'] + $box['width'])->toBeGreaterThan(430)
        ->and($box['x'])->toBeGreaterThan(300)
        ->and($box['x'] + $box['width'])->toBeLessThan(640);
});

it('finds a line struck through the middle of several words', function () {
    $im = markCanvas();
    lineOfWords($im, MARK_WORDS);
    // The letters' bodies span y 82–96; a strikethrough runs through their middle.
    imageline($im, 240, 89, 530, 89, markInk($im));

    $marks = marksOn($im);

    expect($marks[7] ?? [])->not->toBe([])
        ->and(array_column($marks[7], 'kind'))->toContain('line_strike');
    $strike = collect($marks[7])->firstWhere('kind', 'line_strike')['bbox'];
    expect($strike['x'])->toBeLessThanOrEqual(250)
        ->and($strike['x'] + $strike['width'])->toBeGreaterThanOrEqual(520);
});

it('documents a known limit: a stroke that only grazes the tops of the letters is not detected', function () {
    // Kept as a test so a future change in either direction is a deliberate one.
    $im = markCanvas();
    lineOfWords($im, MARK_WORDS);
    imageline($im, 240, 82, 530, 82, markInk($im));

    expect(array_column(marksOn($im)[7] ?? [], 'kind'))->not->toContain('line_strike');
});

it('does not mistake an underline beneath the words for a strike', function () {
    $im = markCanvas();
    lineOfWords($im, MARK_WORDS);
    imageline($im, 90, 60 + MARK_WORD_HEIGHT + 6, 850, 60 + MARK_WORD_HEIGHT + 6, markInk($im));

    expect(marksOn($im))->toBe([]);
});

it('does not mistake a dotted fill line for a strike', function () {
    $im = markCanvas();
    lineOfWords($im, [[100, 110], [700, 120]]);
    for ($x = 220; $x < 690; $x += 7) {
        imagefilledrectangle($im, $x, 94, $x + 2, 96, markInk($im));
    }

    expect(marksOn($im))->toBe([]);
});

it('does not mistake solid logo bars for a scribble', function () {
    $im = markCanvas();
    lineOfWords($im, [[100, 110], [250, 140]]);
    for ($x = 600; $x < 760; $x += 14) {
        imagefilledrectangle($im, $x, 55, $x + 8, 110, markInk($im));
    }

    expect(marksOn($im))->toBe([]);
});

it('ignores regions it cannot inspect', function () {
    $im = markCanvas();
    lineOfWords($im, MARK_WORDS);
    $path = savePage($im);
    $detector = new GdCorrectionMarkDetector;

    expect($detector->detect('/nonexistent/page.png', [['x' => 0, 'y' => 0, 'width' => 100, 'height' => 100]]))->toBe([])
        ->and($detector->detect($path, [['x' => 10, 'y' => 10, 'width' => 4, 'height' => 4]]))->toBe([])
        ->and($detector->detect($path, [['x' => 5000, 'y' => 5000, 'width' => 100, 'height' => 100]]))->toBe([])
        ->and($detector->detect($path, [['x' => 0, 'y' => 150, 'width' => 900, 'height' => 50]]))->toBe([]);
});

<?php

namespace App\Support\Ocr;

use GdImage;

/**
 * A coarse, dependency-free non-text region finder: lay a grid over the page,
 * sample brightness at each cell, skip cells already claimed by a text block,
 * and flood-fill the remaining non-white cells into clusters. This is not
 * object detection — it cannot tell a logo from a photograph by itself (that
 * classification, using position/size/complexity, happens in RegionClassifier)
 * — it only answers "where is there non-text ink on this page".
 */
class GdNonTextRegionDetector implements NonTextRegionDetector
{
    private const GRID_COLUMNS = 60;

    /** A sampled pixel below this brightness (0-255) is considered "ink", not blank page. */
    private const INK_THRESHOLD = 235;

    private const MIN_CLUSTER_CELLS = 2;

    public function detect(string $imagePath, array $occupiedBboxes): array
    {
        $image = $this->load($imagePath);
        if ($image === null) {
            return [];
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $cellSize = max(1, (int) round(max($width, $height) / self::GRID_COLUMNS));
        $cols = (int) ceil($width / $cellSize);
        $rows = (int) ceil($height / $cellSize);

        $ink = [];
        $brightness = [];
        for ($row = 0; $row < $rows; $row++) {
            for ($col = 0; $col < $cols; $col++) {
                $cx = min($width - 1, $col * $cellSize + intdiv($cellSize, 2));
                $cy = min($height - 1, $row * $cellSize + intdiv($cellSize, 2));

                if ($this->isInsideAny($cx, $cy, $occupiedBboxes)) {
                    continue;
                }

                $b = $this->sampleBrightness($image, $cx, $cy, $width, $height);
                $brightness["{$col},{$row}"] = $b;
                if ($b < self::INK_THRESHOLD) {
                    $ink["{$col},{$row}"] = true;
                }
            }
        }

        imagedestroy($image);

        $clusters = $this->clusterCells($ink, $cols, $rows);
        $pageArea = $width * $height;

        $regions = [];
        foreach ($clusters as $cells) {
            if (count($cells) < self::MIN_CLUSTER_CELLS) {
                continue;
            }
            $xs1 = $ys1 = [];
            $xs2 = $ys2 = [];
            $samples = [];
            foreach ($cells as $cell) {
                [$col, $row] = $cell;
                $xs1[] = $col * $cellSize;
                $ys1[] = $row * $cellSize;
                $xs2[] = min($width, ($col + 1) * $cellSize);
                $ys2[] = min($height, ($row + 1) * $cellSize);
                $samples[] = $brightness["{$col},{$row}"] ?? 255;
            }
            $x1 = min($xs1);
            $y1 = min($ys1);
            $x2 = max($xs2);
            $y2 = max($ys2);
            $bboxArea = ($x2 - $x1) * ($y2 - $y1);

            $regions[] = [
                'bbox' => ['x' => $x1, 'y' => $y1, 'width' => $x2 - $x1, 'height' => $y2 - $y1],
                'area_ratio' => $bboxArea / $pageArea,
                'complexity' => $this->stddev($samples),
            ];
        }

        return $regions;
    }

    private function load(string $imagePath): ?GdImage
    {
        $data = @file_get_contents($imagePath);
        if ($data === false) {
            return null;
        }
        $image = @imagecreatefromstring($data);

        return $image === false ? null : $image;
    }

    /**
     * @param  array<int, array{x: int, y: int, width: int, height: int}>  $bboxes
     */
    private function isInsideAny(int $x, int $y, array $bboxes): bool
    {
        foreach ($bboxes as $box) {
            if ($x >= $box['x'] && $x <= $box['x'] + $box['width'] && $y >= $box['y'] && $y <= $box['y'] + $box['height']) {
                return true;
            }
        }

        return false;
    }

    private function sampleBrightness(GdImage $image, int $x, int $y, int $width, int $height): float
    {
        $offsets = [[0, 0], [-2, -2], [2, -2], [-2, 2], [2, 2]];
        $total = 0.0;
        $count = 0;
        foreach ($offsets as [$dx, $dy]) {
            $sx = max(0, min($width - 1, $x + $dx));
            $sy = max(0, min($height - 1, $y + $dy));
            $rgb = imagecolorat($image, $sx, $sy);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            $total += (0.299 * $r + 0.587 * $g + 0.114 * $b);
            $count++;
        }

        return $total / $count;
    }

    /**
     * 4-connected flood fill over the sparse "ink" cell set.
     *
     * @param  array<string, bool>  $ink
     * @return array<int, array<int, array{0: int, 1: int}>>
     */
    private function clusterCells(array $ink, int $cols, int $rows): array
    {
        $visited = [];
        $clusters = [];

        foreach (array_keys($ink) as $key) {
            if (isset($visited[$key])) {
                continue;
            }
            $cluster = [];
            $queue = [$key];
            $visited[$key] = true;

            while ($queue !== []) {
                $current = array_pop($queue);
                [$col, $row] = array_map('intval', explode(',', $current));
                $cluster[] = [$col, $row];

                foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                    $nCol = $col + $dx;
                    $nRow = $row + $dy;
                    if ($nCol < 0 || $nRow < 0 || $nCol >= $cols || $nRow >= $rows) {
                        continue;
                    }
                    $nKey = "{$nCol},{$nRow}";
                    if (isset($ink[$nKey]) && ! isset($visited[$nKey])) {
                        $visited[$nKey] = true;
                        $queue[] = $nKey;
                    }
                }
            }

            $clusters[] = $cluster;
        }

        return $clusters;
    }

    /**
     * @param  array<int, float>  $values
     */
    private function stddev(array $values): float
    {
        $n = count($values);
        if ($n === 0) {
            return 0.0;
        }
        $mean = array_sum($values) / $n;
        $variance = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values)) / $n;

        return sqrt($variance);
    }
}

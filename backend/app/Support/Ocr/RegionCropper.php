<?php

namespace App\Support\Ocr;

/** Crops one region's bounding box out of a rendered page image, for regions a reviewer must look at directly (handwriting, signatures, unclassified regions). */
class RegionCropper
{
    private const PADDING_PX = 6;

    /**
     * @param  array{x: int, y: int, width: int, height: int}  $bbox
     * @return string|null raw PNG bytes, or null if the source image couldn't be read
     */
    public function crop(string $sourceImagePath, array $bbox): ?string
    {
        $data = @file_get_contents($sourceImagePath);
        if ($data === false) {
            return null;
        }
        $source = @imagecreatefromstring($data);
        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);

        $x = max(0, $bbox['x'] - self::PADDING_PX);
        $y = max(0, $bbox['y'] - self::PADDING_PX);
        $cropWidth = min($width - $x, $bbox['width'] + self::PADDING_PX * 2);
        $cropHeight = min($height - $y, $bbox['height'] + self::PADDING_PX * 2);

        if ($cropWidth <= 0 || $cropHeight <= 0) {
            imagedestroy($source);

            return null;
        }

        $cropped = imagecrop($source, ['x' => $x, 'y' => $y, 'width' => $cropWidth, 'height' => $cropHeight]);
        imagedestroy($source);
        if ($cropped === false) {
            return null;
        }

        ob_start();
        imagepng($cropped);
        $png = ob_get_clean();
        imagedestroy($cropped);

        return $png === false ? null : $png;
    }
}

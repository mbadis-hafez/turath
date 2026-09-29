<?php

namespace App\Support\Ocr;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Shells out to poppler-utils' `pdftoppm`. Requires that binary on the host
 * running the queue worker (`apt install poppler-utils` / `brew install poppler`)
 * — a deploy prerequisite, not something this class can install.
 */
class PopplerPdfPageRasterizer implements PdfPageRasterizer
{
    public function rasterize(string $pdfPath, string $outputDir): array
    {
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, recursive: true);
        }

        $prefix = $outputDir.'/page';
        $process = new Process(['pdftoppm', '-png', '-r', '200', $pdfPath, $prefix]);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $pages = [];
        foreach (glob($prefix.'-*.png') ?: [] as $path) {
            if (preg_match('/-(\d+)\.png$/', $path, $m)) {
                $pages[(int) $m[1]] = $path;
            }
        }
        ksort($pages);

        return array_values($pages);
    }
}

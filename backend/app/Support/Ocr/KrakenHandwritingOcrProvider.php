<?php

namespace App\Support\Ocr;

use Illuminate\Support\Facades\File as Filesystem;
use Symfony\Component\Process\Process;

/**
 * Shells out to a self-hosted `kraken` binary running a model trained for
 * historic handwritten Arabic (e.g. the open-source model trained on the
 * Muharaf dataset — see the 2026-09-30 architecture discussion for why a
 * self-hosted model was chosen over a cloud vision API: no data leaves the
 * host, at the cost of running/maintaining the model yourself).
 *
 * NOT bound by default (AppServiceProvider binds NullHandwritingOcrProvider).
 * To use this: install kraken + a compatible Arabic HTR model, set
 * KRAKEN_BINARY_PATH / KRAKEN_MODEL_PATH in .env, and rebind
 * HandwritingOcrProvider to this class.
 *
 * The exact `kraken` CLI invocation below has NOT been exercised against a
 * real installation in this environment — verify the flags against your
 * installed kraken version before relying on this in production. Whatever it
 * returns is a suggestion only: callers must keep requires_human_review=true
 * and never treat this as a verified transcription.
 */
class KrakenHandwritingOcrProvider implements HandwritingOcrProvider
{
    public function __construct(
        private readonly string $binaryPath = 'kraken',
        private readonly ?string $modelPath = null,
    ) {}

    public function suggest(string $cropImagePath, string $language): ?array
    {
        if ($this->modelPath === null) {
            return null;
        }

        $outputPath = tempnam(sys_get_temp_dir(), 'kraken-alto-').'.xml';

        try {
            $process = new Process([
                $this->binaryPath, '-i', $cropImagePath, $outputPath,
                '-a', 'segment', '-bl', 'ocr', '-m', $this->modelPath,
            ]);
            $process->setTimeout(60);
            $process->run();

            if (! $process->isSuccessful() || ! Filesystem::exists($outputPath)) {
                return null;
            }

            return $this->parseAlto(Filesystem::get($outputPath));
        } catch (\Throwable) {
            // A handwriting suggestion is a nice-to-have, never a hard dependency — any failure here
            // degrades to "no suggestion", the same as the null provider, not a pipeline failure.
            return null;
        } finally {
            if (Filesystem::exists($outputPath)) {
                Filesystem::delete($outputPath);
            }
        }
    }

    /**
     * @return array{text: string, confidence: int}|null
     */
    private function parseAlto(string $xml): ?array
    {
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        if ($doc === false) {
            return null;
        }

        $words = [];
        $confidences = [];
        foreach ($doc->xpath('//*[local-name()="String"]') ?: [] as $stringNode) {
            $content = (string) $stringNode['CONTENT'];
            if ($content === '') {
                continue;
            }
            $words[] = $content;
            $wc = (string) $stringNode['WC'];
            if ($wc !== '') {
                $confidences[] = (float) $wc;
            }
        }

        if ($words === []) {
            return null;
        }

        $confidence = $confidences === [] ? 50 : (int) round((array_sum($confidences) / count($confidences)) * 100);

        return ['text' => implode(' ', $words), 'confidence' => $confidence];
    }
}

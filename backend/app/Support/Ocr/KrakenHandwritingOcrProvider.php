<?php

namespace App\Support\Ocr;

use Illuminate\Support\Facades\File as Filesystem;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * A self-hosted kraken (https://kraken.re) recognizer — nothing leaves the
 * host. Verified on 2026-09-30 against kraken 7.1.1 with the Muharaf
 * recognition model (Zenodo 10.5281/zenodo.14295489, CC-BY-4.0, trained on
 * 1,600+ historic handwritten Arabic pages):
 *
 *   kraken -i line1.png out1.xml [-i line2.png out2.xml …] -a ocr -s --reorder --base-dir R -m <model>
 *
 * Why this invocation and not `segment -bl ocr`: kraken's baseline segmenter
 * is trained on whole pages and, on crop-sized images of the benchmark
 * letter, produced one-pixel "lines" and empty text. Crops are split into
 * lines here (CropLineSplitter) and each is recognized with segmentation off
 * (`-s`). `--base-dir R` is required for logical Arabic order: without it the
 * model's output came back character-reversed.
 *
 * What to expect (benchmark, 2026-09-30): partial readings of Arabic names and
 * places; nothing usable for Latin script (emails) or handwritten digits
 * (phones, dates) — the Muharaf model is Arabic-only. Word confidence (ALTO
 * WC) did not track correctness. Suggestions only, always.
 */
class KrakenHandwritingOcrProvider implements HandwritingOcrProvider
{
    private ?string $modelChecksum = null;

    public function __construct(
        private readonly string $modelPath,
        private readonly string $binaryPath = 'kraken',
        private readonly string $baseDir = 'R',
        private readonly int $timeoutSeconds = 120,
        private readonly CropLineSplitter $splitter = new CropLineSplitter,
    ) {}

    public function name(): string
    {
        return 'kraken';
    }

    public function model(): string
    {
        return basename($this->modelPath);
    }

    public function isExternal(): bool
    {
        return false;
    }

    public function suggest(string $cropImagePath, ?string $language): HandwritingSuggestion
    {
        $data = @file_get_contents($cropImagePath);
        $image = $data === false ? false : @imagecreatefromstring($data);
        if ($image === false) {
            throw new HandwritingOcrException("Could not read crop image {$cropImagePath}.", false);
        }

        $workDir = sys_get_temp_dir().'/kraken-'.bin2hex(random_bytes(6));
        Filesystem::ensureDirectoryExists($workDir);

        try {
            $args = [$this->binaryPath];
            $outputs = [];
            foreach ($this->splitter->split($image) as $i => $line) {
                $in = "{$workDir}/line-{$i}.png";
                imagepng($line, $in);
                $outputs[$i] = "{$workDir}/line-{$i}.xml";
                array_push($args, '-i', $in, $outputs[$i]);
            }
            array_push($args, '-a', 'ocr', '-s', '--reorder', '--base-dir', $this->baseDir, '-m', $this->modelPath);

            $process = new Process($args, timeout: $this->timeoutSeconds);
            try {
                $process->run();
            } catch (ProcessTimedOutException $e) {
                throw new HandwritingOcrException("kraken timed out after {$this->timeoutSeconds}s.", true, $e);
            }
            if (! $process->isSuccessful()) {
                throw new HandwritingOcrException('kraken failed: '.mb_substr(trim($process->getErrorOutput()), 0, 500), false);
            }

            $lines = [];
            foreach ($outputs as $output) {
                $lines[] = $this->parseAlto($output);
            }
        } finally {
            Filesystem::deleteDirectory($workDir);
        }

        $words = array_merge(...array_column($lines, 'words'));
        $confidences = array_values(array_filter(array_column($words, 'confidence'), fn ($c) => $c !== null));

        return new HandwritingSuggestion(
            text: trim(implode("\n", array_filter(array_column($lines, 'text'), fn (string $t) => $t !== ''))),
            confidence: $confidences === [] ? null : round(array_sum($confidences) / count($confidences), 3),
            modelVersion: $this->checksum(),
            raw: ['engine' => 'kraken', 'base_dir' => $this->baseDir, 'lines' => $lines],
        );
    }

    /**
     * @return array{text: string, words: array<int, array{text: string, confidence: float|null}>}
     */
    private function parseAlto(string $path): array
    {
        $xml = @file_get_contents($path);
        $doc = $xml === false ? false : @simplexml_load_string($xml);
        if ($doc === false) {
            throw new HandwritingOcrException("kraken wrote no readable output ({$path}).", false);
        }

        $words = [];
        foreach ($doc->xpath('//*[local-name()="String"]') ?: [] as $node) {
            $text = trim((string) $node['CONTENT']);
            if ($text === '') {
                continue;
            }
            $wc = (string) $node['WC'];
            $words[] = ['text' => $text, 'confidence' => $wc === '' ? null : (float) $wc];
        }

        return ['text' => implode(' ', array_column($words, 'text')), 'words' => $words];
    }

    /** A local model file has no version string; its checksum is its identity. */
    private function checksum(): ?string
    {
        if ($this->modelChecksum === null && is_file($this->modelPath)) {
            $this->modelChecksum = 'sha256:'.substr((string) hash_file('sha256', $this->modelPath), 0, 16);
        }

        return $this->modelChecksum;
    }
}

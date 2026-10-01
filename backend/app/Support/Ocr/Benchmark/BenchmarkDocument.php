<?php

namespace App\Support\Ocr\Benchmark;

/** One benchmark document and its known answers (see BenchmarkManifest). */
final class BenchmarkDocument
{
    /**
     * @param  array<mixed>  $expected
     */
    public function __construct(
        public readonly string $id,
        public readonly string $category,
        public readonly string $file,
        public readonly bool $containsPersonalData,
        public readonly array $expected,
    ) {}
}

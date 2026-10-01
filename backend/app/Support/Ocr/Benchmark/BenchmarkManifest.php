<?php

namespace App\Support\Ocr\Benchmark;

use InvalidArgumentException;

/**
 * The benchmark suite: local documents with known answers. A JSON file —
 * kept under storage/app (git-ignored) by default, because some benchmark
 * documents, like the authorization letter, carry personal data:
 *
 *     {"documents": [{
 *         "id": "authorization-letter-1",
 *         "category": "handwritten_form",
 *         "file": "letter.pdf",                      // relative to the manifest
 *         "contains_personal_data": true,
 *         "expected": {
 *             "document_type": "artist_authorization",
 *             "fields": {"authorization_statement": "…", "exhibitions": ["…", "…"]},
 *             "dates": [{"value": "…", "calendar": "hijri", "date_type": "document_issue_date"}],
 *             "pages": [{"page": 1, "language": "ar", "text": "…"}],
 *             "matches": [{"entity_type": "artist", "source_text": "…", "entity_id": 12}]
 *         }
 *     }]}
 *
 * Every "expected" part is optional; a document is scored on what it states.
 * "fields" is the complete answer for the fields it lists the document type
 * of — a value read for a field it doesn't list counts against precision.
 */
final class BenchmarkManifest
{
    /** What a complete suite covers (spec §17) — one document is not a benchmark. */
    public const CATEGORIES = [
        'clean_arabic_printed', 'arabic_english', 'handwritten_form', 'artist_biography', 'artwork_condition_report',
        'exhibition_catalogue', 'low_quality_scan', 'tables', 'images_captions', 'corrections_strikethrough',
    ];

    /**
     * @param  list<BenchmarkDocument>  $documents
     */
    private function __construct(public readonly string $path, public readonly array $documents) {}

    public static function load(string $path): self
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("No benchmark manifest at {$path}.");
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data) || ! is_array($data['documents'] ?? null)) {
            throw new InvalidArgumentException('The manifest must be a JSON object with a "documents" list.');
        }

        $documents = [];
        $seen = [];
        foreach ($data['documents'] as $i => $doc) {
            $where = "documents[{$i}]";
            if (! is_array($doc) || ! is_string($doc['id'] ?? null) || $doc['id'] === '') {
                throw new InvalidArgumentException("{$where} needs an id.");
            }
            if (isset($seen[$doc['id']])) {
                throw new InvalidArgumentException("Two documents are called \"{$doc['id']}\".");
            }
            $seen[$doc['id']] = true;
            if (! in_array($doc['category'] ?? null, self::CATEGORIES, true)) {
                throw new InvalidArgumentException("{$where} (\"{$doc['id']}\") needs a category: ".implode(', ', self::CATEGORIES).'.');
            }
            $file = is_string($doc['file'] ?? null) ? dirname($path).'/'.$doc['file'] : null;
            if ($file === null || ! is_file($file)) {
                throw new InvalidArgumentException("{$where} (\"{$doc['id']}\"): file not found next to the manifest.");
            }
            $expected = $doc['expected'] ?? [];
            if (! is_array($expected)) {
                throw new InvalidArgumentException("{$where} (\"{$doc['id']}\"): \"expected\" must be an object.");
            }

            $documents[] = new BenchmarkDocument(
                id: $doc['id'],
                category: $doc['category'],
                file: $file,
                containsPersonalData: ($doc['contains_personal_data'] ?? false) === true,
                expected: $expected,
            );
        }

        return new self($path, $documents);
    }

    /**
     * Categories with no document yet.
     *
     * @return list<string>
     */
    public function missingCategories(): array
    {
        $covered = array_unique(array_map(fn (BenchmarkDocument $d) => $d->category, $this->documents));

        return array_values(array_diff(self::CATEGORIES, $covered));
    }
}

<?php

namespace App\Support\Ocr\Extraction;

use App\Support\ArabicNormalizer;

/**
 * What DocumentFieldExtractor has found so far, in schema order. A
 * single-value field keeps its first reading — except that an empty one (a
 * form field whose handwriting isn't transcribed yet) gives way to any later
 * reading with a value. A list field keeps every distinct item.
 */
final class FoundFields
{
    private const MAX_LIST_ITEMS = 100;

    /** @var array<string, list<array{field_key: string, value: ?string, confidence: int, page: ?int, region_id: ?int, form_field_id: ?int, method: string, rule: string, original: ?string, crop_path: ?string}>> */
    private array $found = [];

    /**
     * @param  list<FieldDefinition>  $schema
     */
    public function __construct(private readonly array $schema) {}

    /**
     * @param  array{value: ?string, confidence: int, page: ?int, region_id: ?int, form_field_id: ?int, method: string, rule: string, original: ?string, crop_path: ?string}  $candidate
     */
    public function add(FieldDefinition $field, array $candidate): void
    {
        $candidate = ['field_key' => $field->key, ...$candidate];
        $existing = $this->found[$field->key] ?? [];

        if (! $field->isMultiple()) {
            if ($existing === [] || ($existing[0]['value'] === null && $candidate['value'] !== null)) {
                $this->found[$field->key] = [$candidate];
            }

            return;
        }

        if ($candidate['value'] === null || count($existing) >= self::MAX_LIST_ITEMS) {
            return;
        }
        $key = ArabicNormalizer::compact($candidate['value']);
        foreach ($existing as $item) {
            if (ArabicNormalizer::compact((string) $item['value']) === $key) {
                return;
            }
        }
        $this->found[$field->key][] = $candidate;
    }

    /** Whether the field has a reading with a value. */
    public function has(string $key): bool
    {
        foreach ($this->found[$key] ?? [] as $item) {
            if ($item['value'] !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{field_key: string, value: ?string, confidence: int, page: ?int, region_id: ?int, form_field_id: ?int, method: string, rule: string, original: ?string, crop_path: ?string}>
     */
    public function all(): array
    {
        $all = [];
        foreach ($this->schema as $field) {
            foreach ($this->found[$field->key] ?? [] as $item) {
                $all[] = $item;
            }
        }

        return $all;
    }
}

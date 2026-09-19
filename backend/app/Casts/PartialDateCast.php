<?php

namespace App\Casts;

use App\ValueObjects\PartialDate;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps a group of `{prefix}_*` columns onto a single PartialDate value object.
 * The cast is registered on a virtual attribute (e.g. "birth"); set() returns
 * the five underlying columns which Laravel merges into the raw attributes.
 *
 * @implements CastsAttributes<PartialDate|null, array<string, mixed>|PartialDate|null>
 */
class PartialDateCast implements CastsAttributes
{
    public function __construct(private string $prefix) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?PartialDate
    {
        $p = $this->prefix;

        return PartialDate::fromArray([
            'display' => $attributes["{$p}_date_display"] ?? null,
            'year_from' => $attributes["{$p}_year_from"] ?? null,
            'year_to' => $attributes["{$p}_year_to"] ?? null,
            'calendar' => $attributes["{$p}_calendar"] ?? null,
            'certainty' => $attributes["{$p}_certainty"] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $p = $this->prefix;

        if ($value === null) {
            return [
                "{$p}_date_display" => null,
                "{$p}_year_from" => null,
                "{$p}_year_to" => null,
                "{$p}_calendar" => 'gregorian',
                "{$p}_certainty" => 'unknown',
            ];
        }

        $data = $value instanceof PartialDate ? $value->toArray() : (array) $value;

        return [
            "{$p}_date_display" => $data['display'] ?? null,
            "{$p}_year_from" => $data['year_from'] ?? null,
            "{$p}_year_to" => $data['year_to'] ?? null,
            "{$p}_calendar" => $data['calendar'] ?? 'gregorian',
            "{$p}_certainty" => $data['certainty'] ?? 'unknown',
        ];
    }
}

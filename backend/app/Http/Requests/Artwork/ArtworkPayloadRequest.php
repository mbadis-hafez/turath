<?php

namespace App\Http\Requests\Artwork;

use App\Enums\AttributionCertainty;
use App\Enums\CalendarType;
use App\Enums\DateCertainty;
use App\Support\DimensionParser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

abstract class ArtworkPayloadRequest extends FormRequest
{
    /**
     * Map the validated nested payload onto flat model columns. Only keys
     * present in the input are returned (PATCH semantics).
     *
     * @return array<string, mixed>
     */
    public function mappedAttributes(): array
    {
        $v = $this->validated();

        $attributes = [];

        if (array_key_exists('artist_id', $v)) {
            $attributes['artist_id'] = $v['artist_id'];
        }

        if (array_key_exists('attribution_certainty', $v)) {
            $attributes['attribution_certainty'] = $v['attribution_certainty'];
        }

        if (array_key_exists('title', $v)) {
            $attributes['title_ar'] = $v['title']['ar'] ?? null;
            $attributes['title_en'] = $v['title']['en'] ?? null;
        }

        foreach (['is_untitled', 'category', 'edition_number', 'edition_size', 'signed', 'holder_id', 'holder_inventory_no', 'publication_status'] as $column) {
            if (array_key_exists($column, $v)) {
                $attributes[$column] = $v[$column];
            }
        }

        if (array_key_exists('medium', $v)) {
            $attributes['medium_ar'] = $v['medium']['ar'] ?? null;
            $attributes['medium_en'] = $v['medium']['en'] ?? null;
        }

        if (array_key_exists('notes', $v)) {
            $attributes['notes_ar'] = $v['notes']['ar'] ?? null;
            $attributes['notes_en'] = $v['notes']['en'] ?? null;
        }

        $this->mapDimensions($v, $attributes, 'dimensions', '');
        $this->mapDimensions($v, $attributes, 'frame_dimensions', 'frame_');

        if (array_key_exists('weight_kg', $v)) {
            $attributes['weight_kg'] = $v['weight_kg'];
        }

        if (array_key_exists('creation', $v)) {
            if ($v['creation'] === null) {
                $attributes['creation'] = null;
            } else {
                $attributes['creation_date_display'] = $v['creation']['display'] ?? null;
                $attributes['creation_year_from'] = $v['creation']['year_from'] ?? null;
                $attributes['creation_year_to'] = $v['creation']['year_to'] ?? null;
                $attributes['creation_calendar'] = $v['creation']['calendar'] ?? CalendarType::Gregorian->value;
                $attributes['creation_certainty'] = $v['creation']['certainty'] ?? DateCertainty::Unknown->value;
            }
        }

        return $attributes;
    }

    /**
     * Fills the structured height/width/depth columns for either the
     * artwork's own dimensions or its frame. If structured values are given
     * directly they win (an editor correcting a bad auto-parse); otherwise,
     * when a raw string is given, it's run through the DimensionParser.
     * When only structured values are given, the raw column is left
     * untouched so it keeps recording what was originally read from source.
     *
     * @param  array<string, mixed>  $v
     * @param  array<string, mixed>  $attributes
     */
    private function mapDimensions(array $v, array &$attributes, string $key, string $prefix): void
    {
        if (! array_key_exists($key, $v) || $v[$key] === null) {
            return;
        }

        $dim = $v[$key];
        $hasStructured = array_key_exists('height_cm', $dim) || array_key_exists('width_cm', $dim) || array_key_exists('depth_cm', $dim);

        if (array_key_exists('raw', $dim)) {
            $attributes["{$prefix}dimensions_raw"] = $dim['raw'];
        }

        if ($hasStructured) {
            $attributes["{$prefix}height_cm"] = $dim['height_cm'] ?? null;
            $attributes["{$prefix}width_cm"] = $dim['width_cm'] ?? null;
            $attributes["{$prefix}depth_cm"] = $dim['depth_cm'] ?? null;

            return;
        }

        if (array_key_exists('raw', $dim) && is_string($dim['raw']) && $dim['raw'] !== '') {
            $parsed = DimensionParser::parse($dim['raw']);
            $attributes["{$prefix}height_cm"] = $parsed->heightCm;
            $attributes["{$prefix}width_cm"] = $parsed->widthCm;
            $attributes["{$prefix}depth_cm"] = $parsed->depthCm;
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateAttribution($validator);
            $this->validateUntitled($validator);
        });
    }

    private function validateAttribution(Validator $validator): void
    {
        if (! $this->has('attribution_certainty') && ! $this->has('artist_id')) {
            return;
        }

        $certainty = $this->input('attribution_certainty');
        $artistId = $this->input('artist_id');

        if ($certainty === AttributionCertainty::Unattributed->value && $artistId !== null) {
            $validator->errors()->add('artist_id', 'The artist_id must be empty when attribution_certainty is unattributed.');
        }

        if ($certainty !== null && $certainty !== AttributionCertainty::Unattributed->value && $artistId === null) {
            $validator->errors()->add('artist_id', 'The artist_id field is required unless attribution_certainty is unattributed.');
        }
    }

    private function validateUntitled(Validator $validator): void
    {
        $isUntitled = $this->boolean('is_untitled');

        if ($isUntitled && (($this->input('title.ar') !== null) || ($this->input('title.en') !== null))) {
            $validator->errors()->add('title', 'The title must be empty when is_untitled is true.');
        }

        if (! $isUntitled && $this->requiresTitleWhenNotUntitled()
            && $this->input('title.ar') === null && $this->input('title.en') === null) {
            $validator->errors()->add('title', 'The title is required unless is_untitled is true.');
        }
    }

    protected function requiresTitleWhenNotUntitled(): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function titleRules(bool $partial): array
    {
        $presence = $partial ? ['sometimes'] : ['nullable'];

        return [
            'title' => array_merge($presence, ['array']),
            'title.ar' => ['nullable', 'string', 'max:255'],
            'title.en' => ['nullable', 'string', 'max:255'],
            'is_untitled' => array_merge($presence, ['boolean']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function dimensionsRules(string $key, bool $partial): array
    {
        $presence = $partial ? ['sometimes'] : ['nullable'];

        return [
            $key => array_merge($presence, ['nullable', 'array']),
            "{$key}.raw" => ['nullable', 'string', 'max:255'],
            "{$key}.height_cm" => ['nullable', 'numeric', 'min:0'],
            "{$key}.width_cm" => ['nullable', 'numeric', 'min:0'],
            "{$key}.depth_cm" => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function creationRules(bool $partial): array
    {
        $presence = $partial ? ['sometimes'] : ['nullable'];

        return [
            'creation' => array_merge($presence, ['nullable', 'array']),
            'creation.display' => ['nullable', 'string', 'max:100'],
            'creation.year_from' => ['nullable', 'integer'],
            'creation.year_to' => ['nullable', 'integer', 'gte:creation.year_from'],
            'creation.calendar' => ['nullable', new Enum(CalendarType::class)],
            'creation.certainty' => ['nullable', new Enum(DateCertainty::class)],
        ];
    }
}

<?php

namespace App\Http\Requests\Artist;

use App\Enums\CalendarType;
use App\Enums\DateCertainty;
use App\Enums\LivingStatus;
use App\Enums\PublicationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

abstract class ArtistPayloadRequest extends FormRequest
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

        if (array_key_exists('name', $v)) {
            $attributes['name_ar'] = $v['name']['ar'] ?? null;
            $attributes['name_en'] = $v['name']['en'] ?? null;
        }

        if (array_key_exists('bio', $v)) {
            $attributes['bio_ar'] = $v['bio']['ar'] ?? null;
            $attributes['bio_en'] = $v['bio']['en'] ?? null;
        }

        foreach (['nationality', 'classification'] as $group) {
            if (array_key_exists($group, $v)) {
                $attributes["{$group}_ar"] = $v[$group]['ar'] ?? null;
                $attributes["{$group}_en"] = $v[$group]['en'] ?? null;
            }
        }

        foreach (['birth', 'death'] as $prefix) {
            if (! array_key_exists($prefix, $v)) {
                continue;
            }

            if ($v[$prefix] === null) {
                $attributes[$prefix] = null;

                continue;
            }

            $attributes["{$prefix}_date_display"] = $v[$prefix]['display'] ?? null;
            $attributes["{$prefix}_year_from"] = $v[$prefix]['year_from'] ?? null;
            $attributes["{$prefix}_year_to"] = $v[$prefix]['year_to'] ?? null;
            $attributes["{$prefix}_calendar"] = $v[$prefix]['calendar'] ?? CalendarType::Gregorian->value;
            $attributes["{$prefix}_certainty"] = $v[$prefix]['certainty'] ?? DateCertainty::Unknown->value;
            $attributes["{$prefix}_place_ar"] = $v[$prefix]['place']['ar'] ?? null;
            $attributes["{$prefix}_place_en"] = $v[$prefix]['place']['en'] ?? null;
        }

        foreach (['living_status', 'legacy_code', 'publication_status'] as $column) {
            if (array_key_exists($column, $v)) {
                $attributes[$column] = $v[$column];
            }
        }

        return $attributes;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach (['birth', 'death'] as $prefix) {
                $this->validatePartialDate($validator, $prefix);
            }
        });
    }

    private function validatePartialDate(Validator $validator, string $prefix): void
    {
        $date = $this->input($prefix);

        if (! is_array($date)) {
            return;
        }

        $calendar = $date['calendar'] ?? null;
        $from = $date['year_from'] ?? null;
        $to = $date['year_to'] ?? null;
        $certainty = $date['certainty'] ?? null;

        if ($calendar !== null) {
            $min = $calendar === CalendarType::Hijri->value ? 1100 : 1700;
            $max = $calendar === CalendarType::Hijri->value ? 1500 : (int) date('Y');

            foreach (['year_from' => $from, 'year_to' => $to] as $field => $year) {
                if ($year !== null && ((int) $year < $min || (int) $year > $max)) {
                    $validator->errors()->add(
                        "{$prefix}.{$field}",
                        "The {$prefix} {$field} must be between {$min} and {$max} for the {$calendar} calendar.",
                    );
                }
            }
        }

        if ($from !== null && $to !== null && (int) $to < (int) $from) {
            $validator->errors()->add("{$prefix}.year_to", "The {$prefix} year to must be greater than or equal to year from.");
        }

        if ($certainty === DateCertainty::Exact->value && $from !== null && $to !== null && (int) $from !== (int) $to) {
            $validator->errors()->add("{$prefix}.certainty", "The {$prefix} certainty cannot be exact when the years differ.");
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function nameRules(bool $partial): array
    {
        $presence = $partial ? ['sometimes'] : ['nullable'];

        return [
            'name' => array_merge($presence, ['array']),
            'name.ar' => array_merge($partial ? ['nullable'] : ['nullable', 'required_without:name.en'], ['string', 'max:255', 'regex:/\p{Arabic}/u']),
            'name.en' => array_merge($partial ? ['nullable'] : ['nullable', 'required_without:name.ar'], ['string', 'max:255', 'regex:/\p{Latin}/u']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function bioRules(bool $partial): array
    {
        $presence = $partial ? ['sometimes'] : ['nullable'];

        return [
            'bio' => array_merge($presence, ['array']),
            'bio.ar' => ['nullable', 'string', 'max:20000'],
            'bio.en' => ['nullable', 'string', 'max:20000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function dateRules(string $prefix, bool $partial): array
    {
        $presence = $partial ? ['sometimes'] : ['nullable'];

        return [
            $prefix => array_merge($presence, ['array']),
            "{$prefix}.display" => ['nullable', 'string', 'max:100'],
            "{$prefix}.year_from" => ['nullable', 'integer'],
            "{$prefix}.year_to" => ['nullable', 'integer'],
            "{$prefix}.calendar" => ['nullable', new Enum(CalendarType::class)],
            "{$prefix}.certainty" => ['nullable', new Enum(DateCertainty::class)],
            "{$prefix}.place" => ['nullable', 'array'],
            "{$prefix}.place.ar" => ['nullable', 'string', 'max:255'],
            "{$prefix}.place.en" => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function statusRules(bool $partial): array
    {
        $presence = $partial ? ['sometimes'] : ['nullable'];

        return [
            'living_status' => array_merge($presence, [new Enum(LivingStatus::class)]),
            'publication_status' => array_merge($presence, [new Enum(PublicationStatus::class)]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function profileRules(bool $partial): array
    {
        $presence = $partial ? ['sometimes'] : ['nullable'];

        return [
            'nationality' => array_merge($presence, ['nullable', 'array']),
            'nationality.ar' => ['nullable', 'string', 'max:120'],
            'nationality.en' => ['nullable', 'string', 'max:120'],
            'classification' => array_merge($presence, ['nullable', 'array']),
            'classification.ar' => ['nullable', 'string', 'max:120'],
            'classification.en' => ['nullable', 'string', 'max:120'],
        ];
    }
}

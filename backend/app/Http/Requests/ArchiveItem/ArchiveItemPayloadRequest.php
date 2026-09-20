<?php

namespace App\Http\Requests\ArchiveItem;

use App\Enums\CalendarType;
use App\Enums\DateCertainty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

abstract class ArchiveItemPayloadRequest extends FormRequest
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

        if (array_key_exists('title', $v)) {
            $attributes['title_ar'] = $v['title']['ar'] ?? null;
            $attributes['title_en'] = $v['title']['en'] ?? null;
        }

        if (array_key_exists('description', $v)) {
            $attributes['description_ar'] = $v['description']['ar'] ?? null;
            $attributes['description_en'] = $v['description']['en'] ?? null;
        }

        if (array_key_exists('publication', $v)) {
            $attributes['publication_name_ar'] = $v['publication']['name']['ar'] ?? null;
            $attributes['publication_name_en'] = $v['publication']['name']['en'] ?? null;
            $attributes['issue_no'] = $v['publication']['issue_no'] ?? null;
            $attributes['page'] = $v['publication']['page'] ?? null;
        }

        if (array_key_exists('rights_holder', $v)) {
            $attributes['rights_holder_ar'] = $v['rights_holder']['ar'] ?? null;
            $attributes['rights_holder_en'] = $v['rights_holder']['en'] ?? null;
        }

        if (array_key_exists('place', $v)) {
            $attributes['place_ar'] = $v['place']['ar'] ?? null;
            $attributes['place_en'] = $v['place']['en'] ?? null;
        }

        foreach (['people_names', 'keywords'] as $list) {
            if (array_key_exists($list, $v)) {
                $attributes[$list] = array_values(array_filter(array_map(fn ($x) => trim((string) $x), $v[$list] ?? []), fn ($x) => $x !== '')) ?: null;
            }
        }

        foreach ([
            'source_name', 'verification_reference',
            'legacy_ref', 'parent_id', 'item_type', 'internal_notes', 'creator_name',
            'language', 'original_format', 'source_filename', 'quality_flag',
            'digitized_at', 'access_level', 'embargo_until', 'post_embargo_access_level',
            'rights_status', 'license', 'consent_status', 'publication_status',
        ] as $column) {
            if (array_key_exists($column, $v)) {
                $attributes[$column] = $v[$column];
            }
        }

        if (array_key_exists('content', $v)) {
            if ($v['content'] === null) {
                $attributes['content'] = null;
            } else {
                $attributes['content_date_display'] = $v['content']['display'] ?? null;
                $attributes['content_year_from'] = $v['content']['year_from'] ?? null;
                $attributes['content_year_to'] = $v['content']['year_to'] ?? null;
                $attributes['content_calendar'] = $v['content']['calendar'] ?? CalendarType::Gregorian->value;
                $attributes['content_certainty'] = $v['content']['certainty'] ?? DateCertainty::Unknown->value;
            }
        }

        return $attributes;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateEmbargo($validator);
            $this->validateNotBlank($validator);
        });
    }

    private function validateEmbargo(Validator $validator): void
    {
        if (! $this->has('access_level') && ! $this->has('embargo_until')) {
            return;
        }

        if ($this->input('access_level') === 'embargoed' && $this->input('embargo_until') === null) {
            $validator->errors()->add('embargo_until', 'The embargo_until field is required when access_level is embargoed.');
        }
    }

    private function validateNotBlank(Validator $validator): void
    {
        if ($this->isPartialUpdate() && ! $this->has('title') && ! $this->has('description')) {
            return;
        }

        $blank = $this->input('title.ar') === null && $this->input('title.en') === null
            && $this->input('description.ar') === null && $this->input('description.en') === null;

        if ($blank) {
            $validator->errors()->add('title', 'At least one of title or description is required.');
        }
    }

    protected function isPartialUpdate(): bool
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
            'title' => array_merge($presence, ['nullable', 'array']),
            'title.ar' => ['nullable', 'string', 'max:255'],
            'title.en' => ['nullable', 'string', 'max:255'],
            'description' => array_merge($presence, ['nullable', 'array']),
            'description.ar' => ['nullable', 'string', 'max:20000'],
            'description.en' => ['nullable', 'string', 'max:20000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function publicationRules(bool $partial): array
    {
        $presence = $partial ? ['sometimes'] : ['nullable'];

        return [
            'publication' => array_merge($presence, ['nullable', 'array']),
            'publication.name' => ['nullable', 'array'],
            'publication.name.ar' => ['nullable', 'string', 'max:255'],
            'publication.name.en' => ['nullable', 'string', 'max:255'],
            'publication.issue_no' => ['nullable', 'string', 'max:30'],
            'publication.page' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rightsHolderRules(bool $partial): array
    {
        $presence = $partial ? ['sometimes'] : ['nullable'];

        return [
            'rights_holder' => array_merge($presence, ['nullable', 'array']),
            'rights_holder.ar' => ['nullable', 'string', 'max:255'],
            'rights_holder.en' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function contentRules(bool $partial): array
    {
        $presence = $partial ? ['sometimes'] : ['nullable'];

        return [
            'content' => array_merge($presence, ['nullable', 'array']),
            'content.display' => ['nullable', 'string', 'max:100'],
            'content.year_from' => ['nullable', 'integer'],
            'content.year_to' => ['nullable', 'integer', 'gte:content.year_from'],
            'content.calendar' => ['nullable', new Enum(CalendarType::class)],
            'content.certainty' => ['nullable', new Enum(DateCertainty::class)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function profileRules(bool $partial): array
    {
        $presence = $partial ? ['sometimes'] : ['nullable'];

        return [
            'place' => array_merge($presence, ['nullable', 'array']),
            'place.ar' => ['nullable', 'string', 'max:255'],
            'place.en' => ['nullable', 'string', 'max:255'],
            'people_names' => array_merge($presence, ['nullable', 'array', 'max:100']),
            'people_names.*' => ['nullable', 'string', 'max:255'],
            'keywords' => array_merge($presence, ['nullable', 'array', 'max:50']),
            'keywords.*' => ['nullable', 'string', 'max:100'],
            'source_name' => array_merge($presence, ['nullable', 'string', 'max:255']),
            'verification_reference' => array_merge($presence, ['nullable', 'string', 'max:500']),
        ];
    }
}

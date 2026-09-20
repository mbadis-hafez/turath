<?php

namespace App\Http\Requests\Event;

use App\Enums\AccessLevel;
use App\Enums\CalendarType;
use App\Enums\DateCertainty;
use App\Enums\EventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

/** One request for create (POST) and edit (PATCH); PATCH only validates and maps the keys that were sent. */
class EventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    private function partial(): bool
    {
        return $this->isMethod('PATCH');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $p = $this->partial() ? ['sometimes'] : [];

        return [
            'event_type' => [...($this->partial() ? ['sometimes'] : ['required']), new Enum(EventType::class)],
            'title' => [...$p, 'nullable', 'array'],
            'title.ar' => ['nullable', 'string', 'max:255'],
            'title.en' => ['nullable', 'string', 'max:255'],
            'description' => [...$p, 'nullable', 'array'],
            'description.ar' => ['nullable', 'string', 'max:20000'],
            'description.en' => ['nullable', 'string', 'max:20000'],
            'venue_name' => [...$p, 'nullable', 'string', 'max:255'],
            'city' => [...$p, 'nullable', 'string', 'max:255'],
            'holder_id' => [...$p, 'nullable', 'integer', 'exists:holders,id'],
            'date_note' => [...$p, 'nullable', 'string', 'max:500'],
            'access_level' => [...$p, new Enum(AccessLevel::class)],
            'publication_status' => [...$p, 'string', 'in:draft,hidden'],
            'edit_summary' => ['nullable', 'string', 'max:255'],
            ...$this->dateRules('start', $p),
            ...$this->dateRules('end', $p),
        ];
    }

    /**
     * @param  array<int, string>  $p
     * @return array<string, mixed>
     */
    private function dateRules(string $key, array $p): array
    {
        return [
            $key => [...$p, 'nullable', 'array'],
            "{$key}.display" => ['nullable', 'string', 'max:100'],
            "{$key}.year_from" => ['nullable', 'integer'],
            "{$key}.year_to" => ['nullable', 'integer', "gte:{$key}.year_from"],
            "{$key}.calendar" => ['nullable', new Enum(CalendarType::class)],
            "{$key}.certainty" => ['nullable', new Enum(DateCertainty::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($this->partial() && ! $this->has('title')) {
                return;
            }
            if ($this->input('title.ar') === null && $this->input('title.en') === null) {
                $v->errors()->add('title', 'A title in Arabic or English is required.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function mappedAttributes(): array
    {
        $v = $this->validated();
        $out = [];

        if (array_key_exists('title', $v)) {
            $out['title_ar'] = $v['title']['ar'] ?? null;
            $out['title_en'] = $v['title']['en'] ?? null;
        }
        if (array_key_exists('description', $v)) {
            $out['description_ar'] = $v['description']['ar'] ?? null;
            $out['description_en'] = $v['description']['en'] ?? null;
        }
        foreach (['event_type', 'venue_name', 'city', 'holder_id', 'date_note', 'access_level', 'publication_status'] as $column) {
            if (array_key_exists($column, $v)) {
                $out[$column] = $v[$column];
            }
        }
        foreach (['start', 'end'] as $key) {
            if (! array_key_exists($key, $v)) {
                continue;
            }
            if ($v[$key] === null) {
                $out[$key] = null;

                continue;
            }
            $out["{$key}_date_display"] = $v[$key]['display'] ?? null;
            $out["{$key}_year_from"] = $v[$key]['year_from'] ?? null;
            $out["{$key}_year_to"] = $v[$key]['year_to'] ?? null;
            $out["{$key}_calendar"] = $v[$key]['calendar'] ?? CalendarType::Gregorian->value;
            $out["{$key}_certainty"] = $v[$key]['certainty'] ?? DateCertainty::Unknown->value;
        }

        return $out;
    }
}

<?php

namespace App\Http\Requests\Artist;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncArtistEntriesRequest extends FormRequest
{
    public const GROUPS = ['educations' => ['education'], 'activities' => ['award', 'exhibition', 'talk', 'symposium']];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = ['edit_summary' => ['nullable', 'string', 'max:255']];
        foreach (array_keys(self::GROUPS) as $group) {
            $rules[$group] = ['sometimes', 'array', 'max:100'];
            $rules["{$group}.*.id"] = ['nullable', 'integer'];
            $rules["{$group}.*.type"] = $group === 'activities' ? ['required', Rule::in(self::GROUPS['activities'])] : ['nullable'];
            $rules["{$group}.*.title.ar"] = ['nullable', 'string', 'max:255'];
            $rules["{$group}.*.title.en"] = ['nullable', 'string', 'max:255'];
            $rules["{$group}.*.place.ar"] = ['nullable', 'string', 'max:255'];
            $rules["{$group}.*.place.en"] = ['nullable', 'string', 'max:255'];
            $rules["{$group}.*.year_from"] = ['nullable', 'integer', 'between:1000,2100'];
            $rules["{$group}.*.year_to"] = ['nullable', 'integer', 'between:1000,2100', "gte:{$group}.*.year_from"];
            $rules["{$group}.*.note.ar"] = ['nullable', 'string', 'max:2000'];
            $rules["{$group}.*.note.en"] = ['nullable', 'string', 'max:2000'];
        }

        return $rules;
    }
}

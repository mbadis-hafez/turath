<?php

namespace App\Http\Requests\Holder;

use App\Enums\HolderType;
use Illuminate\Foundation\Http\FormRequest;

abstract class HolderPayloadRequest extends FormRequest
{
    /**
     * Map the validated nested payload onto flat model columns, and fill
     * the is_public_name default from the holder type when the client
     * didn't specify it: institutions and estates default public,
     * private collectors and families default private. Only keys present
     * in the input are returned (PATCH semantics).
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

        if (array_key_exists('city', $v)) {
            $attributes['city_ar'] = $v['city']['ar'] ?? null;
            $attributes['city_en'] = $v['city']['en'] ?? null;
        }

        if (array_key_exists('country', $v)) {
            $attributes['country_ar'] = $v['country']['ar'] ?? null;
            $attributes['country_en'] = $v['country']['en'] ?? null;
        }

        foreach (['type', 'legacy_code', 'is_estate', 'internal_notes'] as $column) {
            if (array_key_exists($column, $v)) {
                $attributes[$column] = $v[$column];
            }
        }

        if (array_key_exists('is_public_name', $v)) {
            $attributes['is_public_name'] = $v['is_public_name'];
        } elseif (array_key_exists('type', $v)) {
            $attributes['is_public_name'] = in_array($v['type'], [HolderType::Institution->value, HolderType::ArtistEstate->value], true);
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    protected function nameRules(bool $partial): array
    {
        $presence = $partial ? ['sometimes'] : ['nullable'];

        return [
            'name' => array_merge($presence, ['array']),
            'name.ar' => array_merge($partial ? ['nullable'] : ['nullable', 'required_without:name.en'], ['string', 'max:255']),
            'name.en' => array_merge($partial ? ['nullable'] : ['nullable', 'required_without:name.ar'], ['string', 'max:255']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function placeRules(string $key, bool $partial): array
    {
        $presence = $partial ? ['sometimes'] : ['nullable'];

        return [
            $key => array_merge($presence, ['array']),
            "{$key}.ar" => ['nullable', 'string', 'max:255'],
            "{$key}.en" => ['nullable', 'string', 'max:255'],
        ];
    }
}

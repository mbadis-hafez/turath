<?php

namespace App\Http\Resources;

use App\Models\Holder;
use App\Support\HolderDisplayResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HolderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Holder $holder */
        $holder = $this->resource;

        $data = [
            'id' => $holder->id,
            'type' => $holder->type,
            'name' => HolderDisplayResolver::resolve($holder),
            'city' => [
                'ar' => $holder->city_ar,
                'en' => $holder->city_en,
            ],
            'country' => [
                'ar' => $holder->country_ar,
                'en' => $holder->country_en,
            ],
        ];

        // The real private name and internal notes are never public, even
        // in aggregate — only visible to editors managing the record.
        if ($request->user()?->can('holders.manage') ?? false) {
            $data['legacy_code'] = $holder->legacy_code;
            $data['is_public_name'] = $holder->is_public_name;
            $data['is_estate'] = $holder->is_estate;
            $data['real_name'] = [
                'ar' => $holder->name_ar,
                'en' => $holder->name_en,
            ];
            $data['internal_notes'] = $holder->internal_notes;
            $data['created_at'] = $holder->created_at?->toIso8601String();
            $data['updated_at'] = $holder->updated_at?->toIso8601String();
        }

        return $data;
    }
}

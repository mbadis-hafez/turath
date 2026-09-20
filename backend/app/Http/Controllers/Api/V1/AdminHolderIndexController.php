<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Holder;
use App\Support\ArabicNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminHolderIndexController
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = Holder::query();

        if ($q = $request->input('q')) {
            $needle = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_substr($q, 0, 100)).'%';
            $normalized = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], ArabicNormalizer::normalize(mb_substr($q, 0, 100))).'%';
            $query->where(fn ($w) => $w->where('name_en', 'like', $needle)->orWhere('name_ar', 'like', $needle)->orWhere('name_ar', 'like', $normalized));
        }

        return response()->json(['data' => $query->orderBy('name_en')->orderBy('name_ar')->limit(15)->get()->map(fn (Holder $h) => [
            'id' => $h->id,
            'name' => ['ar' => $h->name_ar, 'en' => $h->name_en],
        ])->values()]);
    }
}

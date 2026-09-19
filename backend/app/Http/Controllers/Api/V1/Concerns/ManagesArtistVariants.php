<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\Artist;
use Illuminate\Validation\ValidationException;

trait ManagesArtistVariants
{
    private function ensureVariantNameUnique(Artist $artist, string $name, ?int $ignoreId = null): void
    {
        $query = $artist->variants()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'name' => [__('validation.unique', ['attribute' => 'name'])],
            ]);
        }
    }

    private function detectVariantLanguage(string $name): string
    {
        return preg_match('/\p{Arabic}/u', $name) ? 'ar' : 'en';
    }
}

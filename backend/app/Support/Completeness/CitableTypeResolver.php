<?php

namespace App\Support\Completeness;

use Illuminate\Database\Eloquent\Model;

class CitableTypeResolver
{
    /**
     * @return array{model: class-string<Model>, manage_permission: string}|null
     */
    public static function forSegment(string $segment): ?array
    {
        return config("completeness.types.{$segment}");
    }

    public static function segmentFor(string $modelClass): ?string
    {
        foreach (config('completeness.types', []) as $segment => $entry) {
            if ($entry['model'] === $modelClass) {
                return $segment;
            }
        }

        return null;
    }

    public static function resolveRecord(string $segment, int|string $id): ?Model
    {
        $entry = self::forSegment($segment);

        if ($entry === null) {
            return null;
        }

        return $entry['model']::query()->find($id);
    }
}

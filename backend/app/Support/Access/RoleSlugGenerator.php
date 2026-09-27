<?php

namespace App\Support\Access;

use App\Models\Role;
use Illuminate\Support\Str;

/**
 * Derives the immutable `roles.name` identifier from a custom role's English
 * display name. Unlike ArtistSlugGenerator, this never silently
 * disambiguates a collision with a numeric suffix — FR-016 requires a
 * colliding name be reported to the administrator, not accepted as a
 * different-looking duplicate.
 */
class RoleSlugGenerator
{
    public static function generate(string $nameEn): string
    {
        return Str::slug($nameEn, '_');
    }

    public static function exists(string $slug): bool
    {
        return Role::where('name', $slug)->exists();
    }
}

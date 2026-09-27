<?php

namespace App\Models;

use App\Concerns\LogsChanges;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Support\Config;

/**
 * Extends the package model so roles carry bilingual display names and the
 * built-in/custom distinction this feature adds. `name` stays the immutable
 * identifier every `hasRole()`/`can:`/`syncRoles()` call site matches on;
 * `name_ar`/`name_en` are the only user-editable name fields.
 *
 * Spatie\Permission\Models\Role sets $table dynamically in its constructor
 * (from config, not a static property), so Larastan's model reflection can't
 * see the roles table's columns on its own — these @property tags are that
 * information, not a suppression.
 *
 * @property string|null $name_ar
 * @property string|null $name_en
 * @property string|null $description_ar
 * @property string|null $description_en
 * @property bool $is_built_in
 */
class Role extends SpatieRole
{
    use LogsChanges;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_built_in' => 'boolean'];
    }

    /**
     * Overrides Spatie's implementation, which resolves the user model via
     * `getModelForGuard($this->attributes['guard_name'] ?? config('auth.defaults.guard'))`.
     * Sanctum's `auth:sanctum` middleware calls `Auth::shouldUse('sanctum')`,
     * which mutates `auth.defaults.guard` to 'sanctum' for the rest of the
     * request — so any *fresh* Role instance built mid-request (e.g. the
     * query builder's template model for `withCount`) picks up
     * `guard_name = 'sanctum'` instead of 'web', and 'sanctum' has no
     * configured provider model, so relation-building throws. This app has
     * exactly one user-provider model, so hardcoding it sidesteps the
     * guard-name lookup entirely rather than working around Sanctum's
     * runtime config mutation.
     *
     * @return MorphToMany<User, $this, MorphPivot, 'pivot'>
     */
    public function users(): MorphToMany
    {
        return $this->morphedByMany(
            User::class,
            'model',
            Config::modelHasRolesTable(),
            app(PermissionRegistrar::class)->pivotRole,
            Config::morphKey(),
        );
    }

    public function activitySubjectLabel(): string
    {
        return $this->name_en ?? $this->name;
    }

    /**
     * @return array<string, array{ar: string, en: string}>
     */
    public static function activityFieldLabels(): array
    {
        return [
            'name_ar' => ['ar' => 'الاسم (عربي)', 'en' => 'Name (Arabic)'],
            'name_en' => ['ar' => 'الاسم (إنجليزي)', 'en' => 'Name (English)'],
            'description_ar' => ['ar' => 'الوصف (عربي)', 'en' => 'Description (Arabic)'],
            'description_en' => ['ar' => 'الوصف (إنجليزي)', 'en' => 'Description (English)'],
            'is_built_in' => ['ar' => 'دور أساسي', 'en' => 'Built-in role'],
        ];
    }
}

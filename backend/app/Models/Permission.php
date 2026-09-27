<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Extends the package model so permissions carry bilingual labels and a
 * display group. No LogsChanges: the catalogue is code-defined and never
 * edited at runtime (FR-022) — nothing here ever changes after the seeder
 * creates it, so there is nothing to audit.
 *
 * @property string|null $label_ar
 * @property string|null $label_en
 * @property string|null $group
 */
class Permission extends SpatiePermission
{
    //
}

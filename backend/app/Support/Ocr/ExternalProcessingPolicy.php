<?php

namespace App\Support\Ocr;

use App\Enums\AccessLevel;
use App\Models\File;

/**
 * The one rule for sending archive material to a provider outside our
 * infrastructure, shared by AI correction and handwriting suggestions: it
 * must be explicitly allowed, and the material's access level must be at or
 * below the configured ceiling. Embargoed material never leaves, whatever
 * the ceiling.
 */
class ExternalProcessingPolicy
{
    /**
     * @param  string  $configKey  e.g. "ocr.correction", holding allow_external_providers and external_max_access_level
     */
    public static function refusalFor(File $file, string $configKey): ?string
    {
        if (! config("{$configKey}.allow_external_providers")) {
            return 'external_provider_not_allowed';
        }

        $level = AccessLevel::tryFrom((string) $file->archiveItem?->access_level);
        $ceiling = AccessLevel::tryFrom((string) config("{$configKey}.external_max_access_level")) ?? AccessLevel::Public;
        if ($level === null || $level === AccessLevel::Embargoed || $level->rank() > $ceiling->rank()) {
            return 'access_level_not_allowed';
        }

        return null;
    }
}

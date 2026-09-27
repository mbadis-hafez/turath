<?php

namespace App\Support\Completeness;

/**
 * Optional extension of CompletenessRules for entities whose fields are
 * grouped into display sections (identity / biography / media …). The API
 * layer uses it to enrich completeness payloads with per-section
 * percentages; rule sets that don't implement it simply don't get the
 * section breakdown.
 */
interface SectionedCompletenessRules extends CompletenessRules
{
    /** The display section a field key belongs to (e.g. 'identity'). */
    public function fieldSection(string $fieldKey): string;
}

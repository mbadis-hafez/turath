<?php

namespace App\Support\Completeness;

use Illuminate\Database\Eloquent\Model;

/**
 * Declarative per-entity rule set consumed by CompletenessCalculator (D47).
 * Core fields block publish when missing; important fields only count
 * against the percentage.
 */
interface CompletenessRules
{
    /**
     * @return array<string, array{requires_citation: bool, citation_field_key?: string, any_citation?: bool}>
     */
    public function coreFields(): array;

    /**
     * @return array<int, string>
     */
    public function importantFields(): array;

    /** Column-level presence check for one field key — citation requirements are layered on top by the calculator. */
    public function isFieldPresent(Model $record, string $fieldKey): bool;

    /** Lets a rule exempt a specific record from an otherwise-required citation (e.g. a living artist doesn't need a death-year citation). */
    public function citationExempt(Model $record, string $fieldKey): bool;

    /**
     * @return array{ar: string, en: string}
     */
    public function fieldLabel(string $fieldKey): array;
}

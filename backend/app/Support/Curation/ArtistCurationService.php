<?php

namespace App\Support\Curation;

use App\Models\Artist;
use App\Models\FieldCitation;
use App\Support\Completeness\ArtistCompletenessRules;
use App\Support\Completeness\CompletenessCalculator;

class ArtistCurationService
{
    /**
     * D96: verification needs the complete 11-field profile plus the two
     * documentation stages, and — for deceased artists with a death year —
     * a death-year citation (citations moved out of completeness into
     * verification).
     *
     * @return array<string, array<int, string>>
     */
    public function verifyErrors(Artist $artist): array
    {
        $errors = [];
        $calculator = new CompletenessCalculator;
        $rules = $calculator->rulesFor(Artist::class);

        foreach ($calculator->evaluate($artist)['blocking'] as $key) {
            $errors["data.{$key}"] = ["Missing required field: {$rules->fieldLabel($key)['en']}."];
        }

        if ($artist->living_status === 'deceased'
            && $artist->getAttribute('death_year_from') !== null
            && ! FieldCitation::where('citable_type', Artist::class)->where('citable_id', $artist->id)->where('field_key', 'death_year')->exists()) {
            $errors['verification.death_year_citation'] = ['A death-year citation is required for a deceased artist.'];
        }

        if (! in_array($artist->authorization_letter_status, ['signed', 'not_applicable'], true)) {
            $errors['pipeline.authorization_letter'] = ["Authorization letter is {$artist->authorization_letter_status}."];
        }

        if (! in_array($artist->owner_pre_agreement_status, ['yes', 'not_applicable'], true)) {
            $errors['pipeline.owner_pre_agreement'] = ["Owner pre-agreement is {$artist->owner_pre_agreement_status}."];
        }

        return $errors;
    }

    /**
     * Read-side preview of ArtistUpdateController's publish gate (D48): a
     * creation-review approval plus no blocking completeness gap. Mirrors
     * CreationReviewGate::assertApproved and PublishGate::assertPublishable
     * without throwing, so the curation page can show why publishing is
     * blocked before the user attempts it — the write-time gates remain the
     * actual enforcement.
     *
     * @return array<string, array<int, string>>
     */
    public function publishErrors(Artist $artist): array
    {
        $errors = [];

        if ($artist->creation_approved_at === null) {
            $errors['completeness.creation_review'] = ["This record hasn't been reviewed yet."];
        }

        $calculator = new CompletenessCalculator;
        $rules = $calculator->rulesFor(Artist::class);

        foreach ($calculator->evaluate($artist)['blocking'] as $key) {
            $errors["data.{$key}"] = ["Missing required field: {$rules->fieldLabel($key)['en']}."];
        }

        return $errors;
    }

    /** "ظهور عام": computed live from the same check, never stored (D96). */
    public function publicVisibility(Artist $artist): string
    {
        return $this->verifyErrors($artist) === [] ? 'visible' : 'hidden';
    }

    /**
     * The 11-item profile checklist (all core/blocking), grouped by
     * section. Administrative requirements (contact, authorization
     * letter, owner pre-agreement) live in adminChecklist(), not here.
     *
     * @return array<int, array{key: string, tier: string, met: bool, supported: bool, section: string}>
     */
    public function checklist(Artist $artist): array
    {
        $rules = new ArtistCompletenessRules;

        return array_map(fn (string $key) => [
            'key' => $key,
            'tier' => 'core',
            'met' => $rules->isFieldPresent($artist, $key),
            'supported' => true,
            'section' => $rules->fieldSection($key),
        ], array_keys($rules->coreFields()));
    }

    /**
     * The administrative requirements on the curation screen — ownership
     * and documentation, deliberately outside the profile completeness
     * percentage.
     *
     * @return array<int, array{key: string, tier: string, met: bool, supported: bool}>
     */
    public function adminChecklist(Artist $artist): array
    {
        return [
            [
                'key' => 'contact',
                'tier' => 'administrative',
                'met' => $artist->contacts->contains(fn ($c) => $c->name !== null && ($c->email !== null || $c->phone !== null)),
                'supported' => true,
            ],
            [
                'key' => 'authorization_letter',
                'tier' => 'administrative',
                'met' => in_array($artist->authorization_letter_status, ['signed', 'not_applicable'], true),
                'supported' => true,
            ],
            [
                'key' => 'owner_pre_agreement',
                'tier' => 'administrative',
                'met' => in_array($artist->owner_pre_agreement_status, ['yes', 'not_applicable'], true),
                'supported' => true,
            ],
        ];
    }
}

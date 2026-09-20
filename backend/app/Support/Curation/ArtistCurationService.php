<?php

namespace App\Support\Curation;

use App\Models\Artist;
use App\Models\FieldCitation;
use App\Support\Completeness\CompletenessCalculator;

class ArtistCurationService
{
    /**
     * D96: verification needs F10 completeness plus the two documentation
     * stages. One itemized list, shared with the checklist and visibility.
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

        if (! in_array($artist->authorization_letter_status, ['signed', 'not_applicable'], true)) {
            $errors['pipeline.authorization_letter'] = ["Authorization letter is {$artist->authorization_letter_status}."];
        }

        if (! in_array($artist->owner_pre_agreement_status, ['yes', 'not_applicable'], true)) {
            $errors['pipeline.owner_pre_agreement'] = ["Owner pre-agreement is {$artist->owner_pre_agreement_status}."];
        }

        return $errors;
    }

    /** "ظهور عام": computed live from the same check, never stored (D96). */
    public function publicVisibility(Artist $artist): string
    {
        return $this->verifyErrors($artist) === [] ? 'visible' : 'hidden';
    }

    /**
     * D99: the eight mockup checklist fields (the portrait needs an uploaded
     * image with rights other than `unknown`).
     *
     * @return array<int, array{key: string, tier: string, met: bool, supported: bool}>
     */
    public function checklist(Artist $artist): array
    {
        $blocking = (new CompletenessCalculator)->evaluate($artist)['blocking'];
        $nameCited = FieldCitation::where('citable_type', Artist::class)->where('citable_id', $artist->id)->where('field_key', 'name')->exists();

        $items = [
            ['name', 'core', $artist->name_ar !== null && $artist->name_en !== null],
            ['artist_code', 'core', $artist->legacy_code !== null],
            ['city', 'important', $artist->birth_place_ar !== null || $artist->birth_place_en !== null],
            ['contact', 'core', $artist->contacts->contains(fn ($c) => $c->name !== null && ($c->email !== null || $c->phone !== null))],
            ['authorization_letter', 'core', in_array($artist->authorization_letter_status, ['signed', 'not_applicable'], true)],
            ['name_verified', 'core', $nameCited],
            ['life_dates', 'core', ! in_array('death_year_or_living_confirmed', $blocking, true)],
        ];

        $result = array_map(fn (array $i) => ['key' => $i[0], 'tier' => $i[1], 'met' => $i[2], 'supported' => true], $items);
        $result[] = ['key' => 'portrait', 'tier' => 'important', 'met' => in_array('portrait_with_clear_rights', (new CompletenessCalculator)->evaluate($artist)['minor'], true) === false, 'supported' => true];

        return $result;
    }
}

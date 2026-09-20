<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\File;
use App\Models\RecordCompleteness;
use App\Support\Curation\ArtistCurationService;
use App\ValueObjects\PartialDate;
use Illuminate\Http\JsonResponse;

class ArtistCurationShowController
{
    public function __invoke(Artist $artist): JsonResponse
    {
        $service = new ArtistCurationService;

        $materials = $artist->archiveItemLinks()->with('archiveItem')->get()->pluck('archiveItem')->filter()->unique('id');
        $completeness = RecordCompleteness::where('citable_type', ArchiveItem::class)->whereIn('citable_id', $materials->pluck('id'))->get()->keyBy('citable_id');

        return response()->json(['data' => [
            'id' => $artist->id,
            'slug' => $artist->slug,
            'legacy_code' => $artist->legacy_code,
            'name' => ['ar' => $artist->name_ar, 'en' => $artist->name_en],
            'city' => ['ar' => $artist->birth_place_ar, 'en' => $artist->birth_place_en],
            'life_dates' => ['birth' => $this->dateLabel($artist->birth), 'death' => $this->dateLabel($artist->death)],
            'name_as_in_sources' => $artist->name_as_in_sources,
            'identified_through' => ['note' => $artist->identified_through_note, 'date' => $artist->identified_through_date?->toDateString()],
            'bio' => ['ar' => $artist->bio_ar, 'en' => $artist->bio_en, 'source_type' => $artist->bio_source_type],
            'verified_status' => $artist->verified_status,
            'contact' => [
                'key_contact_name' => $artist->key_contact_name,
                'owner_type' => $artist->owner_type,
                'contact_email' => $artist->contact_email,
                'contact_phone' => $artist->contact_phone,
                'ref_supervisor_note' => $artist->ref_supervisor_note,
            ],
            'pipeline' => [
                'authorization_letter' => [
                    'status' => $artist->authorization_letter_status,
                    'file_id' => $artist->authorization_letter_file_id,
                    'file_name' => $artist->authorization_letter_file_id ? File::find($artist->authorization_letter_file_id)?->original_filename : null,
                ],
                'owner_pre_agreement' => ['status' => $artist->owner_pre_agreement_status],
            ],
            'checklist' => $service->checklist($artist),
            'public_visibility' => $service->publicVisibility($artist),
            'verify_blockers' => $service->verifyErrors($artist),
            'themes' => $artist->themes->map(fn ($t) => ['id' => $t->id, 'label' => ['ar' => $t->label_ar, 'en' => $t->label_en]])->values(),
            'linked_materials' => $materials->map(fn ($m) => [
                'id' => $m->id,
                'legacy_ref' => $m->legacy_ref,
                'item_type' => $m->item_type,
                'year' => $this->dateLabel($m->content),
                'title' => ['ar' => $m->title_ar, 'en' => $m->title_en],
                'completeness_pct' => $completeness->get($m->id)->completeness_pct ?? 0,
                'gap_count' => count($completeness->get($m->id)->blocking_gap_field_keys ?? []) + count($completeness->get($m->id)->minor_gap_field_keys ?? []),
            ])->values(),
        ]]);
    }

    private function dateLabel(?PartialDate $date): ?string
    {
        if ($date === null) {
            return null;
        }

        if ($date->display !== null) {
            return $date->display;
        }

        if ($date->yearFrom === null) {
            return null;
        }

        return $date->yearTo === null || $date->yearTo === $date->yearFrom ? (string) $date->yearFrom : "{$date->yearFrom}–{$date->yearTo}";
    }
}

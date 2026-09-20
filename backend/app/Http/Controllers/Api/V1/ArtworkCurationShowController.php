<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ArchiveItem;
use App\Models\Artwork;
use App\Models\RecordCompleteness;
use App\Support\Completeness\CompletenessCalculator;
use App\Support\Curation\PipelineService;
use Illuminate\Http\JsonResponse;

class ArtworkCurationShowController
{
    public function __invoke(Artwork $artwork): JsonResponse
    {
        $artwork->load(['artist', 'holder', 'images']);
        $pipeline = new PipelineService;
        $stages = $pipeline->stages($artwork);
        $evaluation = (new CompletenessCalculator)->evaluate($artwork);

        $items = ArchiveItem::query()
            ->whereHas('links', fn ($q) => $q->where('linkable_type', Artwork::class)->where('linkable_id', $artwork->id))
            ->orderByDesc('id')->get();
        $completeness = RecordCompleteness::where('citable_type', ArchiveItem::class)
            ->whereIn('citable_id', $items->pluck('id'))->get()->keyBy('citable_id');

        $dims = fn (string $p) => [
            'height_cm' => $artwork->getAttribute("{$p}height_cm"),
            'width_cm' => $artwork->getAttribute("{$p}width_cm"),
            'depth_cm' => $artwork->getAttribute("{$p}depth_cm"),
            'raw' => $artwork->getAttribute($p === '' ? 'dimensions_raw' : 'frame_dimensions_raw'),
        ];

        $yearFrom = $artwork->getAttribute('creation_year_from');
        $yearCertain = $yearFrom !== null && ! in_array($artwork->getAttribute('creation_certainty'), ['unknown', 'estimated', 'circa'], true);
        $artistLetter = $artwork->artist?->authorization_letter_status;

        $checklist = array_map(fn (array $i) => ['key' => $i[0], 'tier' => $i[1], 'met' => $i[2]], [
            ['code', 'core', $artwork->legacy_ref !== null],
            ['titles', 'core', $artwork->title_ar !== null && $artwork->title_en !== null],
            ['artist', 'core', $artwork->artist_id !== null],
            ['year', 'important', $yearCertain],
            ['dimensions', 'important', ! in_array('dimensions', $evaluation['minor'], true)],
            ['medium', 'important', ! in_array('medium', $evaluation['minor'], true)],
            ['condition_report', 'important', $artwork->getAttribute('condition_report_status') === 'available'],
            ['hr_image', 'important', $artwork->images->contains('is_final', true)],
            ['holder', 'core', $artwork->holder_id !== null],
            ['authorization_letter', 'core', in_array($artistLetter, ['signed', 'not_applicable'], true)],
        ]);

        return response()->json(['data' => [
            'id' => $artwork->id,
            'legacy_ref' => $artwork->legacy_ref,
            'title' => ['ar' => $artwork->title_ar, 'en' => $artwork->title_en],
            'is_untitled' => $artwork->is_untitled,
            'artist' => $artwork->artist ? ['id' => $artwork->artist->id, 'name' => ['ar' => $artwork->artist->name_ar, 'en' => $artwork->artist->name_en]] : null,
            'attribution_certainty' => $artwork->attribution_certainty,
            'category' => $artwork->category,
            'medium' => ['ar' => $artwork->medium_ar, 'en' => $artwork->medium_en],
            'creation' => $artwork->creation?->toArray(),
            'signed' => $artwork->signed,
            'notes' => ['ar' => $artwork->notes_ar, 'en' => $artwork->notes_en],
            'edition' => ['number' => $artwork->edition_number, 'size' => $artwork->edition_size],
            'dimensions' => $dims(''),
            'frame_dimensions' => $dims('frame_'),
            'weight_kg' => $artwork->weight_kg,
            'holder' => $artwork->holder ? ['id' => $artwork->holder->id, 'name' => ['ar' => $artwork->holder->name_ar, 'en' => $artwork->holder->name_en]] : null,
            'holder_inventory_no' => $artwork->holder_inventory_no,
            'material_classification' => $artwork->getAttribute('material_classification'),
            'conservation_risk_note' => $artwork->getAttribute('conservation_risk_note'),
            'inventory_by_owner' => $artwork->getAttribute('inventory_by_owner'),
            'condition_report_link' => $artwork->getAttribute('condition_report_link'),
            'condition_report_status' => $artwork->getAttribute('condition_report_status'),
            'image_quality' => $artwork->getAttribute('image_quality'),
            'editing_status' => $artwork->getAttribute('editing_status'),
            'has_final_hr_image' => $artwork->images->contains('is_final', true),
            'images' => ArtworkImageController::present($artwork),
            'publication_status' => $artwork->publication_status,
            'completeness' => [
                'pct' => $evaluation['completeness_pct'],
                'severity' => $evaluation['severity'],
                'blocking' => $evaluation['blocking'],
                'minor' => $evaluation['minor'],
            ],
            'checklist' => $checklist,
            'approve_blockers' => $pipeline->approvalErrors($artwork),
            'pipeline' => $stages->map(fn ($s) => ['stage_key' => $s->stage_key, 'status' => $s->status, 'note' => $s->note])->values(),
            'linked_materials' => $items->map(fn ($m) => [
                'id' => $m->id,
                'legacy_ref' => $m->legacy_ref,
                'item_type' => $m->item_type,
                'title' => ['ar' => $m->title_ar, 'en' => $m->title_en],
                'completeness_pct' => $completeness->get($m->id)->completeness_pct ?? 0,
            ])->values(),
        ]]);
    }
}

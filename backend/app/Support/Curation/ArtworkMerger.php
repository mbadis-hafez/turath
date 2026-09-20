<?php

namespace App\Support\Curation;

use App\Models\ArchiveItemLink;
use App\Models\Artwork;
use App\Models\ArtworkImage;
use App\Models\ArtworkMerge;
use App\Models\FieldCitation;
use App\Support\Completeness\CompletenessCalculator;
use Illuminate\Support\Facades\DB;

class ArtworkMerger
{
    /** Fields a merge can take from the duplicate. */
    public const MERGEABLE = [
        'artist_id', 'attribution_certainty', 'title_ar', 'title_en', 'is_untitled', 'category',
        'medium_ar', 'medium_en', 'edition_number', 'edition_size', 'height_cm', 'width_cm', 'depth_cm',
        'dimensions_raw', 'frame_height_cm', 'frame_width_cm', 'frame_depth_cm', 'frame_dimensions_raw',
        'weight_kg', 'signed', 'material_classification', 'conservation_risk_note', 'holder_id', 'holder_inventory_no', 'notes_ar', 'notes_en',
        'creation_date_display', 'creation_year_from', 'creation_year_to', 'creation_calendar', 'creation_certainty',
    ];

    /**
     * @param  array<string, string>  $fieldResolution  field => 'survivor'|'duplicate'
     */
    public function merge(Artwork $survivor, Artwork $duplicate, array $fieldResolution): ArtworkMerge
    {
        return DB::transaction(function () use ($survivor, $duplicate, $fieldResolution) {
            foreach ($fieldResolution as $field => $choice) {
                if ($choice === 'duplicate' && in_array($field, self::MERGEABLE, true)) {
                    $survivor->setAttribute($field, $duplicate->getAttribute($field));
                }
            }
            $survivor->save();

            foreach (ArchiveItemLink::where('linkable_type', Artwork::class)->where('linkable_id', $duplicate->id)->get() as $link) {
                $clash = ArchiveItemLink::where('archive_item_id', $link->archive_item_id)
                    ->where('linkable_type', Artwork::class)->where('linkable_id', $survivor->id)
                    ->where('role', $link->role)->exists();
                $clash ? $link->delete() : $link->update(['linkable_id' => $survivor->id]);
            }

            ArtworkImage::where('artwork_id', $duplicate->id)->update(['artwork_id' => $survivor->id, 'is_final' => false]);

            FieldCitation::where('citable_type', Artwork::class)->where('citable_id', $duplicate->id)
                ->update(['citable_id' => $survivor->id]);

            $duplicate->merged_into_id = $survivor->id;
            $duplicate->save();
            $duplicate->delete();

            $merge = ArtworkMerge::create([
                'survivor_artwork_id' => $survivor->id,
                'merged_artwork_id' => $duplicate->id,
                'field_resolution' => $fieldResolution,
                'merged_by_user_id' => auth()->id(),
            ]);

            (new CompletenessCalculator)->recompute($survivor);
            DB::table('record_completeness')->where('citable_type', Artwork::class)->where('citable_id', $duplicate->id)->delete();

            return $merge;
        });
    }
}

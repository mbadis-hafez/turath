<?php

namespace App\Support\Curation;

use App\Models\ArchiveItemLink;
use App\Models\Artist;
use App\Models\ArtistContact;
use App\Models\ArtistEntry;
use App\Models\ArtistMerge;
use App\Models\ArtistSocialLink;
use App\Models\Artwork;
use App\Models\FieldCitation;
use App\Support\ArtistSearchTextBuilder;
use App\Support\Completeness\CompletenessCalculator;
use Illuminate\Support\Facades\DB;

class ArtistMerger
{
    public const MERGEABLE = [
        'name_ar', 'name_en', 'bio_ar', 'bio_en', 'birth_place_ar', 'birth_place_en', 'death_place_ar', 'death_place_en',
        'birth_date_display', 'birth_year_from', 'birth_year_to', 'birth_calendar', 'birth_certainty',
        'death_date_display', 'death_year_from', 'death_year_to', 'death_calendar', 'death_certainty',
        'living_status', 'nationality_ar', 'nationality_en', 'classification_ar', 'classification_en', 'owner_type', 'identified_through_note', 'ref_supervisor_note',
    ];

    /**
     * @param  array<string, string>  $fieldResolution  field => 'survivor'|'duplicate'
     */
    public function merge(Artist $survivor, Artist $duplicate, array $fieldResolution): ArtistMerge
    {
        return DB::transaction(function () use ($survivor, $duplicate, $fieldResolution) {
            foreach ($fieldResolution as $field => $choice) {
                if ($choice === 'duplicate' && in_array($field, self::MERGEABLE, true)) {
                    $survivor->setAttribute($field, $duplicate->getAttribute($field));
                }
            }
            $survivor->save();

            Artwork::where('artist_id', $duplicate->id)->update(['artist_id' => $survivor->id]);

            foreach (ArchiveItemLink::where('linkable_type', Artist::class)->where('linkable_id', $duplicate->id)->get() as $link) {
                $clash = ArchiveItemLink::where('archive_item_id', $link->archive_item_id)
                    ->where('linkable_type', Artist::class)->where('linkable_id', $survivor->id)->where('role', $link->role)->exists();
                $clash ? $link->delete() : $link->update(['linkable_id' => $survivor->id]);
            }

            FieldCitation::where('citable_type', Artist::class)->where('citable_id', $duplicate->id)->update(['citable_id' => $survivor->id]);

            $existingNames = $survivor->variants()->pluck('name')->all();
            foreach ($duplicate->variants as $variant) {
                in_array($variant->name, $existingNames, true) ? $variant->delete() : $variant->update(['artist_id' => $survivor->id]);
            }

            ArtistContact::where('artist_id', $duplicate->id)->update(['artist_id' => $survivor->id]);
            ArtistEntry::where('artist_id', $duplicate->id)->update(['artist_id' => $survivor->id]);
            ArtistSocialLink::where('artist_id', $duplicate->id)->update(['artist_id' => $survivor->id]);

            $survivor->themes()->syncWithoutDetaching($duplicate->themes()->pluck('themes.id')->all());
            $duplicate->themes()->detach();

            $duplicate->merged_into_id = $survivor->id;
            $duplicate->save();
            $duplicate->delete();

            $merge = ArtistMerge::create([
                'survivor_artist_id' => $survivor->id,
                'merged_artist_id' => $duplicate->id,
                'field_resolution' => $fieldResolution,
                'merged_by_user_id' => auth()->id(),
            ]);

            ArtistSearchTextBuilder::rebuildQuietly($survivor->refresh());
            (new CompletenessCalculator)->recompute($survivor);
            DB::table('record_completeness')->where('citable_type', Artist::class)->where('citable_id', $duplicate->id)->delete();

            return $merge;
        });
    }
}

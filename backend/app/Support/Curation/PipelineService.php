<?php

namespace App\Support\Curation;

use App\Models\Artwork;
use App\Models\ArtworkPipelineStage;
use App\Models\FieldCitation;
use App\Models\Source;
use App\Support\Completeness\CompletenessCalculator;
use App\Support\Completeness\ConflictDetector;
use Illuminate\Support\Collection;

class PipelineService
{
    private const CITATION_FIELD = 'image_usage_permission';

    public function ensureStages(Artwork $artwork): void
    {
        foreach (ArtworkPipelineStage::KEYS as $key) {
            ArtworkPipelineStage::firstOrCreate(
                ['artwork_id' => $artwork->id, 'stage_key' => $key],
                ['status' => 'not_started'],
            );
        }
    }

    /**
     * @return Collection<int, ArtworkPipelineStage>
     */
    public function stages(Artwork $artwork): Collection
    {
        $this->ensureStages($artwork);

        return ArtworkPipelineStage::where('artwork_id', $artwork->id)->get()
            ->sortBy(fn (ArtworkPipelineStage $s) => array_search($s->stage_key, ArtworkPipelineStage::KEYS, true))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Artwork $artwork, string $stageKey, array $data): ArtworkPipelineStage
    {
        $this->ensureStages($artwork);
        $stage = ArtworkPipelineStage::where('artwork_id', $artwork->id)->where('stage_key', $stageKey)->firstOrFail();

        $stage->fill(array_intersect_key($data, array_flip(['status', 'note', 'linked_file_id'])));
        $stage->updated_by_user_id = auth()->id();
        $stage->updated_at = now();
        $stage->save();

        $this->syncAgreementCitation($artwork);

        return $stage;
    }

    /**
     * D89: the agreement stages are how the image-usage-permission
     * completeness requirement actually gets met.
     */
    public function syncAgreementCitation(Artwork $artwork): void
    {
        $stages = $this->stages($artwork)->keyBy('stage_key');
        $satisfied = $stages['owner_pre_agreement']->isCleared() && $stages['contract_draft']->isCleared();
        $marker = "pipeline:artwork:{$artwork->id}";

        $existing = FieldCitation::where('citable_type', Artwork::class)
            ->where('citable_id', $artwork->id)->where('field_key', self::CITATION_FIELD)
            ->whereHas('source', fn ($q) => $q->where('reference_note', $marker))->first();

        if ($satisfied && $existing === null) {
            $source = Source::create([
                'source_type' => 'institutional_record',
                'title_en' => 'Owner agreement and contract (curation pipeline)',
                'title_ar' => 'اتفاقية المالك والعقد (مسار العمل)',
                'reference_note' => $marker,
                'added_by_user_id' => auth()->id(),
            ]);
            FieldCitation::create([
                'citable_type' => Artwork::class,
                'citable_id' => $artwork->id,
                'field_key' => self::CITATION_FIELD,
                'source_id' => $source->id,
                'claimed_value' => ['granted' => true],
                'created_by_user_id' => auth()->id(),
            ]);
        } elseif (! $satisfied && $existing !== null) {
            $existing->delete();
        } else {
            return;
        }

        (new ConflictDetector)->check(Artwork::class, $artwork->id, self::CITATION_FIELD);
        (new CompletenessCalculator)->recompute($artwork);
    }

    /**
     * D91: one itemized list combining data (F10) and pipeline gaps.
     *
     * @return array<string, array<int, string>>
     */
    public function approvalErrors(Artwork $artwork): array
    {
        $errors = [];
        $calculator = new CompletenessCalculator;
        $rules = $calculator->rulesFor(Artwork::class);

        foreach ($calculator->evaluate($artwork)['blocking'] as $key) {
            $errors["data.{$key}"] = ["Missing required field: {$rules->fieldLabel($key)['en']}."];
        }

        foreach ($this->stages($artwork) as $stage) {
            if (! $stage->isCleared()) {
                $errors["pipeline.{$stage->stage_key}"] = ["Pipeline stage {$stage->stage_key} is {$stage->status}."];
            }
        }

        return $errors;
    }
}

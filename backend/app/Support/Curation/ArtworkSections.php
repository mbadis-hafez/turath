<?php

namespace App\Support\Curation;

use App\Models\Artwork;
use App\Models\ArtworkPipelineStage;
use App\Models\User;
use App\Support\Completeness\PublishGate;
use App\Support\Proposals\FieldValues;
use App\Support\Proposals\RecordUpdater;

class ArtworkSections
{
    /**
     * Flat field update, mirroring ArtworkUpdateController: fill, gate the
     * transition to published, save — and report what actually changed.
     *
     * @param  array<string, mixed>  $mappedAttributes  column => new value
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function applyFields(Artwork $artwork, array $mappedAttributes): array
    {
        $publishing = ($mappedAttributes['publication_status'] ?? null) !== null
            && FieldValues::normalize($mappedAttributes['publication_status']) === 'published'
            && FieldValues::differ($artwork->getAttribute('publication_status'), 'published');

        if ($publishing) {
            $artwork->fill($mappedAttributes);
            PublishGate::assertPublishable($artwork);
        }

        return RecordUpdater::apply($artwork, $mappedAttributes);
    }

    /**
     * @param  array<int, array{stage_key: string, status?: string, note?: string, linked_file_id?: int}>  $stages
     */
    public function applyPipeline(Artwork $artwork, array $stages, User $actor): void
    {
        $pipeline = new PipelineService;

        foreach ($stages as $stage) {
            abort_unless(in_array($stage['stage_key'], ArtworkPipelineStage::KEYS, true), 422);

            $pipeline->update($artwork, $stage['stage_key'], array_intersect_key($stage, array_flip(['status', 'note', 'linked_file_id'])));
        }
    }

    /**
     * @param  array<int, array{stage_key: string, status?: string, note?: string, linked_file_id?: int}>  $stages
     */
    public function pipelineDiffer(Artwork $artwork, array $stages): bool
    {
        $live = ArtworkPipelineStage::query()->where('artwork_id', $artwork->id)->get()->keyBy('stage_key');

        foreach ($stages as $stage) {
            $row = $live->get($stage['stage_key']);
            if ($row === null) {
                return true;
            }
            foreach (['status', 'note', 'linked_file_id'] as $column) {
                if (array_key_exists($column, $stage) && FieldValues::differ($row->getAttribute($column), $stage[$column])) {
                    return true;
                }
            }
        }

        return false;
    }
}

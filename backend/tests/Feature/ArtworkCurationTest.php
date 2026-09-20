<?php

use App\Models\ArchiveItem;
use App\Models\ArchiveItemLink;
use App\Models\Artwork;
use App\Models\ArtworkMerge;
use App\Models\ArtworkPipelineStage;
use App\Models\CandidateArtwork;
use App\Models\FieldCitation;
use App\Models\Holder;
use App\Models\PipelineNoteSuggestion;
use Spatie\Activitylog\Models\Activity;

it('seeds all six pipeline stages when an artwork is created', function () {
    $artwork = Artwork::factory()->create();

    expect(ArtworkPipelineStage::where('artwork_id', $artwork->id)->pluck('status')->all())
        ->toHaveCount(6)->each->toBe('not_started');
});

it('lets editors advance stages, audits it, and routes contributor status_research notes to a suggestion', function () {
    $artwork = Artwork::factory()->create();

    $this->actingAs(editorUser())->patchJson("/api/v1/artworks/{$artwork->id}/pipeline/availability", ['status' => 'done'])
        ->assertOk()->assertJsonPath('data.status', 'done');
    expect(Activity::where('subject_type', ArtworkPipelineStage::class)->where('event', 'updated')->exists())->toBeTrue();

    $contributor = makeUser('contributor');
    $this->actingAs($contributor)->patchJson("/api/v1/artworks/{$artwork->id}/pipeline/availability", ['status' => 'done'])->assertForbidden();
    $this->actingAs($contributor)->patchJson("/api/v1/artworks/{$artwork->id}/pipeline/status_research", ['note' => 'Seen at 1975 show'])
        ->assertStatus(202);

    expect(ArtworkPipelineStage::where('artwork_id', $artwork->id)->where('stage_key', 'status_research')->first()->note)->toBeNull()
        ->and(PipelineNoteSuggestion::count())->toBe(1);

    $suggestion = PipelineNoteSuggestion::first();
    $this->actingAs(editorUser())->postJson("/api/v1/pipeline-note-suggestions/{$suggestion->id}/accept")->assertOk();
    expect(ArtworkPipelineStage::where('artwork_id', $artwork->id)->where('stage_key', 'status_research')->first()->note)->toBe('Seen at 1975 show');
});

it('creates the image-usage citation only when both agreement stages clear', function () {
    $editor = editorUser();
    $artwork = Artwork::factory()->create();

    $this->actingAs($editor)->patchJson("/api/v1/artworks/{$artwork->id}/pipeline/owner_pre_agreement", ['status' => 'done']);
    expect(FieldCitation::where('field_key', 'image_usage_permission')->count())->toBe(0);

    $this->actingAs($editor)->patchJson("/api/v1/artworks/{$artwork->id}/pipeline/contract_draft", ['status' => 'not_applicable']);
    expect(FieldCitation::where('citable_id', $artwork->id)->where('field_key', 'image_usage_permission')->count())->toBe(1);

    $this->actingAs($editor)->patchJson("/api/v1/artworks/{$artwork->id}/pipeline/contract_draft", ['status' => 'in_progress']);
    expect(FieldCitation::where('field_key', 'image_usage_permission')->count())->toBe(0);
});

it('refuses approval with one itemized list of data and pipeline gaps, and approves when both clear', function () {
    $editor = editorUser();
    $artwork = Artwork::factory()->draft()->create(['holder_id' => null]);

    $errors = $this->actingAs($editor)->postJson("/api/v1/artworks/{$artwork->id}/approve")->assertStatus(422)->json('errors');
    expect($errors)->toHaveKey('data.holder')->toHaveKey('pipeline.contract_draft');

    $artwork->update(['holder_id' => Holder::factory()->create()->id]);
    foreach (ArtworkPipelineStage::KEYS as $key) {
        $this->actingAs($editor)->patchJson("/api/v1/artworks/{$artwork->id}/pipeline/{$key}", ['status' => 'done']);
    }

    $this->actingAs($editor)->postJson("/api/v1/artworks/{$artwork->id}/approve")
        ->assertOk()->assertJsonPath('data.publication_status', 'published');
});

it('keeps internal-only columns out of the public artwork resource', function () {
    $artwork = Artwork::factory()->published()->create(['inventory_by_owner' => 'INV-9', 'condition_report_link' => 'http://x']);

    $json = $this->getJson("/api/v1/artworks/{$artwork->id}")->assertOk()->json('data');

    expect($json)->not->toHaveKeys(['inventory_by_owner', 'condition_report_link', 'condition_report_status', 'image_quality', 'merged_into_id']);
});

it('merges duplicates: re-points links and citations, tombstones the old id, and audits', function () {
    $editor = editorUser();
    $survivor = Artwork::factory()->published()->create(['medium_en' => null]);
    $duplicate = Artwork::factory()->published()->create(['medium_en' => 'Oil on canvas']);
    $item = ArchiveItem::factory()->create();
    ArchiveItemLink::create(['archive_item_id' => $item->id, 'linkable_type' => Artwork::class, 'linkable_id' => $duplicate->id, 'role' => 'depicts']);
    FieldCitation::factory()->create(['citable_type' => Artwork::class, 'citable_id' => $duplicate->id, 'field_key' => 'holder']);

    $this->actingAs($editor)->postJson('/api/v1/artworks/merge', [
        'survivor_id' => $survivor->id,
        'duplicate_id' => $duplicate->id,
        'field_resolution' => ['medium_en' => 'duplicate'],
    ])->assertCreated();

    expect($survivor->refresh()->medium_en)->toBe('Oil on canvas')
        ->and(ArchiveItemLink::where('linkable_id', $survivor->id)->where('linkable_type', Artwork::class)->count())->toBe(1)
        ->and(FieldCitation::where('citable_id', $survivor->id)->where('citable_type', Artwork::class)->count())->toBe(1)
        ->and(Artwork::withTrashed()->find($duplicate->id)->trashed())->toBeTrue();

    $this->getJson("/api/v1/artworks/{$duplicate->id}")->assertStatus(301)->assertRedirect("/api/v1/artworks/{$survivor->id}");
    expect(Activity::where('subject_type', ArtworkMerge::class)->exists())->toBeTrue();
});

it('promotes a candidate into a draft artwork with stages and provenance, and dismisses others', function () {
    $editor = editorUser();
    $item = ArchiveItem::factory()->create();
    $candidate = CandidateArtwork::create(['source_type' => 'archive_item_mention', 'source_archive_item_id' => $item->id, 'suggested_title_en' => 'Folk Dance']);
    $other = CandidateArtwork::create(['source_type' => 'archive_item_mention', 'suggested_title_en' => 'Nope']);

    $id = $this->actingAs($editor)->postJson("/api/v1/candidate-artworks/{$candidate->id}/promote")
        ->assertCreated()->assertJsonPath('data.publication_status', 'draft')->json('data.id');

    expect(ArtworkPipelineStage::where('artwork_id', $id)->count())->toBe(6)
        ->and(ArchiveItemLink::where('archive_item_id', $item->id)->where('linkable_id', $id)->exists())->toBeTrue();

    $this->actingAs($editor)->postJson("/api/v1/candidate-artworks/{$other->id}/dismiss")->assertOk();
    $this->actingAs($editor)->getJson('/api/v1/candidate-artworks')->assertJsonCount(0, 'data');
});

it('filters the internal registry and blocks non-editors', function () {
    $editor = editorUser();
    Artwork::factory()->create(['height_cm' => null, 'width_cm' => null]);
    Artwork::factory()->create(['height_cm' => 10, 'width_cm' => 10]);

    $this->actingAs($editor)->getJson('/api/v1/admin/artworks?missing_dimensions=1&has_pipeline_gap=1')
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.flags', fn ($flags) => in_array('missing_dimensions', $flags, true));

    $this->actingAs(makeUser('reader'))->getJson('/api/v1/admin/artworks')->assertForbidden();
});

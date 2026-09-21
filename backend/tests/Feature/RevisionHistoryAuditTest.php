<?php

use App\Models\Artist;
use App\Models\Revision;

it('surfaces child entry edits in the revision history as non-revertible audit entries', function () {
    $editor = editorUser();
    $artist = Artist::factory()->create();

    $this->actingAs($editor)->putJson("/api/v1/artists/{$artist->id}/entries", [
        'activities' => [['type' => 'award', 'title' => ['en' => 'Prize'], 'place' => ['en' => 'Ministry'], 'year_from' => 1988]],
    ])->assertOk();

    $entryId = $artist->entries()->sole()->id;

    $this->actingAs($editor)->putJson("/api/v1/artists/{$artist->id}/entries", [
        'activities' => [['id' => $entryId, 'type' => 'award', 'title' => ['en' => 'Prize'], 'place' => ['en' => 'Royal Academy'], 'year_from' => 1988]],
    ])->assertOk();

    $history = $this->actingAs($editor)->getJson("/api/v1/records/artists/{$artist->id}/revisions")->assertOk()->json('data');

    $audit = collect($history)->firstWhere('event', 'updated');
    expect($audit)->not->toBeNull()
        ->and($audit['source'])->toBe('audit')
        ->and($audit['id'])->toStartWith('audit-')
        ->and($audit['revision_number'])->toBeNull()
        ->and($audit['event'])->toBe('updated')
        ->and($audit['subject_label'])->toContain('Prize')
        ->and($audit['field_diffs']['place_en'])->toBe(['old' => 'Ministry', 'new' => 'Royal Academy'])
        ->and($audit['field_labels']['place_en']['en'])->toBe('Place (English)')
        ->and($audit['applied_by']['id'])->toBe($editor->id)
        ->and($audit['reverted_by_revision_id'])->toBeNull()
        ->and(collect($history)->where('source', 'audit')->where('event', 'created'))->toHaveCount(1);

    // Audit rows never become replayable revisions.
    expect(Revision::where('citable_id', $artist->id)->count())->toBe(0)
        // And they cannot be rolled back through the revisions endpoint.
        ->and($this->actingAs($editor)->postJson("/api/v1/records/artists/{$artist->id}/revisions/{$audit['id']}/rollback")->status())->toBe(404);
});

it('surfaces encrypted contact edits as a counts-only audit entry', function () {
    $editor = editorUser();
    $artist = Artist::factory()->create();
    $contact = $artist->contacts()->create(['name' => 'Nora', 'email' => 'x@y.co']);

    $this->actingAs($editor)->patchJson("/api/v1/artists/{$artist->id}/curation", [
        'contacts' => [['id' => $contact->id, 'name' => 'Nora N.', 'email' => 'x@y.co'], ['name' => 'Second', 'phone' => '+1 555']],
    ])->assertOk();

    $history = $this->actingAs($editor)->getJson("/api/v1/records/artists/{$artist->id}/revisions")->assertOk()->json('data');

    $audit = collect($history)->firstWhere('source', 'audit');
    expect($audit)->not->toBeNull()
        ->and($audit['description'])->toBe('contacts changed')
        ->and($audit['subject_label'])->toBeNull()
        ->and($audit['contacts_changed'])->toMatchArray(['created' => 1, 'updated' => 1, 'deleted' => 0])
        ->and(json_encode($audit))->not->toContain('x@y.co');
});

it('interleaves audit entries with revisions newest first', function () {
    $editor = editorUser();
    $artist = Artist::factory()->create(['bio_en' => 'Older.']);

    $this->actingAs($editor)->putJson("/api/v1/artists/{$artist->id}/entries", [
        'activities' => [['type' => 'award', 'title' => ['en' => 'Prize'], 'year_from' => 1988]],
    ])->assertOk();

    // Sleep so the direct edit lands a strictly later timestamp than the entry edit.
    usleep(1100 * 1000);
    $this->actingAs($editor)->patchJson("/api/v1/artists/{$artist->id}", ['bio' => ['en' => 'Newer.']])->assertOk();

    $history = $this->actingAs($editor)->getJson("/api/v1/records/artists/{$artist->id}/revisions")->assertOk()->json('data');

    expect(collect($history)->pluck('source')->all())->toBe(['direct_edit', 'audit'])
        ->and(collect($history)->firstWhere('source', 'audit')['field_diffs'])->not->toBe([]);
});

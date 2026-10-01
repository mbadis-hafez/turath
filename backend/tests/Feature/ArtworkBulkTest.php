<?php

use App\Models\Artwork;

it('bulk-updates status, reports items the publish gate rejects, and deletes', function () {
    $editor = editorUser();
    $a = Artwork::factory()->create(['publication_status' => 'draft']);
    $b = Artwork::factory()->create(['publication_status' => 'draft']);

    $res = $this->actingAs($editor)->postJson('/api/v1/admin/artworks/bulk', ['ids' => [$a->id, $b->id], 'action' => 'set_status', 'status' => 'hidden'])->assertOk();
    expect($res->json('data.succeeded'))->toEqualCanonicalizing([$a->id, $b->id])
        ->and($a->refresh()->publication_status)->toBe('hidden')
        ->and($b->refresh()->publication_status)->toBe('hidden');

    // A record with blocking completeness gaps fails the publish gate; the
    // admin holds artworks.publish, so its rejection path is exercised here.
    $blocked = Artwork::factory()->create(['publication_status' => 'draft', 'holder_id' => null]);
    $res = $this->actingAs(makeUser('admin'))->postJson('/api/v1/admin/artworks/bulk', ['ids' => [$blocked->id], 'action' => 'set_status', 'status' => 'published'])->assertOk();
    expect($res->json('data.failed.0.id'))->toBe($blocked->id)->and($blocked->refresh()->publication_status)->toBe('draft');

    // An editor without artworks.publish is refused outright for a publish action.
    $this->actingAs($editor)->postJson('/api/v1/admin/artworks/bulk', ['ids' => [$blocked->id], 'action' => 'set_status', 'status' => 'published'])->assertForbidden();

    $this->actingAs($editor)->postJson('/api/v1/admin/artworks/bulk', ['ids' => [$b->id], 'action' => 'delete'])->assertJsonCount(1, 'data.succeeded');
    expect(Artwork::find($b->id))->toBeNull();

    $this->actingAs(makeUser('reader'))->postJson('/api/v1/admin/artworks/bulk', ['ids' => [$a->id], 'action' => 'delete'])->assertForbidden();
});

it('rejects an unknown bulk action', function () {
    $editor = editorUser();
    $a = Artwork::factory()->create();

    $this->actingAs($editor)->postJson('/api/v1/admin/artworks/bulk', ['ids' => [$a->id], 'action' => 'bogus'])->assertStatus(422);
});

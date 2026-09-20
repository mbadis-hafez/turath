<?php

use App\Models\ArchiveItem;
use App\Models\Artist;

it('lists archive items for editors with counts, filters and mine', function () {
    $editor = editorUser();
    $other = editorUser();

    $mine = $this->actingAs($editor)->postJson('/api/v1/archive-items', [
        'item_type' => 'image', 'title' => ['en' => 'Opening photo'], 'access_level' => 'institution_only',
    ])->assertCreated()->json('data.id');
    Auth::forgetGuards();
    $this->actingAs($other)->postJson('/api/v1/archive-items', ['item_type' => 'poster', 'title' => ['en' => 'A poster'], 'access_level' => 'institution_only'])->assertCreated();
    Auth::forgetGuards();

    $all = $this->actingAs($editor)->getJson('/api/v1/admin/archive-items')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('meta.mine_count', 1);
    expect(collect($all->json('data'))->firstWhere('id', $mine)['incomplete'])->toBeTrue();

    $this->actingAs($editor)->getJson('/api/v1/admin/archive-items?mine=1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine);
    $this->actingAs($editor)->getJson('/api/v1/admin/archive-items?item_type=poster')->assertJsonCount(1, 'data');
    $this->actingAs($editor)->getJson('/api/v1/admin/archive-items?status=incomplete')->assertJsonCount(2, 'data');
    $this->actingAs($editor)->getJson('/api/v1/admin/archive-items?status=published')->assertJsonCount(0, 'data');

    $this->actingAs(makeUser('reader'))->getJson('/api/v1/admin/archive-items')->assertForbidden();
});

it('bulk-links to an artist, reports items the publish gate rejects, and deletes', function () {
    $editor = editorUser();
    $artist = Artist::factory()->create();
    $a = ArchiveItem::factory()->create(['publication_status' => 'draft']);
    $b = ArchiveItem::factory()->create(['publication_status' => 'draft']);

    $this->actingAs($editor)->postJson('/api/v1/admin/archive-items/bulk', ['ids' => [$a->id, $b->id], 'action' => 'link_artist', 'artist_id' => $artist->id])
        ->assertOk()->assertJsonCount(2, 'data.succeeded');
    expect($a->links()->count())->toBe(1);

    $res = $this->actingAs($editor)->postJson('/api/v1/admin/archive-items/bulk', ['ids' => [$a->id], 'action' => 'set_status', 'status' => 'hidden'])->assertOk();
    expect($res->json('data.succeeded'))->toBe([$a->id])->and($a->refresh()->publication_status)->toBe('hidden');

    $blocked = ArchiveItem::factory()->create(['publication_status' => 'draft', 'rights_status' => 'unknown', 'access_level' => 'public']);
    $res = $this->actingAs($editor)->postJson('/api/v1/admin/archive-items/bulk', ['ids' => [$blocked->id], 'action' => 'set_status', 'status' => 'published'])->assertOk();
    expect($res->json('data.failed.0.id'))->toBe($blocked->id)->and($blocked->refresh()->publication_status)->toBe('draft');

    $this->actingAs($editor)->postJson('/api/v1/admin/archive-items/bulk', ['ids' => [$b->id], 'action' => 'delete'])->assertJsonCount(1, 'data.succeeded');
    expect(ArchiveItem::find($b->id))->toBeNull();
});

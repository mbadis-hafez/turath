<?php

use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\File;
use App\Models\ReviewQueueItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('lists archive items for editors with counts, filters and mine', function () {
    $editor = editorUser();
    $other = editorUser();

    $mine = $this->actingAs($editor)->postJson('/api/v1/archive-items', [
        'item_type' => 'image', 'title' => ['en' => 'Opening photo'], 'access_level' => 'institution_only',
    ])->assertCreated()->json('data.id');
    Auth::forgetGuards();
    $this->actingAs($other)->postJson('/api/v1/archive-items', ['item_type' => 'poster', 'title' => ['en' => 'A poster'], 'access_level' => 'institution_only'])->assertCreated();
    Auth::forgetGuards();

    $all = $this->actingAs($editor)->getJson('/api/v1/admin/archive-items')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('meta.mine_count', 1)->assertJsonPath('meta.total_all', 2);
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

it('edits an archive item end to end: new fields, one original file, checklist, review submission and access-checked download', function () {
    Storage::fake('local');
    $editor = editorUser();

    $id = $this->actingAs($editor)->postJson('/api/v1/archive-items', [
        'item_type' => 'image', 'title' => ['ar' => 'افتتاح معرض'], 'access_level' => 'registered',
    ])->assertCreated()->json('data.id');

    $bundle = $this->actingAs($editor)->getJson("/api/v1/admin/archive-items/{$id}")->assertOk()->json('data');
    expect($bundle['completeness_pct'])->toBe(20)->and($bundle['file'])->toBeNull();
    $this->actingAs($editor)->postJson("/api/v1/archive-items/{$id}/submit-review")->assertUnprocessable();

    $this->actingAs($editor)->patchJson("/api/v1/archive-items/{$id}", [
        'content' => ['display' => '1979-03-14', 'year_from' => 1979, 'year_to' => 1979, 'certainty' => 'exact'],
        'rights_holder' => ['ar' => 'أرشيف عائلي'], 'license' => 'CC BY',
        'people_names' => ['منيرة الموصلي', ' ', 'صفية بن زقر'], 'keywords' => ['معارض'],
        'place' => ['ar' => 'دار الفنون'], 'source_name' => 'أرشيف عائلة', 'verification_reference' => 'شهادة تسليم',
    ])->assertOk();

    $file = $this->actingAs($editor)->post("/api/v1/archive-items/{$id}/file", ['file' => UploadedFile::fake()->image('a.jpg', 400, 300)], ['Accept' => 'application/json'])
        ->assertCreated()->assertJsonPath('data.width_px', 400)->json('data');
    $replaced = $this->actingAs($editor)->post("/api/v1/archive-items/{$id}/file", ['file' => UploadedFile::fake()->image('b.jpg', 200, 100)], ['Accept' => 'application/json'])->assertCreated()->json('data');
    expect($replaced['id'])->not->toBe($file['id'])->and(File::where('archive_item_id', $id)->count())->toBe(1);

    $bundle = $this->actingAs($editor)->getJson("/api/v1/admin/archive-items/{$id}")->json('data');
    expect($bundle['completeness_pct'])->toBe(100)->and($bundle['people_names'])->toBe(['منيرة الموصلي', 'صفية بن زقر']);

    $this->actingAs($editor)->postJson("/api/v1/archive-items/{$id}/submit-review")->assertOk()->assertJsonPath('data.under_review', true);
    $this->actingAs($editor)->postJson("/api/v1/archive-items/{$id}/submit-review")->assertOk();
    expect(ReviewQueueItem::where('citable_id', $id)->count())->toBe(1);

    Auth::forgetGuards();
    $this->getJson($replaced['url'])->assertNotFound();
    $this->actingAs($editor)->get($replaced['url'])->assertOk();

    $this->actingAs($editor)->deleteJson("/api/v1/archive-items/{$id}/file")->assertOk()->assertJsonPath('data', null);
    $this->actingAs($editor)->post("/api/v1/archive-items/{$id}/file", ['file' => UploadedFile::fake()->create('x.exe', 10)], ['Accept' => 'application/json'])->assertUnprocessable();
});

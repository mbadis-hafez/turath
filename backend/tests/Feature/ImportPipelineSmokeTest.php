<?php

use App\Models\Artist;
use App\Models\ImportBatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('blocks anonymous and reader users from the import endpoints', function () {
    $this->getJson('/api/v1/imports')->assertUnauthorized();
    $this->actingAs(makeUser('reader'))->getJson('/api/v1/imports')->assertForbidden();
});

it('uploads a CSV, validates rows inline, and blocks commit until every row is resolved', function () {
    $editor = editorUser();
    $existing = Artist::factory()->published()->create(['legacy_code' => 'AR013']);

    $csv = "Artist name AR,Artist name EN,Identificaton code\n"
        ."عبدالحليم رضوي,Abdulhalim Radwi,AR013\n"
        ."فنان جديد,New Artist,AR900\n";
    $file = UploadedFile::fake()->createWithContent('artists.csv', $csv);

    $response = $this->actingAs($editor)->postJson('/api/v1/imports', [
        'entity_type' => 'artist',
        'column_map' => [
            'Artist name AR' => 'name_ar',
            'Artist name EN' => 'name_en',
            'Identificaton code' => 'legacy_code',
        ],
        'file' => $file,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'validated')
        ->assertJsonPath('data.row_count', 2)
        ->assertJsonPath('data.new_count', 1)
        ->assertJsonPath('data.matched_count', 1);

    $batchId = $response->json('data.id');

    $rows = $this->actingAs($editor)->getJson("/api/v1/imports/{$batchId}/rows")->assertOk();
    $matchedRow = collect($rows->json('data'))->firstWhere('match_status', 'matched_exact');
    $newRow = collect($rows->json('data'))->firstWhere('match_status', 'new');

    expect($matchedRow['matched_entity_id'])->toBe($existing->id);

    // Commit is rejected while any row is still pending.
    $this->actingAs($editor)->postJson("/api/v1/imports/{$batchId}/commit")->assertStatus(422);

    $this->actingAs($editor)->patchJson("/api/v1/imports/{$batchId}/rows/{$matchedRow['id']}", [
        'resolution' => 'link_existing',
    ])->assertOk();

    $this->actingAs($editor)->patchJson("/api/v1/imports/{$batchId}/rows/{$newRow['id']}", [
        'resolution' => 'create_new',
    ])->assertOk();

    $commitResponse = $this->actingAs($editor)->postJson("/api/v1/imports/{$batchId}/commit")->assertOk();
    expect($commitResponse->json('data.status'))->toBe('committed');

    $newArtistId = $this->actingAs($editor)->getJson("/api/v1/imports/{$batchId}/rows")
        ->json('data');
    $created = collect($newArtistId)->firstWhere('id', $newRow['id']);

    expect($created['commit_result'])->toBe('created');

    $newArtist = Artist::find($created['resulting_entity_id']);
    expect($newArtist->publication_status)->toBe('draft')
        ->and($newArtist->name_en)->toBe('New Artist');

    $batch = ImportBatch::find($batchId);
    expect($batch->status)->toBe('committed');
});

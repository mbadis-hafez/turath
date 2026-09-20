<?php

use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\Holder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('imports holders, matching by exact code or exact normalized name only', function () {
    $editor = editorUser();
    $existing = Holder::factory()->create(['legacy_code' => 'MU001', 'name_en' => 'Altaybat Museum']);

    $csv = "legacy_code,type,name_en,city_en\n"
        ."MU001,institution,Altaybat Museum,Jeddah\n"
        .",private_collector,Brand New Collector,Riyadh\n";
    $file = UploadedFile::fake()->createWithContent('holders.csv', $csv);

    $response = $this->actingAs($editor)->postJson('/api/v1/imports', [
        'entity_type' => 'holder',
        'column_map' => [
            'legacy_code' => 'legacy_code',
            'type' => 'type',
            'name_en' => 'name_en',
            'city_en' => 'city_en',
        ],
        'file' => $file,
    ])->assertCreated();

    $batchId = $response->json('data.id');
    $rows = collect($this->actingAs($editor)->getJson("/api/v1/imports/{$batchId}/rows")->json('data'));

    $matched = $rows->firstWhere('match_status', 'matched_exact');
    $new = $rows->firstWhere('match_status', 'new');

    expect($matched['matched_entity_id'])->toBe($existing->id)
        ->and($matched['mapped_data']['is_public_name'])->toBeTrue()
        ->and($new['mapped_data']['is_public_name'])->toBeFalse();

    $this->actingAs($editor)->patchJson("/api/v1/imports/{$batchId}/rows/{$matched['id']}", ['resolution' => 'link_existing'])->assertOk();
    $this->actingAs($editor)->patchJson("/api/v1/imports/{$batchId}/rows/{$new['id']}", ['resolution' => 'create_new'])->assertOk();

    $this->actingAs($editor)->postJson("/api/v1/imports/{$batchId}/commit")->assertOk()
        ->assertJsonPath('data.status', 'committed');

    expect(Holder::count())->toBe(2);
});

it('imports archive items metadata-only, always draft/institution_only, and links the resolved artist', function () {
    $editor = editorUser();
    $artist = Artist::factory()->published()->create(['legacy_code' => 'AR900']);

    $csv = "legacy_ref,item_type,title_en,artist_code,content\n"
        ."TEST_ARC_001,article,A newly imported article,AR900,1990\n";
    $file = UploadedFile::fake()->createWithContent('archive.csv', $csv);

    $response = $this->actingAs($editor)->postJson('/api/v1/imports', [
        'entity_type' => 'archive_item',
        'column_map' => [
            'legacy_ref' => 'legacy_ref',
            'item_type' => 'item_type',
            'title_en' => 'title_en',
            'artist_code' => 'artist_code',
            'content' => 'content',
        ],
        'file' => $file,
    ])->assertCreated()
        ->assertJsonPath('data.new_count', 1);

    $batchId = $response->json('data.id');
    $row = collect($this->actingAs($editor)->getJson("/api/v1/imports/{$batchId}/rows")->json('data'))->first();

    $this->actingAs($editor)->patchJson("/api/v1/imports/{$batchId}/rows/{$row['id']}", ['resolution' => 'create_new'])->assertOk();
    $this->actingAs($editor)->postJson("/api/v1/imports/{$batchId}/commit")->assertOk();

    $item = ArchiveItem::with('links')->where('legacy_ref', 'TEST_ARC_001')->first();

    expect($item)->not->toBeNull()
        ->and($item->publication_status)->toBe('draft')
        ->and($item->access_level)->toBe('institution_only')
        ->and($item->links)->toHaveCount(1)
        ->and($item->links->first()->linkable_id)->toBe($artist->id);
});

<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('rejects artwork rows whose Recommended Use is not Inventory', function () {
    $editor = editorUser();

    $csv = "Title EN,Type of Artwork,RECOMMENDED USE\n"
        ."Inventory Piece,painting,Inventory\n"
        ."Pending Piece,painting,tbc\n"
        ."Blank Use Piece,painting,\n";
    $file = UploadedFile::fake()->createWithContent('artworks.csv', $csv);

    $response = $this->actingAs($editor)->postJson('/api/v1/imports', [
        'entity_type' => 'artwork',
        'column_map' => [
            'Title EN' => 'title_en',
            'Type of Artwork' => 'category',
            'RECOMMENDED USE' => 'recommended_use',
        ],
        'file' => $file,
    ]);

    $response->assertCreated()->assertJsonPath('data.row_count', 3);

    $batchId = $response->json('data.id');
    $rows = $this->actingAs($editor)->getJson("/api/v1/imports/{$batchId}/rows")->json('data');

    $inventoryRow = collect($rows)->firstWhere('raw_data.RECOMMENDED USE', 'Inventory');
    $pendingRow = collect($rows)->firstWhere('raw_data.RECOMMENDED USE', 'tbc');
    $blankRow = collect($rows)->firstWhere('raw_data.RECOMMENDED USE', '');

    expect($inventoryRow['validation_errors'])->toBe([]);
    expect($blankRow['validation_errors'])->toBe([]);

    expect($pendingRow['validation_errors'])->toHaveCount(1);
    expect($pendingRow['validation_errors'][0]['field'])->toBe('recommended_use');
});

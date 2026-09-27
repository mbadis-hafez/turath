<?php

use App\Models\Artwork;
use App\Models\ArtworkImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('attaches every file in a batch and reports a per-file result for each', function () {
    $editor = editorUser();
    $artwork = Artwork::factory()->create();

    $files = [
        UploadedFile::fake()->image('a.jpg', 100, 100),
        UploadedFile::fake()->image('b.jpg', 200, 150),
        UploadedFile::fake()->image('c.jpg', 300, 200),
    ];

    $response = $this->actingAs($editor)
        ->post("/api/v1/artworks/{$artwork->id}/images", ['images' => $files], ['Accept' => 'application/json'])
        ->assertCreated();

    expect($response->json('data'))->toHaveCount(3);

    $results = $response->json('results');
    expect($results)->toHaveCount(3);
    foreach ($results as $r) {
        expect($r['status'])->toBe('attached')
            ->and($r['image_id'])->not->toBeNull();
    }

    expect(ArtworkImage::where('artwork_id', $artwork->id)->count())->toBe(3);
});

it('rejects a batch that would push an artwork past the 20-image cap, attaching nothing', function () {
    $editor = editorUser();
    $artwork = Artwork::factory()->create();

    for ($i = 0; $i < 18; $i++) {
        ArtworkImage::create([
            'artwork_id' => $artwork->id,
            'path' => "artwork-images/filler{$i}.jpg",
            'original_filename' => "filler{$i}.jpg",
            'mime_type' => 'image/jpeg',
            'size_bytes' => 100,
            'sha256' => hash('sha256', "filler{$i}"),
            'width_px' => 50,
            'height_px' => 50,
            'rights_status' => 'unknown',
        ]);
    }

    $files = array_map(fn ($i) => UploadedFile::fake()->image("extra{$i}.jpg", 50, 50), range(1, 3));

    $this->actingAs($editor)
        ->post("/api/v1/artworks/{$artwork->id}/images", ['images' => $files], ['Accept' => 'application/json'])
        ->assertStatus(422);

    expect(ArtworkImage::where('artwork_id', $artwork->id)->count())->toBe(18);
});

it('blocks a byte-identical duplicate and names the image it matches, without discarding its siblings', function () {
    $editor = editorUser();
    $artwork = Artwork::factory()->create();

    $original = UploadedFile::fake()->image('original.jpg', 100, 100);
    $bytes = file_get_contents($original->getRealPath());

    $first = $this->actingAs($editor)
        ->post("/api/v1/artworks/{$artwork->id}/images", ['image' => $original], ['Accept' => 'application/json'])
        ->assertCreated()->json('data.0');

    $duplicateOfExisting = UploadedFile::fake()->createWithContent('same-content-renamed.jpg', $bytes);
    $freshImage = UploadedFile::fake()->image('fresh.jpg', 150, 150);

    $response = $this->actingAs($editor)
        ->post("/api/v1/artworks/{$artwork->id}/images", [
            'images' => [$duplicateOfExisting, $freshImage],
        ], ['Accept' => 'application/json'])
        ->assertCreated();

    $results = collect($response->json('results'));
    $duplicateResult = $results->firstWhere('filename', 'same-content-renamed.jpg');
    $freshResult = $results->firstWhere('filename', 'fresh.jpg');

    expect($duplicateResult['status'])->toBe('duplicate')
        ->and($duplicateResult['image_id'])->toBe($first['id'])
        ->and($freshResult['status'])->toBe('attached');

    // Exactly 2 images on the artwork: the original and the fresh one — the duplicate never attached.
    expect(ArtworkImage::where('artwork_id', $artwork->id)->count())->toBe(2);
});

it('blocks two byte-identical files submitted in the same batch, keeping only one', function () {
    $editor = editorUser();
    $artwork = Artwork::factory()->create();

    $one = UploadedFile::fake()->image('twin-a.jpg', 100, 100);
    $bytes = file_get_contents($one->getRealPath());
    $two = UploadedFile::fake()->createWithContent('twin-b.jpg', $bytes);

    $response = $this->actingAs($editor)
        ->post("/api/v1/artworks/{$artwork->id}/images", ['images' => [$one, $two]], ['Accept' => 'application/json'])
        ->assertCreated();

    expect(ArtworkImage::where('artwork_id', $artwork->id)->count())->toBe(1);

    $results = collect($response->json('results'));
    expect($results->where('status', 'attached'))->toHaveCount(1);
    expect($results->where('status', 'duplicate'))->toHaveCount(1);
});

it('attaches valid files from a mixed batch and names the rejected one, without failing the whole request', function () {
    $editor = editorUser();
    $artwork = Artwork::factory()->create();

    $files = [
        UploadedFile::fake()->image('good-a.jpg', 100, 100),
        UploadedFile::fake()->image('good-b.jpg', 120, 90),
        UploadedFile::fake()->create('notes.pdf', 10),
    ];

    $response = $this->actingAs($editor)
        ->post("/api/v1/artworks/{$artwork->id}/images", ['images' => $files], ['Accept' => 'application/json'])
        ->assertCreated();

    expect(ArtworkImage::where('artwork_id', $artwork->id)->count())->toBe(2);

    $results = collect($response->json('results'));
    expect($results->where('status', 'attached'))->toHaveCount(2);

    $rejected = $results->firstWhere('filename', 'notes.pdf');
    expect($rejected['status'])->toBe('rejected')
        ->and($rejected['message'])->not->toBeNull();
});

it('rejects the whole request when every file in the batch is invalid', function () {
    $editor = editorUser();
    $artwork = Artwork::factory()->create();

    $this->actingAs($editor)
        ->post("/api/v1/artworks/{$artwork->id}/images", [
            'images' => [UploadedFile::fake()->create('a.pdf', 10), UploadedFile::fake()->create('b.txt', 10)],
        ], ['Accept' => 'application/json'])
        ->assertStatus(422);

    expect(ArtworkImage::where('artwork_id', $artwork->id)->count())->toBe(0);
});

it('still accepts the legacy single-file field with the unchanged data shape', function () {
    $editor = editorUser();
    $artwork = Artwork::factory()->create();

    $response = $this->actingAs($editor)
        ->post("/api/v1/artworks/{$artwork->id}/images", [
            'image' => UploadedFile::fake()->image('solo.jpg', 100, 100),
            'rights_status' => 'licensed',
        ], ['Accept' => 'application/json'])
        ->assertCreated();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.rights_status'))->toBe('licensed');
});

it('rejects a request that sends both images[] and image', function () {
    $editor = editorUser();
    $artwork = Artwork::factory()->create();

    $this->actingAs($editor)
        ->post("/api/v1/artworks/{$artwork->id}/images", [
            'image' => UploadedFile::fake()->image('a.jpg', 50, 50),
            'images' => [UploadedFile::fake()->image('b.jpg', 50, 50)],
        ], ['Accept' => 'application/json'])
        ->assertStatus(422);
});

it('gives the first image attached to an artwork with none the primary designation automatically', function () {
    $editor = editorUser();
    $artwork = Artwork::factory()->create();

    $response = $this->actingAs($editor)
        ->post("/api/v1/artworks/{$artwork->id}/images", ['image' => UploadedFile::fake()->image('a.jpg', 50, 50)], ['Accept' => 'application/json'])
        ->assertCreated();

    expect($response->json('data.0.is_final'))->toBeTrue();
});

it('produces exactly one primary among a batch attached to an artwork with none', function () {
    $editor = editorUser();
    $artwork = Artwork::factory()->create();

    $files = [
        UploadedFile::fake()->image('a.jpg', 50, 50),
        UploadedFile::fake()->image('b.jpg', 60, 50),
        UploadedFile::fake()->image('c.jpg', 70, 50),
    ];

    $response = $this->actingAs($editor)
        ->post("/api/v1/artworks/{$artwork->id}/images", ['images' => $files], ['Accept' => 'application/json'])
        ->assertCreated();

    expect(collect($response->json('results'))->where('status', 'attached'))->toHaveCount(3);
    expect(ArtworkImage::where('artwork_id', $artwork->id)->where('is_final', true)->count())->toBe(1);
});

it('promotes the oldest remaining image to primary when the primary is deleted', function () {
    $editor = editorUser();
    $artwork = Artwork::factory()->create();

    $first = $this->actingAs($editor)->post("/api/v1/artworks/{$artwork->id}/images", ['image' => UploadedFile::fake()->image('a.jpg', 50, 50)], ['Accept' => 'application/json'])->json('data.0');
    // images() orders is_final DESC — $first is still primary here, so it's
    // data.0 and the new upload is data.1, not the other way round.
    $second = $this->actingAs($editor)->post("/api/v1/artworks/{$artwork->id}/images", ['image' => UploadedFile::fake()->image('b.jpg', 60, 50)], ['Accept' => 'application/json'])->json('data.1');

    expect($first['is_final'])->toBeTrue();

    $this->actingAs($editor)->deleteJson("/api/v1/artworks/{$artwork->id}/images/{$first['id']}")->assertOk();

    $remaining = ArtworkImage::where('artwork_id', $artwork->id)->first();
    expect($remaining->id)->toBe($second['id'])
        ->and($remaining->is_final)->toBeTrue();
});

it('leaves no primary and no images when the last image is removed', function () {
    $editor = editorUser();
    $artwork = Artwork::factory()->create();

    $only = $this->actingAs($editor)->post("/api/v1/artworks/{$artwork->id}/images", ['image' => UploadedFile::fake()->image('a.jpg', 50, 50)], ['Accept' => 'application/json'])->json('data.0');

    $this->actingAs($editor)->deleteJson("/api/v1/artworks/{$artwork->id}/images/{$only['id']}")->assertOk();

    expect(ArtworkImage::where('artwork_id', $artwork->id)->count())->toBe(0);
});

it('never changes publication status when attaching, and a newly attached unknown-rights image on a draft artwork stays hidden from anonymous requesters', function () {
    $editor = editorUser();
    $artwork = Artwork::factory()->create(['publication_status' => 'draft']);

    $image = $this->actingAs($editor)
        ->post("/api/v1/artworks/{$artwork->id}/images", ['image' => UploadedFile::fake()->image('a.jpg', 50, 50)], ['Accept' => 'application/json'])
        ->assertCreated()->json('data.0');

    expect($artwork->refresh()->publication_status)->toBe('draft')
        ->and($image['rights_status'])->toBe('unknown');

    Auth::forgetGuards();
    $this->getJson($image['url'])->assertNotFound();
});

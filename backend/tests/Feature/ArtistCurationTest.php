<?php

use App\Models\ArchiveItem;
use App\Models\ArchiveItemLink;
use App\Models\Artist;
use App\Models\ArtistMerge;
use App\Models\Artwork;
use App\Models\FieldCitation;
use App\Models\Theme;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

it('defaults new artists to not_started pipeline and unspecified bio source', function () {
    $artist = Artist::factory()->create()->refresh();

    expect($artist->authorization_letter_status)->toBe('not_started')
        ->and($artist->owner_pre_agreement_status)->toBe('not_started')
        ->and($artist->bio_source_type)->toBe('unspecified');
});

it('never exposes internal-only fields publicly and encrypts contact data at rest', function () {
    $artist = Artist::factory()->published()->create([
        'key_contact_name' => 'Secret Person', 'owner_type' => 'heir_or_estate',
        'contact_email' => 'secret@example.com', 'contact_phone' => '+966500000000',
        'ref_supervisor_note' => 'Fatima', 'authorization_letter_status' => 'signed',
    ]);

    $body = json_encode($this->getJson("/api/v1/artists/{$artist->slug}")->assertOk()->json());
    $list = json_encode($this->getJson('/api/v1/artists')->assertOk()->json());

    foreach (['Secret Person', 'secret@example.com', '+966500000000', 'heir_or_estate', 'Fatima', 'authorization_letter', 'contact_'] as $needle) {
        expect($body)->not->toContain($needle)->and($list)->not->toContain($needle);
    }
    expect($artist->search_text)->not->toContain('secret');

    $raw = DB::table('artists')->where('id', $artist->id)->value('contact_email');
    expect($raw)->not->toContain('secret@example.com');
});

it('shows contact data only through the curation endpoint, to editors', function () {
    $artist = Artist::factory()->create(['key_contact_name' => 'Ahmad', 'contact_email' => 'a@b.co']);

    $this->actingAs(makeUser('reader'))->getJson("/api/v1/artists/{$artist->id}/curation")->assertForbidden();
    $this->actingAs(editorUser())->getJson("/api/v1/artists/{$artist->id}/curation")
        ->assertOk()->assertJsonPath('data.contact.contact_email', 'a@b.co');
});

it('audits contact changes by field name without leaking values', function () {
    $artist = Artist::factory()->create();

    $this->actingAs(editorUser())->patchJson("/api/v1/artists/{$artist->id}/curation", [
        'contact_email' => 'x@y.co', 'key_contact_name' => 'Nora', 'edit_summary' => 'call notes',
    ])->assertOk();

    $entries = Activity::where('subject_type', Artist::class)->where('subject_id', $artist->id)->get();
    expect(json_encode($entries->map->properties))->not->toContain('x@y.co')
        ->and(json_encode($entries->map->properties))->toContain('contact_fields_changed')
        ->and(json_encode($entries->map->attribute_changes))->toContain('Nora');
});

it('derives name_verified from a citation and public visibility from the verify gate', function () {
    $editor = editorUser();
    $artist = Artist::factory()->create(['living_status' => 'living']);

    $bundle = $this->actingAs($editor)->getJson("/api/v1/artists/{$artist->id}/curation")->json('data');
    expect(collect($bundle['checklist'])->firstWhere('key', 'name_verified')['met'])->toBeFalse()
        ->and($bundle['public_visibility'])->toBe('hidden');

    FieldCitation::factory()->create(['citable_type' => Artist::class, 'citable_id' => $artist->id, 'field_key' => 'name']);
    $bundle = $this->actingAs($editor)->getJson("/api/v1/artists/{$artist->id}/curation")->json('data');
    expect(collect($bundle['checklist'])->firstWhere('key', 'name_verified')['met'])->toBeTrue();
});

it('refuses verification with one itemized list and succeeds when all three conditions clear', function () {
    $editor = editorUser();
    $artist = Artist::factory()->create(['living_status' => 'living']);

    $errors = $this->actingAs($editor)->postJson("/api/v1/artists/{$artist->id}/verify", ['status' => 'verified'])
        ->assertStatus(422)->json('errors');
    expect($errors)->toHaveKeys(['data.primary_source', 'pipeline.authorization_letter', 'pipeline.owner_pre_agreement']);

    FieldCitation::factory()->create(['citable_type' => Artist::class, 'citable_id' => $artist->id, 'field_key' => 'name']);
    $this->actingAs($editor)->patchJson("/api/v1/artists/{$artist->id}/curation", ['authorization_letter_status' => 'signed', 'owner_pre_agreement_status' => 'not_applicable'])->assertOk();

    $this->actingAs($editor)->getJson("/api/v1/artists/{$artist->id}/curation")->assertJsonPath('data.public_visibility', 'visible');
    $this->actingAs($editor)->postJson("/api/v1/artists/{$artist->id}/verify", ['status' => 'verified'])->assertOk();
});

it('assigns themes, audits it, and filters the registry', function () {
    $editor = editorUser();
    $theme = Theme::create(['label_ar' => 'الطبيعة والمكان', 'label_en' => 'Nature and place']);
    $tagged = Artist::factory()->create(['owner_type' => 'gallery']);
    Artist::factory()->create();

    $this->actingAs($editor)->patchJson("/api/v1/artists/{$tagged->id}/themes", ['theme_ids' => [$theme->id]])->assertOk();
    expect(Activity::where('subject_type', Artist::class)->where('description', 'themes changed')->exists())->toBeTrue();

    $this->actingAs($editor)->getJson("/api/v1/admin/artists?theme_id={$theme->id}&owner_type=gallery&unverified=1")
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $tagged->id);
    $this->actingAs(makeUser('reader'))->getJson('/api/v1/admin/artists')->assertForbidden();
});

it('merges artists: re-points artworks, links, citations, variants and leaves a redirect', function () {
    $editor = editorUser();
    $survivor = Artist::factory()->published()->create(['name_en' => 'Survivor One', 'bio_en' => null]);
    $duplicate = Artist::factory()->published()->create(['name_en' => 'Duplicate One', 'bio_en' => 'Real bio']);
    $duplicate->variants()->create(['name' => 'Dup Alias', 'type' => 'alias', 'language' => 'en']);
    $artwork = Artwork::factory()->create(['artist_id' => $duplicate->id]);
    $item = ArchiveItem::factory()->create();
    ArchiveItemLink::create(['archive_item_id' => $item->id, 'linkable_type' => Artist::class, 'linkable_id' => $duplicate->id, 'role' => 'subject']);
    FieldCitation::factory()->create(['citable_type' => Artist::class, 'citable_id' => $duplicate->id, 'field_key' => 'name']);

    $this->actingAs($editor)->postJson('/api/v1/artists/merge', [
        'survivor_id' => $survivor->id, 'duplicate_id' => $duplicate->id, 'field_resolution' => ['bio_en' => 'duplicate'],
    ])->assertCreated();

    expect($survivor->refresh()->bio_en)->toBe('Real bio')
        ->and($artwork->refresh()->artist_id)->toBe($survivor->id)
        ->and(ArchiveItemLink::where('linkable_id', $survivor->id)->where('linkable_type', Artist::class)->count())->toBe(1)
        ->and(FieldCitation::where('citable_id', $survivor->id)->where('citable_type', Artist::class)->count())->toBe(1)
        ->and($survivor->variants()->where('name', 'Dup Alias')->exists())->toBeTrue()
        ->and(Artist::withTrashed()->find($duplicate->id)->trashed())->toBeTrue()
        ->and(Activity::where('subject_type', ArtistMerge::class)->exists())->toBeTrue();

    $this->getJson("/api/v1/artists/{$duplicate->slug}")->assertStatus(301)->assertRedirect("/api/v1/artists/{$survivor->slug}");
});

it('includes city, life dates and material years in the curation bundle', function () {
    $artist = Artist::factory()->create(['birth_place_en' => 'Alahsa', 'birth_place_ar' => 'الأحساء', 'birth_year_from' => 1939, 'birth_year_to' => 1939]);
    $item = ArchiveItem::factory()->create(['content_year_from' => 1978, 'content_year_to' => 1978]);
    ArchiveItemLink::create(['archive_item_id' => $item->id, 'linkable_type' => Artist::class, 'linkable_id' => $artist->id, 'role' => 'subject']);

    $data = $this->actingAs(editorUser())->getJson("/api/v1/artists/{$artist->id}/curation")->assertOk()->json('data');

    expect($data['city']['en'])->toBe('Alahsa')
        ->and($data['life_dates']['birth'])->toBe('1939')
        ->and($data['life_dates']['death'])->toBeNull()
        ->and($data['linked_materials'][0]['year'])->toBe('1978');
});

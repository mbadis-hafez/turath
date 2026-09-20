<?php

use App\Models\ArchiveItem;
use App\Models\ArchiveItemLink;
use App\Models\Artist;
use App\Models\ArtistEntry;
use App\Models\ArtistMerge;
use App\Models\Artwork;
use App\Models\FieldCitation;
use App\Models\Theme;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

it('defaults new artists to not_started pipeline and unspecified bio source', function () {
    $artist = Artist::factory()->create()->refresh();

    expect($artist->authorization_letter_status)->toBe('not_started')
        ->and($artist->owner_pre_agreement_status)->toBe('not_started')
        ->and($artist->bio_source_type)->toBe('unspecified');
});

it('never exposes internal-only fields publicly and encrypts contact data at rest', function () {
    $artist = Artist::factory()->published()->create([
        'owner_type' => 'heir_or_estate', 'ref_supervisor_note' => 'Fatima', 'authorization_letter_status' => 'signed',
    ]);
    $artist->contacts()->create(['name' => 'Secret Person', 'email' => 'secret@example.com', 'phone' => '+966500000000']);
    $artist->socialLinks()->create(['platform' => 'instagram', 'url' => 'https://instagram.com/private_one', 'is_public' => false]);

    $body = json_encode($this->getJson("/api/v1/artists/{$artist->slug}")->assertOk()->json());
    $list = json_encode($this->getJson('/api/v1/artists')->assertOk()->json());

    foreach (['Secret Person', 'secret@example.com', '+966500000000', 'heir_or_estate', 'Fatima', 'authorization_letter', 'contact_', 'private_one'] as $needle) {
        expect($body)->not->toContain($needle)->and($list)->not->toContain($needle);
    }
    expect($artist->search_text)->not->toContain('secret');

    $raw = DB::table('artist_contacts')->where('artist_id', $artist->id)->value('email');
    expect($raw)->not->toContain('secret@example.com');
});

it('shows contact data only through the curation endpoint, to editors', function () {
    $artist = Artist::factory()->create();
    $artist->contacts()->create(['name' => 'Ahmad', 'email' => 'a@b.co']);

    $this->actingAs(makeUser('reader'))->getJson("/api/v1/artists/{$artist->id}/curation")->assertForbidden();
    $this->actingAs(editorUser())->getJson("/api/v1/artists/{$artist->id}/curation")
        ->assertOk()->assertJsonPath('data.contacts.0.email', 'a@b.co');
});

it('audits contact changes by field name without leaking values', function () {
    $artist = Artist::factory()->create();

    $this->actingAs(editorUser())->patchJson("/api/v1/artists/{$artist->id}/curation", [
        'contacts' => [['name' => 'Nora', 'email' => 'x@y.co'], ['name' => 'Second', 'phone' => '+1 555']],
        'owner_type' => 'gallery', 'edit_summary' => 'call notes',
    ])->assertOk()->assertJsonCount(2, 'data.contacts');

    $entries = Activity::where('subject_type', Artist::class)->where('subject_id', $artist->id)->get();
    expect(json_encode($entries->map->properties))->not->toContain('x@y.co')
        ->and(json_encode($entries->map->properties))->not->toContain('+1 555')
        ->and(json_encode($entries->map->properties))->toContain('contacts_changed')
        ->and(json_encode($entries->map->attribute_changes))->toContain('gallery');
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

it('supports multiple contacts, updating in place and removing dropped ones', function () {
    $editor = editorUser();
    $artist = Artist::factory()->create();
    $url = "/api/v1/artists/{$artist->id}/curation";

    $first = $this->actingAs($editor)->patchJson($url, ['contacts' => [['name' => 'A', 'email' => 'a@x.co'], ['name' => 'B', 'phone' => '1']]])->json('data.contacts');
    expect($first)->toHaveCount(2);

    $second = $this->actingAs($editor)->patchJson($url, ['contacts' => [['id' => $first[0]['id'], 'name' => 'A2', 'email' => 'a@x.co']]])->json('data.contacts');
    expect($second)->toHaveCount(1)->and($second[0]['id'])->toBe($first[0]['id'])->and($second[0]['name'])->toBe('A2');
});

it('counts a contact as met only with a name and a way to reach them', function () {
    $editor = editorUser();
    $artist = Artist::factory()->create();
    $met = fn () => collect($this->actingAs($editor)->getJson("/api/v1/artists/{$artist->id}/curation")->json('data.checklist'))->firstWhere('key', 'contact')['met'];

    expect($met())->toBeFalse();
    $artist->contacts()->create(['name' => 'Only name']);
    expect($met())->toBeFalse();
    $artist->contacts()->create(['name' => 'Reachable', 'phone' => '1']);
    expect($met())->toBeTrue();
});

it('shows nationality, classification, dates, education and activities (awards, exhibitions, talks) publicly, plus only public social links', function () {
    $editor = editorUser();
    $artist = Artist::factory()->published()->create();

    $this->actingAs($editor)->patchJson("/api/v1/artists/{$artist->id}", [
        'nationality' => ['ar' => 'سعودي', 'en' => 'Saudi'],
        'classification' => ['ar' => 'رائد', 'en' => 'Pioneer'],
        'birth' => ['display' => '1939-05-01', 'year_from' => 1939, 'year_to' => 1939, 'calendar' => 'gregorian', 'certainty' => 'exact'],
        'death' => ['display' => '2016-01-02', 'year_from' => 2016, 'year_to' => 2016, 'calendar' => 'gregorian', 'certainty' => 'exact'],
    ])->assertOk();

    $this->actingAs($editor)->putJson("/api/v1/artists/{$artist->id}/entries", [
        'educations' => [['title' => ['en' => 'BFA'], 'place' => ['en' => 'Cairo Academy'], 'year_from' => 1960, 'year_to' => 1964]],
        'activities' => [
            ['type' => 'award', 'title' => ['ar' => 'جائزة', 'en' => 'Prize'], 'place' => ['en' => 'Ministry'], 'year_from' => 1988],
            ['type' => 'exhibition', 'title' => ['en' => 'Solo show'], 'place' => ['en' => 'Dar Al-Funun'], 'year_from' => 1978],
            ['type' => 'talk', 'title' => ['en' => 'Artist talk'], 'year_from' => 1980],
        ],
    ])->assertOk();
    $this->actingAs($editor)->putJson("/api/v1/artists/{$artist->id}/social-links", ['links' => [
        ['platform' => 'instagram', 'url' => 'https://instagram.com/pub', 'is_public' => true],
        ['platform' => 'x', 'url' => 'https://x.com/priv'],
    ]])->assertOk();

    $public = $this->getJson("/api/v1/artists/{$artist->slug}")->assertOk()->json('data');

    expect($public['nationality']['en'])->toBe('Saudi')
        ->and($public['classification']['en'])->toBe('Pioneer')
        ->and($public['birth']['display'])->toBe('1939-05-01')
        ->and($public['death']['year_from'])->toBe(2016)
        ->and($public['educations'])->toHaveCount(1)
        ->and($public['educations'][0]['year_to'])->toBe(1964)
        ->and($public['activities'][0]['title']['ar'])->toBe('جائزة')
        ->and($public['activities'])->toHaveCount(3)
        ->and($public['social_links'])->toHaveCount(1)
        ->and(json_encode($public))->not->toContain('priv');
});

it('validates entry years and social link urls', function () {
    $artist = Artist::factory()->create();
    $editor = editorUser();

    $this->actingAs($editor)->putJson("/api/v1/artists/{$artist->id}/entries", ['educations' => [['title' => ['en' => 'X'], 'year_from' => 1990, 'year_to' => 1980]]])->assertStatus(422);
    $this->actingAs($editor)->putJson("/api/v1/artists/{$artist->id}/social-links", ['links' => [['platform' => 'instagram', 'url' => 'not a url']]])->assertStatus(422);
    $this->actingAs(makeUser('reader'))->putJson("/api/v1/artists/{$artist->id}/entries", ['activities' => []])->assertForbidden();
});

it('syncs entries in place: unchanged rows stay unchanged and dropped rows are deleted', function () {
    $editor = editorUser();
    $artist = Artist::factory()->create();
    $url = "/api/v1/artists/{$artist->id}/entries";

    $rows = $this->actingAs($editor)->putJson($url, ['activities' => [['type' => 'award', 'title' => ['en' => 'One']], ['type' => 'symposium', 'title' => ['en' => 'Two']]]])->json('data.activities');
    $before = Activity::where('subject_type', ArtistEntry::class)->count();

    $this->actingAs($editor)->putJson($url, ['activities' => [['id' => $rows[0]['id'], 'type' => 'award', 'title' => ['en' => 'One']]]])->assertOk()->assertJsonCount(1, 'data.activities');

    expect(Activity::where('subject_type', ArtistEntry::class)->count())->toBe($before + 1); // only the delete
});

it('uploads a portrait, keeps it private until rights are clear and the artist is published, then serves it', function () {
    Storage::fake('local');
    $editor = editorUser();
    $artist = Artist::factory()->published()->create();

    $this->actingAs($editor)->post("/api/v1/artists/{$artist->id}/portrait", [
        'image' => UploadedFile::fake()->image('p.jpg', 200, 200),
    ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.rights_status', 'unknown');

    Auth::forgetGuards();
    expect($this->getJson("/api/v1/artists/{$artist->slug}")->json('data.portrait_url'))->toBeNull();
    $this->getJson("/api/v1/artists/{$artist->id}/portrait")->assertNotFound();
    $this->actingAs($editor)->get("/api/v1/artists/{$artist->id}/portrait")->assertOk();

    $this->actingAs($editor)->patchJson("/api/v1/artists/{$artist->id}/portrait", ['rights_status' => 'licensed'])->assertOk();

    Auth::forgetGuards();
    expect($this->getJson("/api/v1/artists/{$artist->slug}")->json('data.portrait_url'))->toBe("/api/v1/artists/{$artist->id}/portrait");
    $this->get("/api/v1/artists/{$artist->id}/portrait")->assertOk();

    $checklist = collect($this->actingAs($editor)->getJson("/api/v1/artists/{$artist->id}/curation")->json('data.checklist'))->firstWhere('key', 'portrait');
    expect($checklist['met'])->toBeTrue()->and($checklist['supported'])->toBeTrue();

    $this->actingAs($editor)->deleteJson("/api/v1/artists/{$artist->id}/portrait")->assertOk()->assertJsonPath('data.has_portrait', false);
});

it('rejects non-image portrait uploads', function () {
    $artist = Artist::factory()->create();

    $this->actingAs(editorUser())->post("/api/v1/artists/{$artist->id}/portrait", [
        'image' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
    ], ['Accept' => 'application/json'])->assertStatus(422);
});

it('stores a contact address encrypted and never in the audit log', function () {
    $editor = editorUser();
    $artist = Artist::factory()->create();

    $this->actingAs($editor)->patchJson("/api/v1/artists/{$artist->id}/curation", [
        'contacts' => [['name' => 'Abdullah', 'address' => 'Al Rawand Street, Al Khawther, Saihat']],
    ])->assertOk();

    expect(DB::table('artist_contacts')->where('artist_id', $artist->id)->value('address'))->not->toContain('Rawand');
    expect(json_encode(Activity::all()->toArray()))->not->toContain('Rawand');
    $this->actingAs($editor)->getJson("/api/v1/artists/{$artist->id}/curation")->assertJsonPath('data.contacts.0.address', 'Al Rawand Street, Al Khawther, Saihat');
});

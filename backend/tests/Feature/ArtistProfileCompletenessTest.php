<?php

use App\Models\Artist;
use App\Models\Artwork;
use App\Models\FieldCitation;
use App\Models\Source;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/*
 * The artist profile completeness contract: 11 uniformly weighted core
 * requirements (identity ×8, biography ×2, media ×1), no citation
 * requirements, independent of verification, publication, ownership and
 * assignment concerns.
 */

it('scores a fully filled artist at 100 percent', function () {
    $artist = Artist::factory()->complete()->create();

    $row = DB::table('record_completeness')->where('citable_type', Artist::class)->where('citable_id', $artist->id)->first();

    expect($row->completeness_pct)->toBe(100)
        ->and(json_decode($row->blocking_gap_field_keys, true))->toBe([])
        ->and($row->severity)->toBe('clear');
});

it('lowers the percentage when any one of the 11 requirements is removed', function () {
    $removals = [
        'artist_code' => ['legacy_code' => null],
        'name_ar' => ['name_ar' => null],
        'name_en' => ['name_en' => ''],
        'birth_city' => ['birth_place_ar' => null, 'birth_place_en' => null],
        'living_status_known' => ['living_status' => 'unknown', 'death_year_from' => 2000],
        'birth_year' => ['birth_year_from' => null],
        'death_year_or_living' => ['living_status' => 'deceased', 'death_year_from' => null],
        'nationality' => ['nationality_ar' => null, 'nationality_en' => null],
        'bio_ar' => ['bio_ar' => '   '],
        'bio_en' => ['bio_en' => null],
        'portrait_with_clear_rights' => ['portrait_rights_status' => 'unknown'],
    ];

    foreach ($removals as $expectedKey => $attributes) {
        $artist = Artist::factory()->complete()->create($attributes);
        $row = DB::table('record_completeness')->where('citable_type', Artist::class)->where('citable_id', $artist->id)->firstOrFail();

        expect($row->completeness_pct)->toBe((int) round(10 / 11 * 100))
            ->and(json_decode($row->blocking_gap_field_keys, true))->toContain($expectedKey);
    }
});

it('lets a living artist without a death year reach 100 percent', function () {
    $artist = Artist::factory()->complete()->create(['living_status' => 'living', 'death_year_from' => null]);

    expect(DB::table('record_completeness')->where('citable_id', $artist->id)->value('completeness_pct'))->toBe(100);
});

it('requires a death year for a deceased artist to reach 100 percent', function () {
    $gap = Artist::factory()->complete()->create(['living_status' => 'deceased', 'death_year_from' => null]);
    expect(DB::table('record_completeness')->where('citable_id', $gap->id)->value('completeness_pct'))->toBe((int) round(10 / 11 * 100));

    $filled = Artist::factory()->complete()->create(['living_status' => 'deceased', 'death_year_from' => 2000]);
    expect(DB::table('record_completeness')->where('citable_id', $filled->id)->value('completeness_pct'))->toBe(100);
});

it('ignores educations, activities and social links entirely', function () {
    $editor = editorUser();
    $artist = Artist::factory()->complete()->create();

    $this->actingAs($editor)->putJson("/api/v1/artists/{$artist->id}/entries", [
        'educations' => [],
        'activities' => [],
    ])->assertOk();
    $this->actingAs($editor)->putJson("/api/v1/artists/{$artist->id}/social-links", ['links' => []])->assertOk();

    expect(DB::table('record_completeness')->where('citable_id', $artist->id)->value('completeness_pct'))->toBe(100);
});

it('keeps a complete citation-less artist at 100 and unverified: verification is independent', function () {
    $editor = editorUser();
    $artist = Artist::factory()->complete()->create(['authorization_letter_status' => 'signed', 'owner_pre_agreement_status' => 'yes']);

    expect(FieldCitation::where('citable_type', Artist::class)->where('citable_id', $artist->id)->count())->toBe(0)
        ->and(DB::table('record_completeness')->where('citable_id', $artist->id)->value('completeness_pct'))->toBe(100);

    // Deceased needs the death-year citation to verify — that is verification, not completeness.
    $deceased = Artist::factory()->complete()->create([
        'living_status' => 'deceased', 'death_year_from' => 2000,
        'authorization_letter_status' => 'signed', 'owner_pre_agreement_status' => 'yes',
    ]);
    $this->actingAs($editor)->postJson("/api/v1/artists/{$deceased->id}/verify", ['status' => 'verified'])
        ->assertUnprocessable()->assertJsonValidationErrors(['verification.death_year_citation']);

    FieldCitation::factory()->create([
        'citable_type' => Artist::class, 'citable_id' => $deceased->id,
        'field_key' => 'death_year', 'source_id' => Source::factory()->create()->id,
    ]);
    $this->actingAs($editor)->postJson("/api/v1/artists/{$deceased->id}/verify", ['status' => 'verified'])->assertOk();
});

it('never auto-publishes or auto-verifies at 100 percent', function () {
    $artist = Artist::factory()->complete()->create();

    expect($artist->refresh()->publication_status)->toBe('draft')
        ->and($artist->verified_status)->toBe('unverified');
});

it('is unchanged by assignment and supervisor notes', function () {
    $artist = Artist::factory()->complete()->create();
    $before = DB::table('record_completeness')->where('citable_id', $artist->id)->value('completeness_pct');

    $artist->update(['assigned_to_user_id' => editorUser()->id, 'ref_supervisor_note' => 'Follow up with the family.']);

    expect(DB::table('record_completeness')->where('citable_id', $artist->id)->value('completeness_pct'))->toBe($before);
});

it('is unchanged by the ownership pipeline statuses', function () {
    $artist = Artist::factory()->complete()->create([
        'authorization_letter_status' => 'not_started',
        'owner_pre_agreement_status' => 'not_started',
    ]);

    expect(DB::table('record_completeness')->where('citable_id', $artist->id)->value('completeness_pct'))->toBe(100);
});

it('accepts nationality in Arabic only and birth city in English only', function () {
    $nationalityOnly = Artist::factory()->complete()->create(['nationality_ar' => 'سعودي', 'nationality_en' => null]);
    expect(DB::table('record_completeness')->where('citable_id', $nationalityOnly->id)->value('completeness_pct'))->toBe(100);

    $cityOnly = Artist::factory()->complete()->create(['birth_place_ar' => null, 'birth_place_en' => 'Alahsa']);
    expect(DB::table('record_completeness')->where('citable_id', $cityOnly->id)->value('completeness_pct'))->toBe(100);
});

it('previews the completeness of an unsaved payload through the API', function () {
    $editor = editorUser();

    $response = $this->actingAs($editor)->postJson('/api/v1/artists/completeness-preview', [
        'legacy_code' => 'AR999',
        'name' => ['ar' => 'عبدالحليم رضوي', 'en' => 'Abdulhalim Radwi'],
        'bio' => ['ar' => 'سيرة الفنان', 'en' => 'Artist biography'],
        'living_status' => 'living',
        'birth' => ['year_from' => 1939],
        'birth_place' => ['ar' => 'مكة'],
        'nationality' => ['en' => 'Saudi'],
        'portrait_uploaded' => true,
        'portrait_rights_status' => 'licensed',
    ])->assertOk();

    expect($response->json('data.percentage'))->toBe(100)
        ->and($response->json('data.met_count'))->toBe(11)
        ->and($response->json('data.total_count'))->toBe(11)
        ->and($response->json('data.complete'))->toBeTrue()
        ->and($response->json('data.sections.identity.percentage'))->toBe(100)
        ->and($response->json('data.missing'))->toBe([]);

    $response = $this->actingAs($editor)->postJson('/api/v1/artists/completeness-preview', [
        'name' => ['en' => 'Abdulhalim Radwi'],
    ])->assertOk();

    expect($response->json('data.percentage'))->toBe((int) round(1 / 11 * 100))
        ->and(collect($response->json('data.missing'))->pluck('key'))->toContain('name_ar');

    Auth::forgetGuards();
    $this->postJson('/api/v1/artists/completeness-preview', ['name' => ['en' => 'X']])->assertUnauthorized();
});

it('adds the enriched keys for artists only', function () {
    $editor = editorUser();
    $artist = Artist::factory()->complete()->create();
    $artwork = Artwork::factory()->create();

    $response = $this->actingAs($editor)->getJson("/api/v1/records/artists/{$artist->id}/completeness")->assertOk();
    expect($response->json('data.completeness_pct'))->toBe(100)
        ->and($response->json('data.met_count'))->toBe(11)
        ->and($response->json('data.total_count'))->toBe(11)
        ->and($response->json('data.complete'))->toBeTrue()
        ->and($response->json('data.sections.identity'))->toBe(['met' => 8, 'total' => 8, 'percentage' => 100])
        ->and($response->json('data.sections.biography'))->toBe(['met' => 2, 'total' => 2, 'percentage' => 100])
        ->and($response->json('data.sections.media'))->toBe(['met' => 1, 'total' => 1, 'percentage' => 100])
        ->and($response->json('data.missing'))->toBe([])
        ->and($response->json('data'))->not->toHaveKey('percentage');

    $incomplete = Artist::factory()->create(['living_status' => 'unknown']);
    $response = $this->actingAs($editor)->getJson("/api/v1/records/artists/{$incomplete->id}/completeness")->assertOk();
    expect($response->json('data.complete'))->toBeFalse()
        ->and(collect($response->json('data.missing'))->first())->toMatchArray([
            'key' => 'artist_code',
            'section' => 'identity',
        ])
        ->and(collect($response->json('data.missing'))->first()['label'])->toHaveKeys(['ar', 'en']);

    $response = $this->actingAs($editor)->getJson("/api/v1/records/artworks/{$artwork->id}/completeness")->assertOk();
    expect($response->json('data'))->not->toHaveKeys(['met_count', 'total_count', 'complete', 'sections', 'missing'])
        ->and($response->json('data'))->toHaveKeys(['completeness_pct', 'severity', 'blocking_gaps', 'minor_gaps', 'open_conflicts', 'citations']);
});

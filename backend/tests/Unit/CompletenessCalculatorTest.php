<?php

use App\Models\Artist;
use App\Models\Artwork;
use App\Models\FieldCitation;
use App\Models\Source;
use App\Support\Completeness\CompletenessCalculator;

it('flags a citation-requiring core field as missing even when the column has a value', function () {
    $artist = Artist::factory()->create(['living_status' => 'deceased', 'death_year_from' => 1990]);

    $result = (new CompletenessCalculator)->evaluate($artist);

    expect($result['blocking'])->toContain('death_year_or_living_confirmed');
});

it('exempts a living artist from needing a death-year citation', function () {
    $artist = Artist::factory()->create(['living_status' => 'living', 'death_year_from' => null]);

    $result = (new CompletenessCalculator)->evaluate($artist);

    expect($result['blocking'])->not->toContain('death_year_or_living_confirmed');
});

it('satisfies a citation-requiring core field once a matching citation exists', function () {
    $artist = Artist::factory()->create(['living_status' => 'deceased', 'death_year_from' => 1990]);
    $source = Source::factory()->create();
    FieldCitation::factory()->create([
        'citable_type' => Artist::class,
        'citable_id' => $artist->id,
        'field_key' => 'death_year',
        'source_id' => $source->id,
        'claimed_value' => ['year' => 1990],
    ]);
    FieldCitation::factory()->create([
        'citable_type' => Artist::class,
        'citable_id' => $artist->id,
        'field_key' => 'primary_source',
        'source_id' => $source->id,
        'claimed_value' => ['ok' => true],
    ]);

    $result = (new CompletenessCalculator)->evaluate($artist);

    expect($result['blocking'])->toBe([])
        ->and($result['severity']->value)->not->toBe('blocking');
});

it('applies D56 severity precedence: blocking beats conflict beats minor beats clear', function () {
    $blockingArtist = Artist::factory()->create(['living_status' => 'deceased', 'death_year_from' => null]);
    expect((new CompletenessCalculator)->evaluate($blockingArtist)['severity']->value)->toBe('blocking');

    $minorOnlyArtist = Artist::factory()->create(['living_status' => 'living', 'birth_place_ar' => null, 'birth_place_en' => null, 'bio_en' => null]);
    Source::factory()->create();
    FieldCitation::factory()->create([
        'citable_type' => Artist::class,
        'citable_id' => $minorOnlyArtist->id,
        'field_key' => 'primary_source',
    ]);
    $result = (new CompletenessCalculator)->evaluate($minorOnlyArtist);
    expect($result['blocking'])->toBe([])
        ->and($result['minor'])->not->toBe([])
        ->and($result['severity']->value)->toBe('minor');
});

it('scores an artwork core field on holder presence', function () {
    $artwork = Artwork::factory()->create(['holder_id' => null]);

    $result = (new CompletenessCalculator)->evaluate($artwork);

    expect($result['blocking'])->toContain('holder');
});

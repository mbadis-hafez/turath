<?php

use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Holder;
use App\Models\SourceConflict;
use App\Support\Completeness\CompletenessCalculator;

it('requires no citation for completeness: a fully filled deceased artist with zero citations scores 100', function () {
    $artist = Artist::factory()->complete()->create(['living_status' => 'deceased', 'death_year_from' => 2000]);

    $result = (new CompletenessCalculator)->evaluate($artist);

    expect($result['blocking'])->toBe([])
        ->and($result['completeness_pct'])->toBe(100)
        ->and($result['severity']->value)->toBe('clear');
});

it('auto-satisfies the death-year requirement for a living artist', function () {
    $artist = Artist::factory()->complete()->create(['living_status' => 'living', 'death_year_from' => null]);

    $result = (new CompletenessCalculator)->evaluate($artist);

    expect($result['blocking'])->not->toContain('death_year_or_living')
        ->and($result['completeness_pct'])->toBe(100);
});

it('flags a deceased artist without a death year as a gap', function () {
    $artist = Artist::factory()->complete()->create(['living_status' => 'deceased', 'death_year_from' => null]);

    $result = (new CompletenessCalculator)->evaluate($artist);

    expect($result['blocking'])->toContain('death_year_or_living');
});

it('flags an unknown living_status as a gap even with a death year present', function () {
    $artist = Artist::factory()->complete()->create(['living_status' => 'unknown', 'death_year_from' => 2000]);

    $result = (new CompletenessCalculator)->evaluate($artist);

    expect($result['blocking'])->toContain('living_status_known')
        ->and($result['blocking'])->not->toContain('death_year_or_living');
});

it('applies D56 severity precedence: blocking beats conflict beats minor beats clear', function () {
    $blockingArtist = Artist::factory()->create(['living_status' => 'unknown']);
    expect((new CompletenessCalculator)->evaluate($blockingArtist)['severity']->value)->toBe('blocking');

    $clearArtist = Artist::factory()->complete()->create();
    expect((new CompletenessCalculator)->evaluate($clearArtist)['severity']->value)->toBe('clear');

    // Artwork still has minor-tier fields: holder met, dimensions/medium missing.
    $minorOnlyArtwork = Artwork::factory()->create(['holder_id' => Holder::factory()->create()->id, 'height_cm' => null, 'width_cm' => null, 'medium_ar' => null, 'medium_en' => null]);
    $result = (new CompletenessCalculator)->evaluate($minorOnlyArtwork);
    expect($result['blocking'])->toBe([])
        ->and($result['minor'])->toBe(['dimensions', 'medium'])
        ->and($result['severity']->value)->toBe('minor');

    $conflictedArtwork = Artwork::factory()->create(['holder_id' => $minorOnlyArtwork->holder_id, 'height_cm' => 60, 'width_cm' => 45, 'medium_en' => 'Oil on canvas']);
    SourceConflict::factory()->create(['citable_type' => Artwork::class, 'citable_id' => $conflictedArtwork->id, 'status' => 'open']);
    expect((new CompletenessCalculator)->evaluate($conflictedArtwork)['severity']->value)->toBe('conflict');
});

it('scores an artwork core field on holder presence', function () {
    $artwork = Artwork::factory()->create(['holder_id' => null]);

    $result = (new CompletenessCalculator)->evaluate($artwork);

    expect($result['blocking'])->toContain('holder');
});

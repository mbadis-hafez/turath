<?php

use App\Models\ArchiveItem;
use App\Models\ArchiveItemLink;
use App\Models\Artist;
use App\Support\Ocr\ArtistCandidateFinder;

function candidatesFor(?string $name, ?ArchiveItem $item = null): array
{
    return collect((new ArtistCandidateFinder)->find($name, $item ?? ArchiveItem::factory()->create()))
        ->map(fn ($c) => ['id' => $c['artist']->id, 'strength' => $c['strength']->value, 'basis' => $c['basis']])
        ->all();
}

it('ranks an exact normalized name match high', function () {
    $artist = Artist::factory()->create(['name_ar' => 'أحمد المغلوث', 'name_en' => 'Ahmad Almaghlout']);

    // Hamza-on-alef and taa-marbuta differences normalize away.
    expect(candidatesFor('احمد المغلوث'))->toBe([['id' => $artist->id, 'strength' => 'high', 'basis' => ['exact_name']]]);
});

it('matches a recorded name variant', function () {
    $artist = Artist::factory()->create(['name_ar' => 'أحمد المغلوث', 'name_en' => 'Ahmad Almaghlout']);
    $artist->variants()->create(['name' => 'أحمد بن عبدالله المغلوث', 'language' => 'ar', 'type' => 'alternate']);

    expect(candidatesFor('أحمد بن عبدالله المغلوث')[0])->toMatchArray(['id' => $artist->id, 'strength' => 'high', 'basis' => ['exact_variant']]);
});

it('ranks a longer written name sharing several parts as medium, folding the definite article', function () {
    $artist = Artist::factory()->create(['name_ar' => 'أحمد مغلوث', 'name_en' => null]);

    expect(candidatesFor('أحمد عبدالله المغلوث'))->toBe([['id' => $artist->id, 'strength' => 'medium', 'basis' => ['shared_name_parts']]]);
});

it('ranks a single shared part out of several low, and ignores unrelated artists', function () {
    $weak = Artist::factory()->create(['name_ar' => 'أحمد السليم', 'name_en' => null]);
    Artist::factory()->create(['name_ar' => 'صفية بن زقر', 'name_en' => 'Safeya Binzagr']);

    expect(candidatesFor('أحمد عبدالله المغلوث'))->toBe([['id' => $weak->id, 'strength' => 'low', 'basis' => ['shared_name_parts']]]);
});

it('offers an artist already linked to the archive item, and ranks it high when the name agrees', function () {
    $item = ArchiveItem::factory()->create();
    $linked = Artist::factory()->create(['name_ar' => 'أحمد مغلوث', 'name_en' => null]);
    ArchiveItemLink::factory()->create(['archive_item_id' => $item->id, 'linkable_id' => $linked->id]);

    expect(candidatesFor(null, $item))->toBe([['id' => $linked->id, 'strength' => 'medium', 'basis' => ['linked_to_archive_item']]])
        ->and(candidatesFor('أحمد عبدالله', $item)[0])->toMatchArray(['id' => $linked->id, 'strength' => 'high', 'basis' => ['shared_name_parts', 'linked_to_archive_item']]);
});

it('ignores bare numbers and one-letter OCR debris as name parts', function () {
    Artist::factory()->create(['name_ar' => 'فنان 3', 'name_en' => null, 'legacy_code' => 'AR003']);

    expect(candidatesFor('3 ب'))->toBe([]);
});

it('returns the strongest candidates first, capped', function () {
    $exact = Artist::factory()->create(['name_ar' => 'محمد السليم', 'name_en' => null]);
    foreach (range(1, 6) as $i) {
        Artist::factory()->create(['name_ar' => "محمد فنان{$i}", 'name_en' => null]);
    }

    $found = candidatesFor('محمد السليم');

    expect($found)->toHaveCount(5)
        ->and($found[0]['id'])->toBe($exact->id)
        ->and($found[0]['strength'])->toBe('high');
});

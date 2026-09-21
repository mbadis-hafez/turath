<?php

use App\Models\ArchiveItem;
use App\Models\ArchiveItemLink;
use App\Models\Artist;
use App\Models\Theme;

function publicItem(array $overrides = []): ArchiveItem
{
    return ArchiveItem::factory()->create(array_merge([
        'publication_status' => 'published', 'access_level' => 'public', 'rights_status' => 'public_domain', 'item_type' => 'article',
    ], $overrides));
}

function counts(array $facet): array
{
    return collect($facet)->pluck('count', 'value')->all();
}

/** Values are percent-encoded the way a browser would send them; a raw UTF-8 query string is not a real request. */
function browse(string $query = '')
{
    $encoded = preg_replace_callback('/=([^&]+)/u', fn ($m) => '='.rawurlencode($m[1]), $query) ?? $query;

    return test()->getJson('/api/v1/archive-items?include_facets=1'.($encoded === '' ? '' : '&'.$encoded));
}

it('filters by several types at once, and a single type still works as before', function () {
    publicItem(['item_type' => 'article']);
    publicItem(['item_type' => 'image']);
    publicItem(['item_type' => 'poster']);

    expect(browse('item_type[]=article&item_type[]=image')->assertOk()->json('meta.total'))->toBe(2);
    expect($this->getJson('/api/v1/archive-items?item_type=poster')->assertOk()->json('meta.total'))->toBe(1);
    $this->getJson('/api/v1/archive-items?item_type[]=nonsense')->assertUnprocessable();
});

it('filters by place, by theme, and by full versus preview-only access', function () {
    $theme = Theme::create(['label_ar' => 'الأحساء', 'label_en' => 'Alahsa']);
    $a = publicItem(['place_ar' => 'الرياض', 'access_level' => 'public']);
    $b = publicItem(['place_ar' => 'جدة', 'access_level' => 'registered']);
    publicItem(['place_ar' => null, 'place_en' => 'Jeddah', 'access_level' => 'institution_only']);
    $a->themes()->attach($theme->id);

    expect(browse('place[]=الرياض')->json('meta.total'))->toBe(1);
    // A place recorded only in English is found by that text too.
    expect(browse('place[]=Jeddah')->json('meta.total'))->toBe(1);
    expect(browse("theme_id[]={$theme->id}")->json('data.0.id'))->toBe($a->id);
    expect(browse('access[]=full')->json('meta.total'))->toBe(1);
    expect(browse('access[]=preview')->json('meta.total'))->toBe(2);
    // Asking for both is the same as not filtering.
    expect(browse('access[]=full&access[]=preview')->json('meta.total'))->toBe(3);
    expect($b->id)->not->toBe($a->id);
});

it('returns facet counts for the visible archive only, never counting drafts', function () {
    publicItem(['item_type' => 'article', 'place_ar' => 'الرياض']);
    publicItem(['item_type' => 'article', 'place_ar' => 'الرياض']);
    publicItem(['item_type' => 'image', 'place_ar' => 'جدة', 'access_level' => 'registered']);
    publicItem(['item_type' => 'image', 'publication_status' => 'draft', 'place_ar' => 'جدة']);

    $facets = browse()->assertOk()->json('meta.facets');

    expect(counts($facets['item_type']))->toBe(['article' => 2, 'image' => 1])
        ->and(counts($facets['place']))->toBe(['الرياض' => 2, 'جدة' => 1])
        ->and(counts($facets['access']))->toBe(['full' => 2, 'preview' => 1]);
    expect(browse()->json('meta.total'))->toBe(3);
});

it('leaves a facet group its own alternatives while the other groups narrow to the selection', function () {
    publicItem(['item_type' => 'article', 'place_ar' => 'الرياض']);
    publicItem(['item_type' => 'image', 'place_ar' => 'جدة']);
    publicItem(['item_type' => 'image', 'place_ar' => 'جدة']);

    $facets = browse('item_type[]=article')->json('meta.facets');

    // Types still show image, so a visitor can add it; places show only where an article exists.
    expect(counts($facets['item_type']))->toBe(['image' => 2, 'article' => 1])
        ->and(counts($facets['place']))->toBe(['الرياض' => 1]);
});

it('counts themes on archive items and lists them with their labels', function () {
    $theme = Theme::create(['label_ar' => 'نشأة الحركة', 'label_en' => 'Founding']);
    $other = Theme::create(['label_ar' => 'الطبيعة', 'label_en' => 'Nature']);
    publicItem()->themes()->attach($theme->id);
    publicItem()->themes()->attach($theme->id);
    publicItem(['publication_status' => 'draft'])->themes()->attach($other->id);

    $themes = browse()->json('meta.facets.theme_id');

    expect($themes)->toHaveCount(1)->and($themes[0])->toMatchArray(['value' => $theme->id, 'count' => 2, 'label' => ['ar' => 'نشأة الحركة', 'en' => 'Founding']]);
});

it('only computes facets when asked', function () {
    publicItem();

    expect($this->getJson('/api/v1/archive-items')->assertOk()->json('meta'))->not->toHaveKey('facets');
});

it('shows each card its published artists and place, restricted items included, but never a draft artist or the description', function () {
    $published = Artist::factory()->create(['publication_status' => 'published', 'name_ar' => 'أحمد المغلوث']);
    $draft = Artist::factory()->create(['publication_status' => 'draft', 'name_ar' => 'مسودة سرية']);
    $open = publicItem(['place_ar' => 'الرياض', 'description_ar' => 'وصف مفتوح']);
    $restricted = publicItem(['place_ar' => 'الأحساء', 'access_level' => 'institution_only', 'description_ar' => 'وصف مقيد سري']);
    foreach ([$open, $restricted] as $item) {
        foreach ([$published, $draft] as $artist) {
            ArchiveItemLink::create(['archive_item_id' => $item->id, 'linkable_type' => Artist::class, 'linkable_id' => $artist->id, 'role' => 'about']);
        }
    }

    $body = $this->getJson('/api/v1/archive-items')->assertOk();
    $rows = collect($body->json('data'))->keyBy('id');

    foreach ([$open, $restricted] as $item) {
        expect(collect($rows[$item->id]['artists'])->pluck('name.ar')->all())->toBe(['أحمد المغلوث']);
    }
    expect($rows[$restricted->id]['restricted'])->toBeTrue()->and($rows[$restricted->id]['place']['ar'])->toBe('الأحساء')
        ->and($rows[$restricted->id])->not->toHaveKey('description');
    expect($body->getContent())->not->toContain('مسودة سرية')->not->toContain('وصف مقيد سري');
});

it('tags archive items with the shared themes only for archive managers', function () {
    $theme = Theme::create(['label_ar' => 'الأحساء', 'label_en' => 'Alahsa']);
    $item = publicItem();

    $this->actingAs(makeUser('reader'))->patchJson("/api/v1/archive-items/{$item->id}/themes", ['theme_ids' => [$theme->id]])->assertForbidden();
    Auth::forgetGuards();
    $this->actingAs(makeUser('contributor'))->patchJson("/api/v1/archive-items/{$item->id}/themes", ['theme_ids' => [$theme->id]])->assertForbidden();
    Auth::forgetGuards();

    $this->actingAs(editorUser())->patchJson("/api/v1/archive-items/{$item->id}/themes", ['theme_ids' => [$theme->id]])->assertOk()->assertJsonPath('data.theme_ids.0', $theme->id);
    expect($item->themes()->count())->toBe(1);
});

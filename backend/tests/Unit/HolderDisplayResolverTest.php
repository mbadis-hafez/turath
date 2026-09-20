<?php

use App\Models\Holder;
use App\Support\HolderDisplayResolver;

it('shows the real name for public holders', function () {
    $holder = Holder::factory()->make(['is_public_name' => true, 'name_ar' => 'مؤسسة', 'name_en' => 'Foundation']);

    expect(HolderDisplayResolver::resolve($holder))->toBe(['ar' => 'مؤسسة', 'en' => 'Foundation']);
});

it('never reveals a private collector\'s real name, only city', function () {
    $holder = Holder::factory()->privateCollector()->make([
        'name_ar' => 'اسم حقيقي',
        'name_en' => 'Real Name',
        'city_ar' => 'جدة',
        'city_en' => 'Jeddah',
    ]);

    expect(HolderDisplayResolver::resolve($holder))->toBe([
        'ar' => 'مجموعة خاصة، جدة',
        'en' => 'Private collection, Jeddah',
    ]);
});

it('falls back to a plain private-collection label when no city is known', function () {
    $holder = Holder::factory()->privateCollector()->make(['city_ar' => null, 'city_en' => null]);

    expect(HolderDisplayResolver::resolve($holder))->toBe([
        'ar' => 'مجموعة خاصة',
        'en' => 'Private collection',
    ]);
});

it('labels a non-public artist estate distinctly from a family collection', function () {
    $estate = Holder::factory()->familyEstate()->make();
    $family = Holder::factory()->make(['type' => 'family', 'is_public_name' => false, 'is_estate' => false]);

    expect(HolderDisplayResolver::resolve($estate))->toBe(['ar' => 'ورثة الفنان', 'en' => "Artist's estate"])
        ->and(HolderDisplayResolver::resolve($family))->toBe(['ar' => 'مجموعة عائلية', 'en' => 'Family collection']);
});

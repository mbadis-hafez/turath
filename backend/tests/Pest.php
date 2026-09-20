<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

uses(RefreshDatabase::class)->in('Feature', 'Unit');

function seedRoles(): void
{
    test()->seed(RolesAndPermissionsSeeder::class);
}

function makeUser(?string $role = null): User
{
    if ($role !== null) {
        seedRoles();
    }

    $user = User::factory()->create();

    if ($role !== null) {
        $user->assignRole($role);
    }

    return $user;
}

function editorUser(): User
{
    return makeUser('editor');
}

/**
 * Valid nested artist payload, overridable per key.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function artistPayload(array $overrides = []): array
{
    return array_merge([
        'name' => ['ar' => 'عبدالحليم رضوي', 'en' => 'Abdulhalim Radwi'],
        'bio' => ['ar' => 'سيرة الفنان', 'en' => 'Artist biography'],
        'birth' => [
            'display' => 'c. 1939',
            'year_from' => 1939,
            'year_to' => 1941,
            'calendar' => 'gregorian',
            'certainty' => 'circa',
            'place' => ['ar' => 'مكة', 'en' => 'Makkah'],
        ],
        'death' => null,
        'living_status' => 'deceased',
        'legacy_code' => 'AR999',
        'publication_status' => 'published',
    ], $overrides);
}

/**
 * Valid nested artwork payload, overridable per key.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function artworkPayload(array $overrides = []): array
{
    return array_merge([
        'title' => ['ar' => 'صورة زيتية مع سمك', 'en' => 'Still Life With Fish'],
        'is_untitled' => false,
        'category' => 'painting',
        'medium' => ['ar' => 'زيت على قماش', 'en' => 'Oil on canvas'],
        'attribution_certainty' => 'confirmed',
        'dimensions' => ['raw' => '60H x 45W cm'],
        'creation' => [
            'display' => '1975',
            'year_from' => 1975,
            'year_to' => 1975,
            'calendar' => 'gregorian',
            'certainty' => 'exact',
        ],
        'publication_status' => 'published',
    ], $overrides);
}

/**
 * Valid nested holder payload, overridable per key.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function holderPayload(array $overrides = []): array
{
    return array_merge([
        'type' => 'institution',
        'name' => ['ar' => 'مؤسسة تجريبية', 'en' => 'Test Foundation'],
        'city' => ['ar' => 'جدة', 'en' => 'Jeddah'],
    ], $overrides);
}

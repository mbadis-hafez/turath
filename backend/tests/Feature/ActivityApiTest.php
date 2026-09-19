<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;
use Tests\Fixtures\TrackableThing;

beforeEach(function () {
    if (! Schema::hasTable('trackable_things')) {
        Schema::create('trackable_things', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->unsignedSmallInteger('birth_year_from')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    $this->seed(RolesAndPermissionsSeeder::class);

    DB::table('activity_log')->delete();
    DB::table('model_has_roles')->delete();
    DB::table('model_has_permissions')->delete();
    DB::table('users')->delete();
    DB::table('trackable_things')->delete();

    config(['activity.resources.things' => TrackableThing::class]);
});

function makeEditor(): User
{
    $user = User::factory()->create();
    $user->assignRole('editor');

    return $user;
}

function makeThing(array $attributes = []): TrackableThing
{
    return TrackableThing::create($attributes + ['title' => 'Radwi']);
}

it('requires authentication and the activity view permission', function () {
    $this->getJson('/api/v1/activity')->assertUnauthorized();

    $reader = User::factory()->create();
    $reader->assignRole('reader');

    $this->actingAs($reader)->getJson('/api/v1/activity')->assertForbidden();
    $this->actingAs($reader)->getJson('/api/v1/things/1/activity')->assertForbidden();

    $this->actingAs(makeEditor())->getJson('/api/v1/activity')->assertOk();
    $this->actingAs(makeEditor())->getJson('/api/v1/things/1/activity')->assertOk();
});

it('returns the global feed newest first with the documented shape', function () {
    $editor = makeEditor();

    $this->actingAs($editor);
    $first = makeThing();
    $second = makeThing(['title' => 'Second']);

    $this->actingAs($editor)->getJson('/api/v1/activity')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.subject_id', $second->id)
        ->assertJsonPath('data.1.subject_id', $first->id)
        ->assertJsonPath('data.0.subject_type', 'TrackableThing')
        ->assertJsonPath('data.0.subject_label', 'Thing: Second')
        ->assertJsonPath('data.0.causer.id', $editor->id)
        ->assertJsonPath('data.0.event', 'created')
        ->assertJsonPath('data.0.edit_summary', null)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'event', 'subject_type', 'subject_id', 'subject_label', 'causer', 'edit_summary', 'changes', 'created_at'],
            ],
            'links', 'meta',
        ])
        ->assertJsonPath('meta.per_page', 24);
});

it('derives changes with bilingual labels without exposing raw properties', function () {
    $this->actingAs(makeEditor());

    $thing = makeThing(['title' => 'Radwi', 'birth_year_from' => 1939]);
    $thing->update(['birth_year_from' => 1940]);

    $response = $this->actingAs(makeEditor())->getJson('/api/v1/activity?event=updated');

    $response->assertOk()->assertJsonCount(1, 'data');

    $changes = $response->json('data.0.changes');

    expect($changes)->toBe([
        [
            'field' => 'birth_year_from',
            'label' => ['ar' => 'سنة الميلاد (من)', 'en' => 'Birth year (from)'],
            'old' => 1939,
            'new' => 1940,
        ],
    ])->and($response->json('data.0'))->not->toHaveKey('properties');
});

it('filters the feed by subject type, causer, event and date range', function () {
    $editor = makeEditor();
    $other = makeEditor();

    $this->actingAs($editor);
    $thing = makeThing(['title' => 'Radwi', 'birth_year_from' => 1939]);
    $thing->update(['birth_year_from' => 1940]);
    $thing->delete();

    auth()->forgetGuards();
    $this->actingAs($other);
    $otherThing = makeThing(['title' => 'Other']);

    // subject_type (both FQCN and basename accepted)
    $this->actingAs($editor)->getJson('/api/v1/activity?subject_type='.TrackableThing::class)
        ->assertJsonCount(4, 'data');
    $this->actingAs($editor)->getJson('/api/v1/activity?subject_type=TrackableThing')
        ->assertJsonCount(4, 'data');

    // subject_id
    $this->actingAs($editor)->getJson('/api/v1/activity?subject_id='.$otherThing->id)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.subject_id', $otherThing->id);

    // causer_id
    $this->actingAs($editor)->getJson('/api/v1/activity?causer_id='.$editor->id)
        ->assertJsonCount(3, 'data');
    $this->actingAs($editor)->getJson('/api/v1/activity?causer_id='.$other->id)
        ->assertJsonCount(1, 'data');

    // event
    $this->actingAs($editor)->getJson('/api/v1/activity?event=deleted')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.event', 'deleted');
    $this->actingAs($editor)->getJson('/api/v1/activity?event=restored')
        ->assertJsonCount(0, 'data');

    // date range
    $tomorrow = now()->addDay()->toDateString();
    $this->actingAs($editor)->getJson('/api/v1/activity?date_from='.$tomorrow)
        ->assertJsonCount(0, 'data');
    $this->actingAs($editor)->getJson('/api/v1/activity?date_to='.now()->toDateString())
        ->assertJsonCount(4, 'data');
});

it('rejects invalid filter values', function () {
    $this->actingAs(makeEditor())->getJson('/api/v1/activity?event=nonsense')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['event']);
});

it('paginates with a default of 24 and a max of 100', function () {
    $this->actingAs(makeEditor());

    foreach (range(1, 30) as $i) {
        makeThing(['title' => "Thing {$i}"]);
    }

    $this->actingAs(makeEditor())->getJson('/api/v1/activity')
        ->assertJsonCount(24, 'data')
        ->assertJsonPath('meta.per_page', 24)
        ->assertJsonPath('meta.total', 30);

    $this->actingAs(makeEditor())->getJson('/api/v1/activity?per_page=100')
        ->assertJsonCount(30, 'data');
});

it('scopes the per entity history to a single subject', function () {
    $editor = makeEditor();
    $this->actingAs($editor);

    $thing = makeThing(['birth_year_from' => 1939]);
    $thing->update(['birth_year_from' => 1940]);
    $other = makeThing(['title' => 'Other']);

    $response = $this->actingAs($editor)->getJson("/api/v1/things/{$thing->id}/activity");

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.event', 'updated')
        ->assertJsonPath('data.1.event', 'created');

    expect(collect($response->json('data'))->pluck('subject_id')->unique()->all())
        ->toBe([$thing->id]);
});

it('returns 404 for unknown resource segments', function () {
    $this->actingAs(makeEditor())->getJson('/api/v1/widgets/1/activity')->assertNotFound();
});

it('resolves labels for soft deleted subjects', function () {
    $editor = makeEditor();
    $this->actingAs($editor);

    $thing = makeThing();
    $thing->delete();

    $this->actingAs($editor)->getJson('/api/v1/activity?event=deleted')
        ->assertOk()
        ->assertJsonPath('data.0.subject_label', 'Thing: Radwi');
});

it('falls back to a deleted marker when the subject is hard missing', function () {
    $editor = makeEditor();

    Activity::create([
        'log_name' => 'trackable_things',
        'description' => 'created',
        'event' => 'created',
        'subject_type' => TrackableThing::class,
        'subject_id' => 999,
        'causer_id' => null,
        'causer_type' => null,
        'properties' => collect(['edit_summary' => null]),
        'attribute_changes' => collect(['attributes' => ['title' => 'Gone']]),
    ]);

    $this->actingAs($editor)->getJson('/api/v1/activity')
        ->assertOk()
        ->assertJsonPath('data.0.subject_label', 'TrackableThing #999 (deleted)');
});

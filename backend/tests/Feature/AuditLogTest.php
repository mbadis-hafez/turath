<?php

use App\Models\User;
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

    DB::table('activity_log')->delete();
    DB::table('model_has_roles')->delete();
    DB::table('model_has_permissions')->delete();
    DB::table('users')->delete();
    DB::table('trackable_things')->delete();
});

it('logs exactly one entry per lifecycle event', function () {
    $thing = TrackableThing::create(['title' => 'Radwi', 'birth_year_from' => 1939]);

    expect(Activity::count())->toBe(1)
        ->and(Activity::first()->event)->toBe('created')
        ->and(Activity::first()->log_name)->toBe('trackable_things');

    $thing->update(['title' => 'Abdulhalim Radwi']);

    expect(Activity::count())->toBe(2)
        ->and(Activity::latest('id')->first()->event)->toBe('updated');

    $thing->delete();

    expect(Activity::count())->toBe(3)
        ->and(Activity::latest('id')->first()->event)->toBe('deleted');

    $thing->restore();

    expect(Activity::count())->toBe(4)
        ->and(Activity::latest('id')->first()->event)->toBe('restored');
});

it('attributes entries to the authenticated user and leaves causer null otherwise', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $thing = TrackableThing::create(['title' => 'Signed']);
    $entry = Activity::first();

    expect($entry->causer_id)->toBe($user->id)
        ->and($entry->causer_type)->toBe(User::class);

    auth()->forgetGuards();

    TrackableThing::create(['title' => 'System']);
    $systemEntry = Activity::latest('id')->first();

    expect(Activity::count())->toBe(2)
        ->and($systemEntry->causer_id)->toBeNull()
        ->and($systemEntry->causer_type)->toBeNull();
});

it('logs only dirty attributes and never timestamps', function () {
    $thing = TrackableThing::create(['title' => 'Radwi', 'birth_year_from' => 1939]);
    $thing->update(['birth_year_from' => 1940]);

    $entry = Activity::latest('id')->first();
    $old = $entry->attribute_changes['old'];
    $new = $entry->attribute_changes['attributes'];

    expect(array_keys($old))->toBe(['birth_year_from'])
        ->and(array_keys($new))->toBe(['birth_year_from'])
        ->and($old['birth_year_from'])->toBe(1939)
        ->and($new['birth_year_from'])->toBe(1940)
        ->and($entry->attribute_changes['old'])->not->toHaveKeys(['created_at', 'updated_at'])
        ->and($entry->attribute_changes['attributes'])->not->toHaveKeys(['created_at', 'updated_at']);

    $thing->touch();

    expect(Activity::where('event', 'updated')->count())->toBe(1);
});

it('excludes timestamps from created and deleted entries', function () {
    $thing = TrackableThing::create(['title' => 'Radwi']);
    $created = Activity::first();

    expect($created->attribute_changes['attributes'])->not->toHaveKeys(['created_at', 'updated_at']);

    $thing->delete();
    $deleted = Activity::latest('id')->first();

    expect($deleted->attribute_changes['old'])->not->toHaveKeys(['created_at', 'updated_at', 'deleted_at']);
});

it('attaches the edit summary from the request input', function () {
    request()->merge(['edit_summary' => 'Fixed the birth year']);

    $thing = TrackableThing::create(['title' => 'Radwi']);
    $thing->update(['title' => 'Abdulhalim Radwi']);

    expect(Activity::first()->properties['edit_summary'])->toBe('Fixed the birth year')
        ->and(Activity::latest('id')->first()->properties['edit_summary'])->toBe('Fixed the birth year');
});

it('ignores edit summaries that are empty or too long', function () {
    request()->merge(['edit_summary' => str_repeat('a', 256)]);

    TrackableThing::create(['title' => 'Radwi']);

    expect(Activity::first()->properties['edit_summary'] ?? null)->toBeNull();
});

it('uses the model table as log name', function () {
    TrackableThing::create(['title' => 'Radwi']);

    expect(Activity::first()->log_name)->toBe('trackable_things');
});

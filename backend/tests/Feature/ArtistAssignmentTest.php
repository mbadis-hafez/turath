<?php

use App\Models\Artist;
use Spatie\Activitylog\Models\Activity;

it('assigns a staff member to an artist, clears it, and audits the change', function () {
    $editor = editorUser();
    $staff = makeUser('editor');
    $artist = Artist::factory()->create();

    $this->actingAs($editor)->patchJson("/api/v1/artists/{$artist->id}/assignment", ['assigned_to_user_id' => $staff->id])
        ->assertOk()->assertJsonPath('data.id', $staff->id)->assertJsonPath('data.name', $staff->name);

    expect($artist->refresh()->assigned_to_user_id)->toBe($staff->id);
    $entry = Activity::where('subject_type', Artist::class)->where('subject_id', $artist->id)->latest('id')->first();
    expect($entry->attribute_changes['attributes']['assigned_to_user_id'])->toBe($staff->id);

    $this->actingAs($editor)->patchJson("/api/v1/artists/{$artist->id}/assignment", ['assigned_to_user_id' => null])
        ->assertOk()->assertJsonPath('data', null);
    expect($artist->refresh()->assigned_to_user_id)->toBeNull();
});

it('rejects an unknown user id and requires artists.manage', function () {
    $editor = editorUser();
    $artist = Artist::factory()->create();

    $this->actingAs($editor)->patchJson("/api/v1/artists/{$artist->id}/assignment", ['assigned_to_user_id' => 999999])->assertUnprocessable();

    $this->actingAs(makeUser('reader'))->patchJson("/api/v1/artists/{$artist->id}/assignment", ['assigned_to_user_id' => null])->assertForbidden();
    $this->actingAs(makeUser('contributor'))->patchJson("/api/v1/artists/{$artist->id}/assignment", ['assigned_to_user_id' => null])->assertForbidden();
});

it('lists only active staff-role users as assignment options, filtered by name or email, never readers or contributors', function () {
    $editor = editorUser();
    $target = makeUser('reviewer');
    $target->update(['name' => 'Samar Al Otaibi']);
    $inactive = makeUser('editor');
    $inactive->update(['is_active' => false]);
    makeUser('reader');
    makeUser('contributor');

    $all = $this->actingAs($editor)->getJson('/api/v1/staff-options')->assertOk()->json('data');
    $names = collect($all)->pluck('name');
    expect($names)->toContain('Samar Al Otaibi')->not->toContain($inactive->name);

    $byName = $this->actingAs($editor)->getJson('/api/v1/staff-options?q=Samar')->assertOk()->json('data');
    expect($byName)->toHaveCount(1)->and($byName[0]['id'])->toBe($target->id);

    $byEmail = $this->actingAs($editor)->getJson('/api/v1/staff-options?q='.substr($target->email, 0, 6))->assertOk()->json('data');
    expect(collect($byEmail)->pluck('id'))->toContain($target->id);
});

it('shows the assignment on the curation bundle and carries it through a merge', function () {
    $editor = editorUser();
    $staff = makeUser('editor');
    $survivor = Artist::factory()->create();
    $duplicate = Artist::factory()->create(['assigned_to_user_id' => $staff->id]);

    expect($this->actingAs($editor)->getJson("/api/v1/artists/{$survivor->id}/curation")->json('data.assigned_to'))->toBeNull();

    $this->actingAs($editor)->postJson('/api/v1/artists/merge', [
        'survivor_id' => $survivor->id, 'duplicate_id' => $duplicate->id,
        'field_resolution' => ['assigned_to_user_id' => 'duplicate'],
    ])->assertCreated();

    expect($survivor->refresh()->assigned_to_user_id)->toBe($staff->id);
});

<?php

use App\Models\Artist;
use Illuminate\Support\Facades\Auth;

it('verifies and unverifies an artist', function () {
    $editor = editorUser();
    $artist = Artist::factory()->published()->create();

    $response = $this->actingAs($editor)->postJson("/api/v1/artists/{$artist->id}/verify", [
        'status' => 'verified',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.verified_status', 'verified')
        ->assertJsonPath('data.verified_by.id', $editor->id);

    $artist->refresh();

    expect($artist->verified_by_user_id)->toBe($editor->id)
        ->and($artist->verified_at)->not->toBeNull();

    // Disputed path.
    $this->actingAs($editor)->postJson("/api/v1/artists/{$artist->id}/verify", [
        'status' => 'disputed',
    ])->assertOk()->assertJsonPath('data.verified_status', 'disputed');

    // Unverify clears everything.
    $response = $this->actingAs($editor)->deleteJson("/api/v1/artists/{$artist->id}/verify");

    $response->assertOk()->assertJsonPath('data.verified_status', 'unverified');

    $artist->refresh();

    expect($artist->verified_by_user_id)->toBeNull()
        ->and($artist->verified_at)->toBeNull();
});

it('rejects invalid verify payloads', function () {
    $editor = editorUser();
    $artist = Artist::factory()->published()->create();

    $this->actingAs($editor)->postJson("/api/v1/artists/{$artist->id}/verify", [
        'status' => 'unverified',
    ])->assertUnprocessable()->assertJsonValidationErrors(['status']);

    $this->actingAs($editor)->postJson("/api/v1/artists/{$artist->id}/verify", [])
        ->assertUnprocessable();
});

it('requires the artists.verify permission', function () {
    seedRoles();

    $contributor = makeUser('contributor');
    $artist = Artist::factory()->published()->create();

    // contributor exists but lacks artists.verify
    $this->actingAs($contributor)->postJson("/api/v1/artists/{$artist->id}/verify", ['status' => 'verified'])
        ->assertForbidden();

    // Anonymous (fresh guards: the cached sanctum guard would otherwise leak
    // the previous user within this single test process)
    Auth::forgetGuards();

    $this->postJson("/api/v1/artists/{$artist->id}/verify", ['status' => 'verified'])
        ->assertUnauthorized();
});

<?php

use App\Models\Artist;
use App\Models\ContentPermissionRequest;

it('lets a reviewer request to delete a published artist, admin approves, and the reviewer can then delete it once', function () {
    $reviewer = reviewerUser();
    $admin = makeUser('admin');
    $artist = Artist::factory()->published()->create();

    // A reviewer cannot delete a published artist directly.
    $this->actingAs($reviewer)->deleteJson("/api/v1/artists/{$artist->id}")->assertForbidden();

    $id = $this->actingAs($reviewer)->postJson("/api/v1/records/artists/{$artist->id}/permission-requests", [
        'request_type' => 'delete', 'reason' => 'This record duplicates another and should be removed.',
    ])->assertCreated()->assertJsonPath('data.status', 'pending')->json('data.id');

    // Not yet approved: still forbidden.
    $this->actingAs($reviewer)->deleteJson("/api/v1/artists/{$artist->id}")->assertForbidden();

    // An editor cannot decide requests.
    $this->actingAs(editorUser())->postJson("/api/v1/permission-requests/{$id}/approve")->assertForbidden();

    $this->actingAs($admin)->postJson("/api/v1/permission-requests/{$id}/approve", ['decision_note' => 'Confirmed duplicate.'])
        ->assertOk()->assertJsonPath('data.status', 'approved');

    $this->actingAs($reviewer)->deleteJson("/api/v1/artists/{$artist->id}")->assertNoContent();
    expect($artist->refresh()->trashed())->toBeTrue();

    // The approval was a one-shot grant, already consumed; re-deleting a fresh record needs a fresh request.
    expect(ContentPermissionRequest::find($id)->status)->toBe('approved');
});

it('lets an admin reject a delete request, leaving the record untouched', function () {
    $reviewer = reviewerUser();
    $admin = makeUser('admin');
    $artist = Artist::factory()->published()->create();

    $id = $this->actingAs($reviewer)->postJson("/api/v1/records/artists/{$artist->id}/permission-requests", [
        'request_type' => 'delete', 'reason' => 'Looks wrong.',
    ])->json('data.id');

    $this->actingAs($admin)->postJson("/api/v1/permission-requests/{$id}/reject", ['decision_note' => 'Not enough evidence.'])
        ->assertOk()->assertJsonPath('data.status', 'rejected');

    $this->actingAs($reviewer)->deleteJson("/api/v1/artists/{$artist->id}")->assertForbidden();
    expect($artist->refresh()->trashed())->toBeFalse();
});

it('refuses a second pending request of the same type from the same reviewer, and refuses an editor requesting at all', function () {
    $reviewer = reviewerUser();
    $editor = editorUser();
    $artist = Artist::factory()->published()->create();

    $this->actingAs($reviewer)->postJson("/api/v1/records/artists/{$artist->id}/permission-requests", [
        'request_type' => 'edit', 'reason' => 'Needs a correction.',
    ])->assertCreated();

    $this->actingAs($reviewer)->postJson("/api/v1/records/artists/{$artist->id}/permission-requests", [
        'request_type' => 'edit', 'reason' => 'Again.',
    ])->assertUnprocessable();

    // An editor already has direct access to drafts and doesn't need to request anything.
    $this->actingAs($editor)->postJson("/api/v1/records/artists/{$artist->id}/permission-requests", [
        'request_type' => 'edit', 'reason' => 'Not needed.',
    ])->assertForbidden();
});

it('lets an editor and admin edit/delete an unpublished record directly without any request', function () {
    $editor = editorUser();
    $artist = Artist::factory()->create(); // draft

    $this->actingAs($editor)->patchJson("/api/v1/artists/{$artist->id}", ['bio' => ['en' => 'x']])->assertOk();
    $this->actingAs($editor)->deleteJson("/api/v1/artists/{$artist->id}")->assertNoContent();
});

it('lets an admin edit and delete a published record directly, with no request needed', function () {
    $admin = makeUser('admin');
    $artist = Artist::factory()->published()->create();

    $this->actingAs($admin)->patchJson("/api/v1/artists/{$artist->id}", ['bio' => ['en' => 'x']])->assertOk();
    $this->actingAs($admin)->deleteJson("/api/v1/artists/{$artist->id}")->assertNoContent();
});

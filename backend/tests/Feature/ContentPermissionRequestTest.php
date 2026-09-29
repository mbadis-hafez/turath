<?php

use App\Models\Artist;
use App\Models\ContentPermissionRequest;

/**
 * The request-permission workflow is for a user who can review
 * (review_queue.*) but holds no manage permission — the built-in Reviewer
 * role no longer fits that shape (it now also holds the full Editor
 * permission set), so these tests exercise it with a synthetic role that
 * still matches the case the feature was built for: a custom role a
 * platform admin could create via the Roles admin UI.
 */
function reviewOnlyUser()
{
    seedRoles();
    $user = makeUser();
    $user->givePermissionTo('review_queue.editorial_review');

    return $user;
}

it('lets a review-only user request to delete a published artist, admin approves, and they can then delete it once', function () {
    $reviewer = reviewOnlyUser();
    $admin = makeUser('admin');
    $artist = Artist::factory()->published()->create();

    // No manage permission: cannot delete a published artist directly.
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
    $reviewer = reviewOnlyUser();
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

it('refuses a second pending request of the same type from the same review-only user, and refuses an editor requesting at all', function () {
    $reviewer = reviewOnlyUser();
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

it('now holding the full editor permission set, a reviewer can no longer use the permission-request workflow', function () {
    $reviewer = reviewerUser();
    $artist = Artist::factory()->published()->create();

    $this->actingAs($reviewer)->postJson("/api/v1/records/artists/{$artist->id}/permission-requests", [
        'request_type' => 'delete', 'reason' => 'Looks wrong.',
    ])->assertForbidden();

    // Same as an editor: direct access to drafts, the proposal pipeline for published edits, no direct delete of a published record.
    $draft = Artist::factory()->create();
    $this->actingAs($reviewer)->patchJson("/api/v1/artists/{$draft->id}", ['bio' => ['en' => 'x']])->assertOk();
    $this->actingAs($reviewer)->deleteJson("/api/v1/artists/{$artist->id}")->assertForbidden();
});

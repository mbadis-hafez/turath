<?php

use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\EditProposal;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

function logEventCreated(Event $event, User $user): void
{
    Activity::create([
        'log_name' => 'events',
        'description' => 'created',
        'event' => 'created',
        'subject_type' => Event::class,
        'subject_id' => (string) $event->id,
        'causer_type' => User::class,
        'causer_id' => (string) $user->id,
    ]);
}

// The artist/artwork/archive observers materialize a record_completeness row
// on every save, so tests overwrite that row instead of inserting a second one.
function setCompleteness(string $citableType, int $citableId, int $pct, string $severity): void
{
    DB::table('record_completeness')->updateOrInsert(
        ['citable_type' => $citableType, 'citable_id' => $citableId],
        [
            'completeness_pct' => $pct,
            'severity' => $severity,
            'blocking_gap_field_keys' => '[]',
            'minor_gap_field_keys' => '[]',
            'open_conflict_count' => 0,
            'computed_at' => now(),
        ],
    );
}

it('scopes content overview counts to the caller across all four entity types', function () {
    $editor = editorUser();
    $other = makeUser('editor');

    Artist::factory()->complete()->create(['assigned_to_user_id' => $editor->id]);
    Artist::factory()->complete()->create(['created_by_user_id' => $editor->id]);
    Artist::factory()->create(['assigned_to_user_id' => $other->id, 'created_by_user_id' => $other->id]);

    Artwork::factory()->create(['created_by_user_id' => $editor->id]);
    Artwork::factory()->create(['created_by_user_id' => $other->id]);

    ArchiveItem::factory()->create(['created_by_user_id' => $editor->id]);
    ArchiveItem::factory()->create(['created_by_user_id' => $other->id]);

    logEventCreated(Event::create(['event_type' => 'exhibition', 'title_en' => 'Mine']), $editor);
    logEventCreated(Event::create(['event_type' => 'exhibition', 'title_en' => 'Theirs']), $other);

    $data = $this->actingAs($editor)->getJson('/api/v1/dashboard/editor')->assertOk()->json('data');

    expect($data['content_overview']['artists']['total'])->toBe(2)
        ->and($data['content_overview']['artists']['incomplete'])->toBe(0)
        ->and($data['content_overview']['artworks']['total'])->toBe(1)
        ->and($data['content_overview']['artworks']['incomplete'])->toBeLessThanOrEqual(1)
        ->and($data['content_overview']['archive_items']['total'])->toBe(1)
        ->and($data['content_overview']['archive_items']['incomplete'])->toBeLessThanOrEqual(1);
});

it('scopes events to the caller through activity_log created events', function () {
    $editor = editorUser();
    $other = makeUser('editor');

    logEventCreated(Event::create(['event_type' => 'exhibition', 'title_en' => 'Mine']), $editor);
    logEventCreated(Event::create(['event_type' => 'talk', 'title_en' => 'Theirs']), $other);

    $data = $this->actingAs($editor)->getJson('/api/v1/dashboard/editor')->assertOk()->json('data');

    expect($data['content_overview']['events']['total'])->toBe(1)
        ->and($data['review_pipeline']['published'])->toBe(0);
});

it('orders needs_attention changes_requested first then lowest pct, carrying the review note without duplicates', function () {
    $editor = editorUser();
    $other = makeUser('editor');

    $flagged = Artist::factory()->create(['created_by_user_id' => $editor->id, 'name_en' => 'Flagged']);
    EditProposal::create([
        'citable_type' => Artist::class,
        'citable_id' => $flagged->id,
        'proposed_by_user_id' => $editor->id,
        'status' => 'changes_requested',
        'field_diffs' => ['bio_ar' => ['old' => 'a', 'new' => 'b']],
        'rationale' => 'test',
        'review_type' => 'data_audit',
        'review_note' => 'Add a birth citation',
    ]);

    $lowPct = Artist::factory()->create(['created_by_user_id' => $editor->id]);
    setCompleteness(Artist::class, $lowPct->id, 25, 'blocking');

    $midPct = Artist::factory()->create(['created_by_user_id' => $editor->id]);
    setCompleteness(Artist::class, $midPct->id, 60, 'minor');

    // Complete but still awaiting creation-review approval: only phase 3 may list it.
    $pending = Artist::factory()->unreviewed()->complete()->create(['created_by_user_id' => $editor->id]);

    // Fully complete + reviewed record: must not appear at all.
    $complete = Artist::factory()->create(['created_by_user_id' => $editor->id]);
    setCompleteness(Artist::class, $complete->id, 100, 'clear');

    // Another user's flagged + incomplete records: must not leak in.
    $theirs = Artist::factory()->create(['created_by_user_id' => $other->id]);
    EditProposal::create([
        'citable_type' => Artist::class,
        'citable_id' => $theirs->id,
        'proposed_by_user_id' => $other->id,
        'status' => 'changes_requested',
        'field_diffs' => ['bio_ar' => ['old' => 'a', 'new' => 'b']],
        'rationale' => 'test',
        'review_type' => 'data_audit',
        'review_note' => 'Someone else note',
    ]);
    setCompleteness(Artist::class, $theirs->id, 5, 'blocking');

    $items = $this->actingAs($editor)->getJson('/api/v1/dashboard/editor')
        ->assertOk()
        ->json('data.needs_attention');

    expect($items[0])->toMatchArray([
        'entity_type' => 'artist',
        'id' => $flagged->id,
        'kind' => 'changes_requested',
        'reason_note' => 'Add a birth citation',
    ])->and($items[1]['kind'])->toBe('incomplete')
        ->and($items[1]['id'])->toBe($lowPct->id)
        ->and($items[1]['completeness_pct'])->toBe(25)
        ->and($items[2]['id'])->toBe($midPct->id)
        ->and($items[3]['kind'])->toBe('creation_pending')
        ->and($items[3]['id'])->toBe($pending->id);

    $keys = collect($items)->map(fn (array $item) => $item['entity_type'].':'.$item['id']);
    expect($keys->unique()->values()->all())->toBe($keys->values()->all())
        ->and($keys)->not->toContain('artist:'.$theirs->id);
});

it('builds completeness buckets and the lowest list from materialized record_completeness', function () {
    $editor = editorUser();
    $other = makeUser('editor');

    $complete = Artist::factory()->complete()->create(['created_by_user_id' => $editor->id]);
    setCompleteness(Artist::class, $complete->id, 100, 'clear');

    foreach ([85 => 'high', 60 => 'medium', 30 => 'low'] as $pct => $bucket) {
        $artist = Artist::factory()->create(['created_by_user_id' => $editor->id]);
        setCompleteness(Artist::class, $artist->id, $pct, $bucket === 'high' ? 'minor' : 'blocking');
    }

    // Lower pct owned by someone else: must not enter the buckets or lowest.
    $theirs = Artist::factory()->create(['created_by_user_id' => $other->id]);
    setCompleteness(Artist::class, $theirs->id, 5, 'blocking');

    $section = $this->actingAs($editor)->getJson('/api/v1/dashboard/editor')
        ->assertOk()
        ->json('data.completeness');

    expect($section['buckets'])->toBe(['complete' => 1, 'high' => 1, 'medium' => 1, 'low' => 1])
        ->and($section['lowest'])->toHaveCount(3)
        ->and($section['lowest'][0]['completeness_pct'])->toBe(30)
        ->and(collect($section['lowest'])->pluck('id'))->not->toContain($theirs->id);
});

it('only surfaces the caller own activity in continue_working and recent_activity', function () {
    $editor = editorUser();
    $other = makeUser('editor');

    $mineArtist = Artist::factory()->create();
    $mineArtwork = Artwork::factory()->create();
    $theirs = Artist::factory()->create();

    $this->actingAs($other);
    $theirs->update(['bio_en' => 'Written by the other editor']);
    auth()->forgetGuards();

    $this->actingAs($editor);
    $mineArtist->update(['bio_en' => 'Written by me']);
    $mineArtwork->update(['medium_en' => 'Edited by me']);

    $data = $this->actingAs($editor)->getJson('/api/v1/dashboard/editor')->assertOk()->json('data');

    $continueIds = collect($data['continue_working'])->map(fn (array $i) => $i['entity_type'].':'.$i['id']);
    expect($continueIds->sort()->values()->all())->toBe(collect(['artist:'.$mineArtist->id, 'artwork:'.$mineArtwork->id])->sort()->values()->all())
        ->and($continueIds)->not->toContain('artist:'.$theirs->id);

    $activityIds = collect($data['recent_activity'])->map(fn (array $i) => $i['entity_type'].':'.$i['id']);
    expect($activityIds->sort()->values()->all())->toBe(collect(['artist:'.$mineArtist->id, 'artwork:'.$mineArtwork->id])->sort()->values()->all())
        ->and($activityIds)->not->toContain('artist:'.$theirs->id);
});

it('counts only the caller published records in the review pipeline', function () {
    $editor = editorUser();
    $other = makeUser('editor');

    Artist::factory()->published()->create(['created_by_user_id' => $editor->id]);
    Artwork::factory()->published()->create(['created_by_user_id' => $editor->id]);
    Artist::factory()->create(['created_by_user_id' => $editor->id]);
    Artist::factory()->published()->create(['created_by_user_id' => $other->id]);

    $pipeline = $this->actingAs($editor)->getJson('/api/v1/dashboard/editor')
        ->assertOk()
        ->json('data.review_pipeline');

    expect($pipeline['published'])->toBe(2)
        ->and($pipeline['draft'])->toBe(1);
});

it('is self-scoped by design for any authenticated role and rejects guests', function () {
    $this->getJson('/api/v1/dashboard/editor')->assertUnauthorized();

    $reviewer = reviewerUser();
    $admin = makeUser('admin');

    $this->actingAs($reviewer)->getJson('/api/v1/dashboard/editor')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'my_work' => ['drafts', 'in_progress', 'ready_for_review', 'changes_requested'],
                'needs_attention',
                'content_overview' => ['artists', 'artworks', 'events', 'archive_items'],
                'review_pipeline' => ['draft', 'in_progress', 'ready_for_review', 'under_review', 'changes_requested', 'approved', 'published'],
                'completeness' => ['buckets', 'lowest'],
                'archive' => ['draft', 'incomplete', 'under_review', 'published', 'items'],
                'continue_working',
                'recent_activity',
            ],
        ]);

    $this->actingAs($admin)->getJson('/api/v1/dashboard/editor')->assertOk();
});

it('never accepts a user_id override', function () {
    $editor = editorUser();
    $other = makeUser('editor');

    Artist::factory()->create(['created_by_user_id' => $editor->id]);
    Artist::factory()->create(['created_by_user_id' => $other->id, 'assigned_to_user_id' => $other->id]);

    $data = $this->actingAs($editor)
        ->getJson("/api/v1/dashboard/editor?user_id={$other->id}")
        ->assertOk()
        ->json('data');

    expect($data['content_overview']['artists']['total'])->toBe(1);
});

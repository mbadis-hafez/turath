<?php

use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Event;
use App\Models\Theme;

function eventPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'event_type' => 'exhibition', 'title' => ['ar' => 'المعرض الأول', 'en' => 'First exhibition'],
        'venue_name' => 'دار الفنون', 'city' => 'جدة',
        'start' => ['display' => '1979-03-14', 'year_from' => 1979, 'year_to' => 1979, 'certainty' => 'exact'],
    ], $overrides);
}

it('blocks publishing until the core fields are met, with the same date rule as archive items', function () {
    $editor = editorUser();
    $id = $this->actingAs($editor)->postJson('/api/v1/events', eventPayload([
        'venue_name' => null, 'start' => ['display' => 'أوائل الثمانينيات', 'year_from' => 1980, 'year_to' => 1983, 'certainty' => 'circa'],
    ]))->assertCreated()->json('data.id');

    $res = $this->actingAs($editor)->postJson("/api/v1/events/{$id}/publish")->assertUnprocessable();
    expect(array_keys($res->json('errors')))->toContain('completeness.venue_name', 'completeness.date');

    $this->actingAs($editor)->patchJson("/api/v1/events/{$id}", ['venue_name' => 'دار الفنون', 'date_note' => 'Only "early 1980s" is stated.'])->assertOk();
    $this->actingAs($editor)->postJson("/api/v1/events/{$id}/publish")->assertOk()->assertJsonPath('data.publication_status', 'published');
});

it('requires an event type and a title, and only managers can create or edit', function () {
    $editor = editorUser();
    $this->actingAs($editor)->postJson('/api/v1/events', ['title' => ['ar' => 'x']])->assertUnprocessable();
    $this->actingAs($editor)->postJson('/api/v1/events', ['event_type' => 'talk'])->assertUnprocessable();

    $this->actingAs(makeUser('reader'))->postJson('/api/v1/events', eventPayload())->assertForbidden();
});

it('links artists and artworks as participants, keeps awardees distinct, and hides unpublished participants publicly', function () {
    $editor = editorUser();
    $artist = Artist::factory()->create(['publication_status' => 'published']);
    $hidden = Artist::factory()->create(['publication_status' => 'draft']);
    $work = Artwork::factory()->create(['publication_status' => 'published']);
    $id = $this->actingAs($editor)->postJson('/api/v1/events', eventPayload(['event_type' => 'award']))->json('data.id');

    $this->actingAs($editor)->patchJson("/api/v1/events/{$id}/participants", ['participants' => [
        ['type' => 'artist', 'participant_id' => $artist->id, 'role' => 'awardee', 'note' => 'الجائزة الأولى'],
        ['type' => 'artist', 'participant_id' => $hidden->id, 'role' => 'participant'],
        ['type' => 'artwork', 'participant_id' => $work->id, 'role' => 'exhibited_work'],
    ]])->assertOk()->assertJsonCount(3, 'data');

    $this->actingAs($editor)->patchJson("/api/v1/events/{$id}/participants", ['participants' => [
        ['type' => 'artist', 'participant_id' => $artist->id, 'role' => 'awardee'], ['type' => 'artist', 'participant_id' => $artist->id, 'role' => 'awardee'],
    ]])->assertUnprocessable();
    $this->actingAs($editor)->patchJson("/api/v1/events/{$id}/participants", ['participants' => [['type' => 'artist', 'participant_id' => 999999, 'role' => 'juror']]])->assertUnprocessable();

    $this->actingAs($editor)->postJson("/api/v1/events/{$id}/publish")->assertOk();
    Auth::forgetGuards();

    $public = $this->getJson("/api/v1/events/{$id}")->assertOk()->json('data');
    $roles = collect($public['participants'])->pluck('role')->all();
    expect($roles)->toBe(['awardee', 'exhibited_work'])
        ->and($public['participants'][0]['note'])->toBe('الجائزة الأولى')
        ->and($public['participants'][0]['kind'])->toBe('artist');

    $this->getJson("/api/v1/artists/{$artist->slug}")->assertJsonPath('data.events.0.role', 'awardee')->assertJsonPath('data.events.0.id', $id);
    $this->getJson("/api/v1/artworks/{$work->id}")->assertJsonPath('data.events.0.role', 'exhibited_work');
});

it('keeps drafts private and lists only published events publicly', function () {
    $editor = editorUser();
    $draft = $this->actingAs($editor)->postJson('/api/v1/events', eventPayload())->json('data.id');
    Auth::forgetGuards();

    $this->getJson("/api/v1/events/{$draft}")->assertNotFound();
    $this->getJson('/api/v1/events')->assertJsonCount(0, 'data');
    $this->actingAs($editor)->getJson('/api/v1/events?status=all')->assertJsonCount(1, 'data');
    $this->actingAs($editor)->getJson('/api/v1/admin/events?status=draft')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.gap_count', 0);
    $this->actingAs(makeUser('reader'))->getJson('/api/v1/admin/events')->assertForbidden();
});

it('links archive items to an event without disturbing artist and artwork links, and lists them on the event', function () {
    $editor = editorUser();
    $artist = Artist::factory()->create();
    $event = Event::create(eventPayload()['event_type'] ? ['event_type' => 'exhibition', 'title_ar' => 'معرض', 'publication_status' => 'published'] : []);
    $item = ArchiveItem::factory()->create(['publication_status' => 'published', 'access_level' => 'public', 'rights_status' => 'public_domain']);

    $this->actingAs($editor)->postJson("/api/v1/archive-items/{$item->id}/links", ['linkable_type' => 'event', 'linkable_id' => $event->id, 'role' => 'event_documentation'])->assertCreated();
    $this->actingAs($editor)->postJson("/api/v1/archive-items/{$item->id}/links", ['linkable_type' => 'artist', 'linkable_id' => $artist->id, 'role' => 'about'])->assertCreated();
    $this->actingAs($editor)->postJson("/api/v1/archive-items/{$item->id}/links", ['linkable_type' => 'event', 'linkable_id' => 999999, 'role' => 'about'])->assertUnprocessable();

    $data = $this->actingAs($editor)->getJson("/api/v1/events/{$event->id}")->assertOk()->json('data');
    expect($data['archive_items'])->toHaveCount(1)
        ->and(collect($data['archive_items'][0]['links'])->pluck('role')->all())->toContain('event_documentation', 'about');
    expect($this->actingAs($editor)->getJson("/api/v1/admin/archive-items/{$item->id}")->json('data.links.0.kind'))->toBeIn(['event', 'artist']);
});

it('tags artists, artworks and events with one shared theme', function () {
    $editor = editorUser();
    $theme = Theme::create(['label_ar' => 'التأسيس', 'label_en' => 'Founding']);
    $artist = Artist::factory()->create();
    $work = Artwork::factory()->create();
    $event = Event::create(['event_type' => 'talk', 'title_ar' => 'ندوة']);

    $this->actingAs($editor)->patchJson("/api/v1/artists/{$artist->id}/themes", ['theme_ids' => [$theme->id]])->assertOk();
    $this->actingAs($editor)->patchJson("/api/v1/artworks/{$work->id}/themes", ['theme_ids' => [$theme->id]])->assertOk();
    $this->actingAs($editor)->patchJson("/api/v1/events/{$event->id}/themes", ['theme_ids' => [$theme->id]])->assertOk();

    expect(DB::table('theme_taggables')->where('theme_id', $theme->id)->pluck('taggable_type')->sort()->values()->all())
        ->toBe([Artist::class, Artwork::class, Event::class])
        ->and($artist->themes()->count())->toBe(1)->and(Schema::hasTable('artist_themes'))->toBeFalse();
});

it('builds a year-bucketed timeline tagged by kind, filtered by type, range and theme', function () {
    $theme = Theme::create(['label_ar' => 'التأسيس']);
    $event = Event::create(['event_type' => 'exhibition', 'title_ar' => 'معرض', 'publication_status' => 'published',
        'start_year_from' => 1979, 'start_year_to' => 1979, 'start_date_display' => '1979']);
    $draft = Event::create(['event_type' => 'exhibition', 'title_ar' => 'مسودة', 'publication_status' => 'draft', 'start_year_from' => 1979]);
    $artist = Artist::factory()->create(['publication_status' => 'published', 'birth_year_from' => 1953, 'birth_year_to' => 1953]);
    $work = Artwork::factory()->create(['publication_status' => 'published', 'creation_year_from' => 1979, 'creation_year_to' => 1979]);
    $event->themes()->attach($theme->id);
    $work->themes()->attach($theme->id);

    $only = $this->getJson('/api/v1/timeline')->assertOk()->json('data');
    expect($only)->toHaveCount(1)->and($only[0]['year'])->toBe(1979)->and($only[0]['entries'])->toHaveCount(1)
        ->and($only[0]['entries'][0]['kind'])->toBe('event')->and($only[0]['entries'][0]['id'])->toBe($event->id);

    $all = $this->getJson('/api/v1/timeline?type[]=event&type[]=artist_lifespan&type[]=artwork')->json('data');
    expect(collect($all)->pluck('year')->all())->toBe([1953, 1979])
        ->and(collect($all[1]['entries'])->pluck('kind')->sort()->values()->all())->toBe(['artwork', 'event']);

    $themed = $this->getJson("/api/v1/timeline?type[]=event&type[]=artist_lifespan&type[]=artwork&theme_id[]={$theme->id}")->json('data');
    expect($themed)->toHaveCount(1)->and($themed[0]['entries'])->toHaveCount(2);

    expect($this->getJson('/api/v1/timeline?type[]=artist_lifespan&from=1960')->json('data'))->toBeEmpty();
    $this->getJson('/api/v1/timeline?type[]=bogus')->assertUnprocessable();
    expect($draft->id)->not->toBe($event->id);
});

it('preserves every existing artist theme tag exactly when the events migration runs over artist_themes', function () {
    // DDL commits implicitly in MySQL, so this test cleans up after itself instead of relying on rollback.
    $themes = [Theme::create(['label_ar' => 'أ']), Theme::create(['label_ar' => 'ب'])];
    $artists = Artist::factory()->count(2)->create();
    $pairs = [[$artists[0]->id, $themes[0]->id], [$artists[0]->id, $themes[1]->id], [$artists[1]->id, $themes[1]->id]];
    foreach ($pairs as [$artistId, $themeId]) {
        DB::table('theme_taggables')->insert(['theme_id' => $themeId, 'taggable_type' => Artist::class, 'taggable_id' => $artistId]);
    }
    $work = Artwork::factory()->create();
    DB::table('theme_taggables')->insert(['theme_id' => $themes[0]->id, 'taggable_type' => Artwork::class, 'taggable_id' => $work->id]);

    $migration = require database_path('migrations/2026_09_20_000027_create_events_tables.php');

    try {
        $migration->down();
        expect(Schema::hasTable('artist_themes'))->toBeTrue()
            ->and(DB::table('artist_themes')->orderBy('artist_id')->orderBy('theme_id')->get(['artist_id', 'theme_id'])->map(fn ($r) => [$r->artist_id, $r->theme_id])->all())
            ->toBe([[$artists[0]->id, $themes[0]->id], [$artists[0]->id, $themes[1]->id], [$artists[1]->id, $themes[1]->id]]);

        $migration->up();
        $back = DB::table('theme_taggables')->where('taggable_type', Artist::class)->orderBy('taggable_id')->orderBy('theme_id')->get()
            ->map(fn ($r) => [$r->taggable_id, $r->theme_id])->all();
        expect(Schema::hasTable('artist_themes'))->toBeFalse()->and($back)->toBe([[$artists[0]->id, $themes[0]->id], [$artists[0]->id, $themes[1]->id], [$artists[1]->id, $themes[1]->id]]);
    } finally {
        if (! Schema::hasTable('theme_taggables')) {
            $migration->up();
        }
        DB::table('theme_taggables')->whereIn('theme_id', collect($themes)->pluck('id'))->delete();
        Theme::whereIn('id', collect($themes)->pluck('id'))->delete();
        Artist::whereIn('id', $artists->pluck('id'))->forceDelete();
        Artwork::whereKey($work->id)->forceDelete();
    }
});

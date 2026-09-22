<?php

namespace App\Support\Curation;

use App\Http\Requests\Artist\SyncArtistEntriesRequest;
use App\Models\Artist;
use App\Models\ArtistEntry;
use App\Models\User;
use App\Support\Completeness\PublishGate;
use App\Support\Events\ArtistActivityEventSync;
use App\Support\Proposals\FieldValues;
use App\Support\Proposals\RecordUpdater;

/**
 * The one write path for an artist's sections, shared by the direct-edit
 * controllers and the editorial draft applier, so an approved draft lands
 * exactly where a direct edit would.
 */
class ArtistSections
{
    /**
     * Flat field update, mirroring ArtistUpdateController: fill, gate the
     * transition to published, save — and report what actually changed.
     *
     * @param  array<string, mixed>  $mappedAttributes  column => new value
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function applyFields(Artist $artist, array $mappedAttributes): array
    {
        $publishing = ($mappedAttributes['publication_status'] ?? null) !== null
            && FieldValues::normalize($mappedAttributes['publication_status']) === 'published'
            && FieldValues::differ($artist->getAttribute('publication_status'), 'published');

        if ($publishing) {
            // The gate evaluates the filled model, exactly like the controller.
            $artist->fill($mappedAttributes);
            PublishGate::assertPublishable($artist);
        }

        return RecordUpdater::apply($artist, $mappedAttributes);
    }

    /**
     * Curation update, mirroring ArtistCurationUpdateController. Encrypted
     * contact values never enter the audit diff; the activity row records
     * what changed by count only.
     *
     * @param  array<string, mixed>  $data  validated curation payload
     * @return array<string, array{old: mixed, new: mixed}> applied flat diffs
     */
    public function applyCuration(Artist $artist, array $data, ?User $actor): array
    {
        $editSummary = $data['edit_summary'] ?? null;
        unset($data['edit_summary']);

        $contacts = $data['contacts'] ?? null;
        unset($data['contacts']);

        $applied = RecordUpdater::apply($artist, $data);

        $contactCounts = null;
        if ($contacts !== null) {
            $items = array_map(fn (array $c) => [
                'id' => $c['id'] ?? null, 'name' => $c['name'] ?? null, 'role_note' => $c['role_note'] ?? null,
                'email' => $c['email'] ?? null, 'phone' => $c['phone'] ?? null, 'address' => $c['address'] ?? null,
            ], array_filter($contacts, fn (array $c) => ($c['name'] ?? null) !== null || ($c['email'] ?? null) !== null || ($c['phone'] ?? null) !== null || ($c['address'] ?? null) !== null));
            $contactCounts = ChildSync::sync($artist->contacts(), $items);
        }

        if ($contactCounts !== null && array_sum($contactCounts) > 0) {
            activity($artist->getTable())->performedOn($artist)->causedBy($actor)->event('updated')
                ->withProperties(['edit_summary' => $editSummary, 'contacts_changed' => $contactCounts])
                ->log('contacts changed');
        }

        return $applied;
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $educations
     * @param  array<int, array<string, mixed>>|null  $activities
     */
    public function syncEntries(Artist $artist, ?array $educations, ?array $activities): void
    {
        $groups = ['educations' => $educations, 'activities' => $activities];

        foreach (SyncArtistEntriesRequest::GROUPS as $group => $types) {
            $raw = $groups[$group];
            if ($raw === null) {
                continue;
            }

            $items = array_map(fn (array $i) => [
                'id' => $i['id'] ?? null,
                'type' => $group === 'activities' ? $i['type'] : $types[0],
                'title_ar' => $i['title']['ar'] ?? null, 'title_en' => $i['title']['en'] ?? null,
                'place_ar' => $i['place']['ar'] ?? null, 'place_en' => $i['place']['en'] ?? null,
                'year_from' => $i['year_from'] ?? null, 'year_to' => $i['year_to'] ?? null,
                'note_ar' => $i['note']['ar'] ?? null, 'note_en' => $i['note']['en'] ?? null,
            ], array_filter($raw, fn (array $i) => ($i['title']['ar'] ?? null) !== null || ($i['title']['en'] ?? null) !== null));

            $before = $group === 'activities'
                ? $artist->entries()->whereIn('type', $types)->get()
                : null;

            ChildSync::sync($artist->entries()->whereIn('type', $types), $items);

            if ($before !== null) {
                ArtistActivityEventSync::reconcile($artist, $before);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $links
     */
    public function syncSocialLinks(Artist $artist, array $links): void
    {
        ChildSync::sync($artist->socialLinks(), array_map(fn (array $l) => [
            'id' => $l['id'] ?? null, 'platform' => $l['platform'], 'url' => $l['url'], 'is_public' => $l['is_public'] ?? false,
        ], $links));
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $educations
     * @param  array<int, array<string, mixed>>|null  $activities
     */
    public function entriesDiffer(Artist $artist, ?array $educations, ?array $activities): bool
    {
        $groups = ['educations' => $educations, 'activities' => $activities];

        foreach (SyncArtistEntriesRequest::GROUPS as $group => $types) {
            $raw = $groups[$group];
            if ($raw === null) {
                continue;
            }

            $incoming = array_map(fn (array $i) => [
                'id' => $i['id'] ?? null,
                'title_ar' => $i['title']['ar'] ?? null, 'title_en' => $i['title']['en'] ?? null,
                'place_ar' => $i['place']['ar'] ?? null, 'place_en' => $i['place']['en'] ?? null,
                'year_from' => $i['year_from'] ?? null, 'year_to' => $i['year_to'] ?? null,
                'note_ar' => $i['note']['ar'] ?? null, 'note_en' => $i['note']['en'] ?? null,
            ], array_filter($raw, fn (array $i) => ($i['title']['ar'] ?? null) !== null || ($i['title']['en'] ?? null) !== null));

            if (ChildDiffers::check($this->normalizedEntries($artist, $types), $incoming)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $links
     */
    public function socialLinksDiffer(Artist $artist, array $links): bool
    {
        $live = $artist->socialLinks()->get()
            ->map(fn ($l) => ['id' => $l->id, 'platform' => $l->platform, 'url' => $l->url, 'is_public' => $l->is_public])
            ->all();

        $incoming = array_map(fn (array $l) => [
            'id' => $l['id'] ?? null, 'platform' => $l['platform'], 'url' => $l['url'], 'is_public' => $l['is_public'] ?? false,
        ], $links);

        return ChildDiffers::check($live, $incoming);
    }

    /**
     * @param  array<int, array<string, mixed>>  $contacts
     */
    public function contactsDiffer(Artist $artist, array $contacts): bool
    {
        $live = $artist->contacts()->get()
            ->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'role_note' => $c->role_note,
                'email' => $c->email, 'phone' => $c->phone, 'address' => $c->address,
            ])->all();

        $incoming = array_map(fn (array $c) => [
            'id' => $c['id'] ?? null, 'name' => $c['name'] ?? null, 'role_note' => $c['role_note'] ?? null,
            'email' => $c['email'] ?? null, 'phone' => $c['phone'] ?? null, 'address' => $c['address'] ?? null,
        ], array_filter($contacts, fn (array $c) => ($c['name'] ?? null) !== null || ($c['email'] ?? null) !== null || ($c['phone'] ?? null) !== null || ($c['address'] ?? null) !== null));

        return ChildDiffers::check($live, $incoming);
    }

    /**
     * @param  array<int, string>  $types
     * @return array<int, array<string, mixed>>
     */
    private function normalizedEntries(Artist $artist, array $types): array
    {
        return $artist->entries()->whereIn('type', $types)->get()
            ->map(fn (ArtistEntry $e) => [
                'id' => $e->id,
                'title_ar' => $e->title_ar, 'title_en' => $e->title_en,
                'place_ar' => $e->place_ar, 'place_en' => $e->place_en,
                'year_from' => $e->year_from, 'year_to' => $e->year_to,
                'note_ar' => $e->note_ar, 'note_en' => $e->note_en,
            ])->all();
    }
}

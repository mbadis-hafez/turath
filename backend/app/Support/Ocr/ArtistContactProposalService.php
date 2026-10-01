<?php

namespace App\Support\Ocr;

use App\Enums\DocumentType;
use App\Enums\ExtractionMethod;
use App\Enums\ProposalStatus;
use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\ArtistContact;
use App\Models\ArtistContactProposal;
use App\Models\EditProposal;
use App\Models\File;
use App\Models\FileOcrContactProposal;
use App\Models\FileOcrFormField;
use App\Models\OcrHandwritingSuggestion;
use App\Models\User;
use App\Support\Proposals\EditorialDraftService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An authorization letter never modifies an Artist or ArtistContact directly:
 *
 *   document → artist identity (reviewer-transcribed name)
 *   → candidate Artists (ArtistCandidateFinder) → reviewer confirms one
 *   → contact values (pre-filled from form fields) → reviewer confirms them
 *   → one ArtistContactProposal per value, carried by an editorial draft
 *     submitted to the archivist review queue
 *   → a *different* reviewer approves it (ProposalController::authorizeReview)
 *   → EditorialDraftService::apply → ArtistSections::applyCuration.
 *
 * Everything past "submitted" is the existing editorial-draft machinery, so an
 * OCR-originated contact change is reviewed, applied and audited exactly like
 * one typed into the artist curation page — including the activity log that
 * records contact changes by count only, never by value. The per-value rows
 * follow the draft's state (follow()) and keep where each value came from.
 */
class ArtistContactProposalService
{
    private const CONTACT_KEYS = ArtistContactProposal::FIELDS;

    public function confirmArtist(File $file, Artist $artist, User $user): FileOcrContactProposal
    {
        $this->assertAuthorizationLetter($file);

        $row = $file->ocrContactProposal;
        if ($row !== null && $this->openProposal($row) !== null) {
            throw $this->conflict('A contact proposal from this document is still open. It must be resolved before the artist can change.');
        }

        return $file->ocrContactProposal()->updateOrCreate([], [
            'artist_id' => $artist->id,
            'artist_confirmed_by_user_id' => $user->id,
            'artist_confirmed_at' => now(),
            'target_contact_id' => null,
        ]);
    }

    /**
     * @param  array{name?: ?string, role_note?: ?string, email?: ?string, phone?: ?string, address?: ?string}  $values  what the reviewer confirmed
     */
    public function propose(File $file, ArchiveItem $item, User $user, array $values, ?int $targetContactId, ?string $note): FileOcrContactProposal
    {
        $this->assertAuthorizationLetter($file);

        $row = $file->ocrContactProposal;
        $artist = $row?->artist;
        if ($row === null || $artist === null) {
            throw ValidationException::withMessages(['artist_id' => ['Confirm which artist this document belongs to first.']]);
        }

        $values = array_map(fn ($v) => is_string($v) && trim($v) !== '' ? trim($v) : null, $values);
        if (array_filter(array_intersect_key($values, array_flip(self::CONTACT_KEYS))) === []) {
            throw ValidationException::withMessages(['values' => ['Enter at least one of email, phone or address.']]);
        }

        $target = null;
        if ($targetContactId !== null) {
            $target = $artist->contacts()->whereKey($targetContactId)->first();
            if ($target === null) {
                throw ValidationException::withMessages(['target_contact_id' => ['That contact does not belong to the confirmed artist.']]);
            }
        }

        $this->assertNoCompetingDraft($row, $artist, $user);

        [$contacts, $index] = $this->contactList($artist, $target, $values);
        $payload = ['curation' => ['contacts' => $contacts]];
        $rationale = $this->rationale($item, $file, $note);
        $proposed = $this->proposedValues($file, $artist, $target, $values, $user);

        try {
            // One transaction, so a submit the draft service refuses (nothing differs, invalid value) leaves no stray draft behind.
            DB::transaction(function () use ($file, $row, $artist, $target, $user, $payload, $rationale, $proposed) {
                // The earlier round from this document is replaced, not decided. Done before the
                // draft is rewritten, so follow() doesn't read the rewrite as someone else's.
                ArtistContactProposal::query()
                    ->where('file_id', $file->id)
                    ->whereIn('status', ArtistContactProposal::OPEN_STATUSES)
                    ->update(['status' => ArtistContactProposal::STATUS_SUPERSEDED, 'superseded_reason' => ArtistContactProposal::SUPERSEDED_BY_NEWER_PROPOSAL]);

                $drafts = new EditorialDraftService;
                $drafts->upsert($artist, $user, $payload, $rationale);
                $proposal = $drafts->submit($artist, $user);

                foreach ($proposed as $attributes) {
                    ArtistContactProposal::create([...$attributes, 'edit_proposal_id' => $proposal->id, 'status' => ArtistContactProposal::STATUS_PENDING]);
                }

                $row->update([
                    'edit_proposal_id' => $proposal->id,
                    'target_contact_id' => $target?->id,
                    'proposed_by_user_id' => $user->id,
                    'proposed_at' => now(),
                ]);
            });
        } catch (ValidationException $e) {
            throw $this->rekeyed($e, $index);
        }

        return $row->refresh();
    }

    /**
     * Keeps a draft's document-sourced values in step with the draft (called by
     * EditProposalObserver). Only undecided values move: a decision, once
     * recorded, stays what it was.
     */
    public function follow(EditProposal $proposal): void
    {
        $open = ArtistContactProposal::query()
            ->where('edit_proposal_id', $proposal->id)
            ->whereIn('status', ArtistContactProposal::OPEN_STATUSES);

        if ($proposal->wasChanged('payload')) {
            // propose() supersedes its own earlier round before rewriting the draft, so a
            // rewrite that finds values still open came from elsewhere (the artist page)
            // and the draft may no longer carry them.
            $open->update(['status' => ArtistContactProposal::STATUS_SUPERSEDED, 'superseded_reason' => ArtistContactProposal::SUPERSEDED_DRAFT_REWRITTEN]);

            return;
        }

        $status = match ($proposal->status) {
            ProposalStatus::Pending->value => ArtistContactProposal::STATUS_PENDING,
            ProposalStatus::ChangesRequested->value => ArtistContactProposal::STATUS_CHANGES_REQUESTED,
            ProposalStatus::Approved->value => ArtistContactProposal::STATUS_APPROVED,
            ProposalStatus::Rejected->value => ArtistContactProposal::STATUS_REJECTED,
            ProposalStatus::Superseded->value => ArtistContactProposal::STATUS_SUPERSEDED,
            default => null,
        };
        if ($status === null) {
            return;
        }

        $open->update([
            'status' => $status,
            'superseded_reason' => $status === ArtistContactProposal::STATUS_SUPERSEDED ? ArtistContactProposal::SUPERSEDED_OTHER_APPROVED : null,
            'reviewed_by_user_id' => $proposal->reviewed_by_user_id,
            'reviewed_at' => $proposal->reviewed_at,
            'review_note' => $proposal->review_note,
        ]);
    }

    public function openProposal(FileOcrContactProposal $row): ?EditProposal
    {
        $proposal = $row->editProposal;

        return $proposal !== null && in_array($proposal->status, [
            ProposalStatus::Draft->value, ProposalStatus::Pending->value, ProposalStatus::ChangesRequested->value,
        ], true) ? $proposal : null;
    }

    private function assertAuthorizationLetter(File $file): void
    {
        if ($file->document_type !== DocumentType::ArtistAuthorization) {
            throw ValidationException::withMessages(['document_type' => ['Contact proposals are only available for artist authorization letters.']]);
        }
    }

    /**
     * EditorialDraftService::upsert replaces the caller's open draft wholesale,
     * so a draft the reviewer started by hand on the artist page must never be
     * silently overwritten from here. The one draft this flow may replace is
     * its own, after a reviewer sent it back with changes requested.
     */
    private function assertNoCompetingDraft(FileOcrContactProposal $row, Artist $artist, User $user): void
    {
        $open = EditProposal::query()
            ->where('citable_type', Artist::class)
            ->where('citable_id', $artist->id)
            ->where('proposed_by_user_id', $user->id)
            ->whereIn('status', [ProposalStatus::Draft->value, ProposalStatus::Pending->value, ProposalStatus::ChangesRequested->value])
            ->first();

        if ($open === null) {
            return;
        }

        if ($open->status === ProposalStatus::Pending->value) {
            throw $this->conflict('Your proposal for this artist is already awaiting review.', $open->id);
        }

        if ($open->id !== $row->edit_proposal_id) {
            throw $this->conflict('You have an unsubmitted draft on this artist. Submit or discard it from the artist page first.', $open->id);
        }
    }

    /**
     * The curation contacts section is a full-list replace (ChildSync deletes
     * rows missing from the list), so every existing contact is carried over
     * unchanged by id; only the target (or a new row) differs.
     *
     * @param  array<string, ?string>  $values
     * @return array{0: array<int, array<string, mixed>>, 1: int}
     */
    private function contactList(Artist $artist, ?ArtistContact $target, array $values): array
    {
        $contacts = [];
        $index = null;

        foreach ($artist->contacts()->orderBy('sort')->get() as $i => $c) {
            $row = ['id' => $c->id, 'name' => $c->name, 'role_note' => $c->role_note, 'email' => $c->email, 'phone' => $c->phone, 'address' => $c->address];
            if ($target !== null && $c->id === $target->id) {
                // Only the values the reviewer actually confirmed replace what's there.
                $row = [...$row, ...array_filter($values, fn ($v) => $v !== null)];
                $index = $i;
            }
            $contacts[] = $row;
        }

        if ($index === null) {
            $contacts[] = [
                'name' => $values['name'] ?? null, 'role_note' => $values['role_note'] ?? null,
                'email' => $values['email'] ?? null, 'phone' => $values['phone'] ?? null, 'address' => $values['address'] ?? null,
            ];
            $index = count($contacts) - 1;
        }

        return [$contacts, $index];
    }

    private function rationale(ArchiveItem $item, File $file, ?string $note): string
    {
        $reference = $item->legacy_ref ?? '#'.$item->id;
        $text = "Contact details read from the artist authorization letter on archive item {$reference} (file #{$file->id}), confirmed by the proposer against the scanned document.";

        $note = $note !== null ? trim($note) : '';

        return $note === '' ? $text : $text."\n\n".$note;
    }

    /**
     * One proposal's attributes per contact value that would change, with where
     * the value came from. A value the target contact already holds isn't a
     * change, so it isn't proposed. A value that differs from its form field's
     * reading was typed or corrected by the proposer. Provenance is copied, not
     * just referenced: re-running OCR replaces the regions and form fields.
     *
     * @param  array<string, ?string>  $values
     * @return list<array<string, mixed>>
     */
    private function proposedValues(File $file, Artist $artist, ?ArtistContact $target, array $values, User $user): array
    {
        $formFields = $file->ocrFormFields()->with(['valueRegion', 'labelRegion'])->orderBy('id')->get();
        $extracted = (new ArtistAuthorizationFields)->extract($formFields);

        $out = [];
        foreach (self::CONTACT_KEYS as $key) {
            $value = $values[$key] ?? null;
            if ($value === null) {
                continue;
            }

            $current = $target?->getAttribute($key);
            $current = is_string($current) && trim($current) !== '' ? trim($current) : null;
            if ($current === $value) {
                continue;
            }

            $source = $extracted[$key];
            $field = $source['form_field_id'] === null ? null : $formFields->firstWhere('id', $source['form_field_id']);
            $region = $field?->valueRegion;
            $fromDocument = $source['value'] !== null && trim($source['value']) === $value;
            $method = $fromDocument && $source['method'] !== null ? $source['method'] : ExtractionMethod::ManuallyTranscribed->value;

            $out[] = [
                'file_id' => $file->id,
                'artist_id' => $artist->id,
                'action' => $target === null ? ArtistContactProposal::ACTION_NEW_CONTACT : ArtistContactProposal::ACTION_UPDATE_CONTACT,
                'target_contact_id' => $target?->id,
                'field' => $key,
                'proposed_value' => $value,
                'replaces_existing' => $current !== null,
                'source_page' => ($region ?? $field?->labelRegion)?->page_number,
                'source_region_id' => $region?->id,
                'source_bbox' => $region?->bbox,
                'source_form_field_id' => $field?->id,
                'source_label' => $field?->field_label,
                'extraction_method' => $method,
                // An OCR engine's own confidence; a person's transcription has none to report.
                'confidence' => $method === ExtractionMethod::OcrDerived->value ? $region?->confidence : null,
                'machine_suggestion' => $fromDocument && $field !== null ? $this->machineSuggestion($file, $field) : null,
                'has_correction_mark' => ($field->has_correction_mark ?? false) || ($region->has_correction_mark ?? false),
                'edited_by_proposer' => ! $fromDocument,
                'proposed_by_user_id' => $user->id,
                'proposed_at' => now(),
            ];
        }

        return $out;
    }

    /**
     * The handwriting suggestion a transcription was taken from, if any: which
     * model read it, how sure it was and what the reviewer decided — never the
     * suggested text itself.
     *
     * @return array{provider: string, model: string, model_version: string|null, confidence: float|null, decision: string|null}|null
     */
    private function machineSuggestion(File $file, FileOcrFormField $field): ?array
    {
        $sha = $field->valueRegion?->crop_sha256;
        if ($field->manual_value === null || $sha === null) {
            return null;
        }

        $suggestion = OcrHandwritingSuggestion::query()
            ->where('file_id', $file->id)
            ->where('crop_sha256', $sha)
            ->where('final_text', $field->manual_value)
            ->whereNotNull('decision')
            ->latest('decided_at')
            ->first();

        return $suggestion === null ? null : [
            'provider' => $suggestion->provider,
            'model' => $suggestion->model,
            'model_version' => $suggestion->model_version,
            'confidence' => $suggestion->confidence,
            'decision' => $suggestion->decision,
        ];
    }

    /**
     * The draft validator speaks in the draft's shape ("curation.contacts.3.email");
     * the reviewer's form has one contact, so its errors come back under the
     * form's own keys. Errors on other, carried-over contacts keep their key.
     */
    private function rekeyed(ValidationException $e, int $index): ValidationException
    {
        $prefix = "curation.contacts.{$index}.";
        $messages = [];
        foreach ($e->errors() as $key => $msgs) {
            $key = match (true) {
                str_starts_with($key, $prefix) => substr($key, strlen($prefix)),
                $key === 'payload' => 'values',
                default => $key,
            };
            $messages[$key] = [...($messages[$key] ?? []), ...$msgs];
        }

        return ValidationException::withMessages($messages);
    }

    private function conflict(string $message, ?string $proposalId = null): HttpResponseException
    {
        return new HttpResponseException(response()->json(array_filter([
            'message' => $message,
            'proposal_id' => $proposalId,
        ]), 409));
    }
}

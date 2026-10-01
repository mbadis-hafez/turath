<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DocumentType;
use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\File;
use App\Models\FileOcrContactProposal;
use App\Models\User;
use App\Support\Ocr\ArtistAuthorizationFields;
use App\Support\Ocr\ArtistCandidateFinder;
use App\Support\Ocr\ArtistContactProposalPresenter;
use App\Support\Ocr\ArtistContactProposalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The authorization-letter → ArtistContact flow (see ArtistContactProposalService).
 * Reading the flow and confirming the artist need only archive.manage — neither
 * changes an Artist. Proposing needs proposals.submit, exactly like any other
 * editorial draft; approving happens in the existing review queue.
 *
 * Existing contact values are internal-only (D100): they're returned only to
 * users who can see them on the artist curation page (artists.manage), and
 * only those users may target an existing contact rather than add a new one.
 * Each proposed value, its provenance and its state come from
 * ArtistContactProposalPresenter.
 */
class ArchiveItemFileOcrArtistContactController
{
    public function show(Request $request, ArchiveItem $archiveItem): JsonResponse
    {
        $file = $this->fileFor($archiveItem);
        $data = $request->validate(['name' => ['nullable', 'string', 'max:255']]);

        return response()->json(['data' => $this->present($file, $archiveItem, $request->user(), $data['name'] ?? null)]);
    }

    public function confirmArtist(Request $request, ArchiveItem $archiveItem, ArtistContactProposalService $service): JsonResponse
    {
        $file = $this->fileFor($archiveItem);
        $data = $request->validate(['artist_id' => ['required', 'integer']]);
        $artist = Artist::query()->findOrFail($data['artist_id']);

        $service->confirmArtist($file, $artist, $request->user());

        return response()->json(['data' => $this->present($file->refresh(), $archiveItem, $request->user(), null)]);
    }

    public function propose(Request $request, ArchiveItem $archiveItem, ArtistContactProposalService $service): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('proposals.submit'), 403);
        $file = $this->fileFor($archiveItem);

        // Types and bounds only — the contact rules themselves (email format, lengths) are
        // UpdateArtistCurationRequest's, applied when the draft is validated.
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:2000'],
            'role_note' => ['nullable', 'string', 'max:2000'],
            'email' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:2000'],
            'address' => ['nullable', 'string', 'max:2000'],
            'target_contact_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $targetContactId = $data['target_contact_id'] ?? null;
        abort_if($targetContactId !== null && ! $user->can('artists.manage'), 403);

        $service->propose(
            $file, $archiveItem, $user,
            array_intersect_key($data, array_flip(['name', 'role_note', 'email', 'phone', 'address'])),
            $targetContactId,
            $data['note'] ?? null,
        );

        return response()->json(['data' => $this->present($file->refresh(), $archiveItem, $user, null)]);
    }

    private function fileFor(ArchiveItem $archiveItem): File
    {
        $file = $archiveItem->files()->where('role', 'original')->latest('id')->first();
        abort_if($file === null, 404);

        return $file;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(File $file, ArchiveItem $item, User $user, ?string $nameOverride): array
    {
        $extracted = (new ArtistAuthorizationFields)->extract($file->ocrFormFields()->orderBy('id')->get());
        $row = $file->ocrContactProposal()->with(['artist.contacts', 'editProposal'])->first();
        $mayReadContacts = $user->can('artists.manage');

        $searchName = $nameOverride ?? $extracted['artist_name']['value'];
        $candidates = (new ArtistCandidateFinder)->find($searchName, $item);

        return [
            'applicable' => $file->document_type === DocumentType::ArtistAuthorization,
            'extracted' => $extracted,
            'search_name' => $searchName,
            'candidates' => array_map(fn (array $c) => [
                'artist' => $this->artistSummary($c['artist']),
                'strength' => $c['strength']->value,
                'basis' => $c['basis'],
            ], $candidates),
            'confirmed_artist' => $row?->artist === null ? null : [
                ...$this->artistSummary($row->artist),
                'confirmed_by_user_id' => $row->artist_confirmed_by_user_id,
                'confirmed_at' => $row->artist_confirmed_at?->toIso8601String(),
            ],
            'existing_contacts' => $row?->artist === null || ! $mayReadContacts ? null : $row->artist->contacts->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'role_note' => $c->role_note, 'email' => $c->email, 'phone' => $c->phone, 'address' => $c->address,
            ])->values()->all(),
            'proposal' => $this->proposalSummary($row),
            'contact_proposals' => (new ArtistContactProposalPresenter)->forFile($file, $user),
            'can_propose' => $user->can('proposals.submit'),
            'can_target_existing' => $mayReadContacts,
        ];
    }

    /**
     * @return array{id: int, slug: string|null, name: array{ar: string|null, en: string|null}, legacy_code: string|null}
     */
    private function artistSummary(Artist $artist): array
    {
        return [
            'id' => $artist->id,
            'slug' => $artist->slug,
            'name' => ['ar' => $artist->name_ar, 'en' => $artist->name_en],
            'legacy_code' => $artist->legacy_code,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function proposalSummary(?FileOcrContactProposal $row): ?array
    {
        if ($row === null || $row->edit_proposal_id === null) {
            return null;
        }

        return [
            'edit_proposal_id' => $row->edit_proposal_id,
            'status' => $row->editProposal?->status,
            'review_note' => $row->editProposal?->review_note,
            'reviewed_at' => $row->editProposal?->reviewed_at?->toIso8601String(),
            'target_contact_id' => $row->target_contact_id,
            'proposed_by_user_id' => $row->proposed_by_user_id,
            'proposed_at' => $row->proposed_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ArchiveItemType;
use App\Enums\AuthorizationLetterStatus;
use App\Enums\SubmissionStatus;
use App\Http\Requests\MaterialSubmission\StoreMaterialSubmissionRequest;
use App\Models\MaterialSubmission;
use App\Models\MaterialSubmissionFile;
use App\Models\ReviewQueueItem;
use App\Support\Submissions\SubmissionCataloger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaterialSubmissionController
{
    /**
     * The forward path a staff member may move a submission along by hand.
     * Cataloging (to authorization_pending) and rejecting have their own endpoints.
     */
    private const TRANSITIONS = [
        'submitted' => ['initial_review', 'withdrawn'],
        'initial_review' => ['cataloging', 'withdrawn'],
        'cataloging' => ['withdrawn'],
        'authorization_pending' => ['published', 'withdrawn'],
    ];

    /** Public and anonymous (D149). Only an id and status come back: there is no public read of a submission. */
    public function store(StoreMaterialSubmissionRequest $request): JsonResponse
    {
        $data = $request->validated();

        $submission = DB::transaction(function () use ($data, $request) {
            $submission = MaterialSubmission::create([
                'submitter_name' => trim($data['submitter_name']),
                'submitter_contact' => trim($data['submitter_contact']),
                'submitter_role' => $data['submitter_role'],
                'city' => $data['city'] ?? null,
                'description' => $data['description'],
                'status' => SubmissionStatus::Submitted->value,
            ]);

            foreach ((array) $request->file('files', []) as $upload) {
                $path = $upload->store("material-submissions/{$submission->id}", 'local');
                $submission->files()->create([
                    'disk' => 'local',
                    'path' => $path,
                    // Sniffed from the content by the validator, not taken from the client.
                    'mime_type' => $upload->getMimeType() ?? 'application/octet-stream',
                    'size_bytes' => $upload->getSize(),
                    'original_filename' => mb_substr($upload->getClientOriginalName(), 0, 255),
                ]);
            }

            // D158: F10's existing queue, with its own review type.
            ReviewQueueItem::create([
                'citable_type' => MaterialSubmission::class,
                'citable_id' => $submission->id,
                'review_type' => 'material_intake',
                'note' => mb_substr($data['description'], 0, 255),
                'status' => 'pending',
            ]);

            return $submission;
        });

        return response()->json(['data' => ['id' => $submission->id, 'status' => $submission->status]], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::enum(SubmissionStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = MaterialSubmission::query()->withCount('files');
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        $paginated = $query->orderByDesc('id')->paginate((int) ($data['per_page'] ?? 24));

        return response()->json([
            'data' => $paginated->getCollection()->map(fn (MaterialSubmission $s) => self::present($s, false))->values(),
            'meta' => [
                'current_page' => $paginated->currentPage(), 'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(), 'total' => $paginated->total(),
            ],
        ]);
    }

    public function show(MaterialSubmission $submission): JsonResponse
    {
        return response()->json(['data' => self::present($submission, true)]);
    }

    public function update(Request $request, MaterialSubmission $submission): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['initial_review', 'cataloging', 'published', 'withdrawn'])],
            // D152: only ever set here, by a person. Nothing infers these from the submitter's name.
            'linked_artist_id' => ['sometimes', 'nullable', 'integer', 'exists:artists,id'],
            'linked_artwork_id' => ['sometimes', 'nullable', 'integer', 'exists:artworks,id'],
            'staff_notes' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'authorization_letter_status' => ['sometimes', Rule::enum(AuthorizationLetterStatus::class)],
        ]);

        $current = SubmissionStatus::from($submission->status);
        abort_if($current->isTerminal() && $request->hasAny(['status']), 422, 'This submission is already closed.');

        $newStatus = $data['status'] ?? null;
        if ($newStatus !== null) {
            if (! in_array($newStatus, self::TRANSITIONS[$current->value] ?? [], true)) {
                throw ValidationException::withMessages(['status' => ["A submission cannot move from {$current->value} to {$newStatus}."]]);
            }
            if ($newStatus === 'published') {
                $letter = $data['authorization_letter_status'] ?? $submission->authorization_letter_status;
                if (! in_array($letter, ['signed', 'not_applicable'], true)) {
                    throw ValidationException::withMessages(['status' => ['A signed authorization letter is required before this material is published (D157).']]);
                }
            }
        }

        DB::transaction(function () use ($submission, $data, $newStatus, $request) {
            $submission->fill($data);
            $submission->reviewed_by_user_id = $request->user()?->id;
            $submission->reviewed_at = now();
            $submission->save();

            if ($newStatus === 'withdrawn') {
                (new SubmissionCataloger)->purgeFiles($submission);
                $this->closeQueue($submission, 'dismissed');
            } elseif ($newStatus !== null) {
                $this->closeQueue($submission, 'acknowledged');
            }
        });

        return response()->json(['data' => self::present($submission->refresh(), true)]);
    }

    public function catalog(Request $request, MaterialSubmission $submission): JsonResponse
    {
        abort_unless(in_array($submission->status, ['initial_review', 'cataloging'], true), 422, 'Only a submission under review can be cataloged.');

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.item_type' => ['required', Rule::enum(ArchiveItemType::class)],
            'items.*.title' => ['required', 'array'],
            'items.*.title.ar' => ['nullable', 'string', 'max:255'],
            'items.*.title.en' => ['nullable', 'string', 'max:255'],
            'items.*.file_ids' => ['nullable', 'array'],
            'items.*.file_ids.*' => ['integer'],
        ]);
        foreach ($data['items'] as $i => $item) {
            if (($item['title']['ar'] ?? null) === null && ($item['title']['en'] ?? null) === null) {
                throw ValidationException::withMessages(["items.{$i}.title" => ['Each item needs a title in Arabic or English.']]);
            }
        }

        $items = (new SubmissionCataloger)->catalog($submission, $data['items'], $request->user());
        $this->closeQueue($submission, 'acknowledged');

        return response()->json(['data' => [
            ...self::present($submission->refresh(), true),
            'created_archive_item_ids' => collect($items)->pluck('id')->all(),
        ]], 201);
    }

    public function reject(Request $request, MaterialSubmission $submission): JsonResponse
    {
        abort_if(SubmissionStatus::from($submission->status)->isTerminal(), 422, 'This submission is already closed.');
        $data = $request->validate(['staff_notes' => ['required', 'string', 'min:3', 'max:20000']]);

        DB::transaction(function () use ($submission, $data, $request) {
            $submission->update([
                'status' => SubmissionStatus::Rejected->value,
                'staff_notes' => $data['staff_notes'],
                'reviewed_by_user_id' => $request->user()?->id,
                'reviewed_at' => now(),
            ]);
            (new SubmissionCataloger)->purgeFiles($submission);
            $this->closeQueue($submission, 'dismissed');
        });

        return response()->json(['data' => self::present($submission->refresh(), true)]);
    }

    /** Staff-only preview of a staged file. */
    public function file(MaterialSubmission $submission, int $file): StreamedResponse
    {
        $record = MaterialSubmissionFile::where('material_submission_id', $submission->id)->findOrFail($file);
        abort_unless(Storage::disk($record->disk)->exists($record->path), 404);

        return Storage::disk($record->disk)->response($record->path, $record->original_filename);
    }

    private function closeQueue(MaterialSubmission $submission, string $status): void
    {
        ReviewQueueItem::where('citable_type', MaterialSubmission::class)->where('citable_id', $submission->id)
            ->where('status', 'pending')->update(['status' => $status]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(MaterialSubmission $s, bool $detail): array
    {
        $base = [
            'id' => $s->id,
            'submitter_name' => $s->submitter_name,
            'submitter_contact' => $s->submitter_contact,
            'submitter_role' => $s->submitter_role,
            'city' => $s->city,
            'description' => $s->description,
            'status' => $s->status,
            'file_count' => $s->files_count ?? $s->files()->count(),
            'submitted_at' => $s->submitted_at->toIso8601String(),
        ];

        if (! $detail) {
            return $base;
        }

        return [
            ...$base,
            'linked_artist' => $s->linkedArtist ? ['id' => $s->linkedArtist->id, 'name' => ['ar' => $s->linkedArtist->name_ar, 'en' => $s->linkedArtist->name_en]] : null,
            'linked_artwork' => $s->linkedArtwork ? ['id' => $s->linkedArtwork->id, 'title' => ['ar' => $s->linkedArtwork->title_ar, 'en' => $s->linkedArtwork->title_en]] : null,
            'authorization_letter_status' => $s->authorization_letter_status,
            'staff_notes' => $s->staff_notes,
            'reviewed_at' => $s->reviewed_at?->toIso8601String(),
            'files' => $s->files()->get()->map(fn (MaterialSubmissionFile $f) => [
                'id' => $f->id, 'name' => $f->original_filename, 'mime_type' => $f->mime_type, 'size_bytes' => $f->size_bytes,
                'url' => "/api/v1/material-submissions/{$s->id}/files/{$f->id}",
            ])->values(),
            'archive_items' => $s->archiveItems()->get()->map(fn ($i) => [
                'id' => $i->id, 'title' => ['ar' => $i->title_ar, 'en' => $i->title_en], 'item_type' => $i->item_type,
            ])->values(),
        ];
    }
}

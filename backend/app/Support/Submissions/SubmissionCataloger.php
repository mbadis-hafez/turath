<?php

namespace App\Support\Submissions;

use App\Enums\SubmissionStatus;
use App\Models\ArchiveItem;
use App\Models\ArchiveItemLink;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\File;
use App\Models\MaterialSubmission;
use App\Models\MaterialSubmissionFile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SubmissionCataloger
{
    /**
     * D155: turn staged material into real, non-public Archive Items.
     *
     * Access and publication are fixed here regardless of anything the
     * submitter said: institution_only, draft. The human authorization step
     * (D157) is what sets the final access level, never the intake form.
     *
     * @param  array<int, array{title: array{ar?: ?string, en?: ?string}, item_type: string, file_ids?: array<int, int>}>  $items
     * @return array<int, ArchiveItem>
     */
    public function catalog(MaterialSubmission $submission, array $items, User $by): array
    {
        $staged = $submission->files()->get()->keyBy('id');
        $assigned = collect($items)->flatMap(fn ($i) => $i['file_ids'] ?? [])->all();

        foreach ($assigned as $fileId) {
            if (! $staged->has($fileId)) {
                throw ValidationException::withMessages(['items' => ["File #{$fileId} does not belong to this submission."]]);
            }
        }
        if (count($assigned) !== count(array_unique($assigned))) {
            throw ValidationException::withMessages(['items' => ['A file can only go into one item.']]);
        }
        $unassigned = $staged->keys()->diff($assigned);
        if ($unassigned->isNotEmpty()) {
            throw ValidationException::withMessages(['items' => ['Every staged file must be assigned to an item: '.$unassigned->implode(', ').'.']]);
        }

        return DB::transaction(function () use ($submission, $items, $staged, $by) {
            $created = [];

            foreach ($items as $spec) {
                $item = ArchiveItem::create([
                    'item_type' => $spec['item_type'],
                    'title_ar' => $spec['title']['ar'] ?? null,
                    'title_en' => $spec['title']['en'] ?? null,
                    'internal_notes' => "From material submission #{$submission->id}".($submission->city ? " ({$submission->city})" : '').': '.$submission->description,
                    'access_level' => 'institution_only',
                    'publication_status' => 'draft',
                    'rights_status' => 'unknown',
                    'source_material_submission_id' => $submission->id,
                ]);

                foreach ($spec['file_ids'] ?? [] as $fileId) {
                    $this->promote($staged->get($fileId), $item, $by);
                }

                // D156: the existing `donor` role, not a new one.
                foreach ([[Artist::class, $submission->linked_artist_id], [Artwork::class, $submission->linked_artwork_id]] as [$class, $id]) {
                    if ($id !== null) {
                        ArchiveItemLink::firstOrCreate(['archive_item_id' => $item->id, 'linkable_type' => $class, 'linkable_id' => $id, 'role' => 'donor']);
                    }
                }

                $created[] = $item;
            }

            $submission->update(['status' => SubmissionStatus::AuthorizationPending->value, 'reviewed_by_user_id' => $by->id, 'reviewed_at' => now()]);

            return $created;
        });
    }

    /** Moves (not copies) the staged bytes into permanent storage and retires the staging row. */
    private function promote(MaterialSubmissionFile $staged, ArchiveItem $item, User $by): void
    {
        $disk = Storage::disk($staged->disk);
        $target = 'archive-files/'.basename($staged->path);
        $disk->move($staged->path, $target);

        $absolute = $disk->path($target);
        $size = str_starts_with($staged->mime_type, 'image/') ? (@getimagesize($absolute) ?: [null, null]) : [null, null];

        File::create([
            'archive_item_id' => $item->id,
            'role' => 'original',
            'disk' => $staged->disk,
            'path' => $target,
            'mime_type' => $staged->mime_type,
            'size_bytes' => $staged->size_bytes,
            'sha256' => hash_file('sha256', $absolute),
            'width_px' => $size[0],
            'height_px' => $size[1],
            'original_filename' => $staged->original_filename,
            'uploaded_by_user_id' => $by->id,
        ]);

        $staged->delete();
    }

    /** D160: rejected and withdrawn submissions lose their bytes immediately; the metadata row stays for audit. */
    public function purgeFiles(MaterialSubmission $submission): void
    {
        foreach ($submission->files()->get() as $file) {
            Storage::disk($file->disk)->delete($file->path);
            $file->delete();
        }
    }
}

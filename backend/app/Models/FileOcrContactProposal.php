<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The reviewer-driven path from an authorization letter to an ArtistContact
 * change: artist confirmed → contact values confirmed → editorial draft
 * submitted to the archivist review queue → approved by a different user.
 * One row per document: which artist it belongs to and its latest draft.
 * Never holds contact values; each proposed value, with its provenance and
 * state, is an ArtistContactProposal. `provenance` is no longer written.
 */
class FileOcrContactProposal extends Model
{
    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'artist_confirmed_at' => 'datetime',
            'proposed_at' => 'datetime',
            'provenance' => 'array',
        ];
    }

    /**
     * @return BelongsTo<File, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    /**
     * @return BelongsTo<Artist, $this>
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    /**
     * @return BelongsTo<EditProposal, $this>
     */
    public function editProposal(): BelongsTo
    {
        return $this->belongsTo(EditProposal::class);
    }
}

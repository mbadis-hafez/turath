<?php

namespace App\Models;

use App\Concerns\LogsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * D149: an anonymous offer of material. Nothing here is public, and it is not
 * an Archive Item until a curator catalogs it (D155).
 */
class MaterialSubmission extends Model
{
    use LogsChanges;

    public $timestamps = false;

    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    /**
     * @return HasMany<MaterialSubmissionFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(MaterialSubmissionFile::class)->orderBy('id');
    }

    /**
     * @return HasMany<ArchiveItem, $this>
     */
    public function archiveItems(): HasMany
    {
        return $this->hasMany(ArchiveItem::class, 'source_material_submission_id');
    }

    /**
     * @return BelongsTo<Artist, $this>
     */
    public function linkedArtist(): BelongsTo
    {
        return $this->belongsTo(Artist::class, 'linked_artist_id');
    }

    /**
     * @return BelongsTo<Artwork, $this>
     */
    public function linkedArtwork(): BelongsTo
    {
        return $this->belongsTo(Artwork::class, 'linked_artwork_id');
    }

    /**
     * The submitter's own details never enter the audit diff, the same rule
     * artist contact data follows; status changes and staff actions still do.
     *
     * @return array<int, string>
     */
    public function excludedFromActivityLog(): array
    {
        return ['submitter_name', 'submitter_contact'];
    }

    public function activitySubjectLabel(): string
    {
        return 'Material submission #'.$this->getKey();
    }

    /**
     * @return array<string, array{ar: string, en: string}>
     */
    public static function activityFieldLabels(): array
    {
        return [
            'submitter_role' => ['ar' => 'صفة المقدِّم', 'en' => 'Submitter role'],
            'city' => ['ar' => 'المدينة', 'en' => 'City'],
            'description' => ['ar' => 'وصف المواد', 'en' => 'Description'],
            'status' => ['ar' => 'الحالة', 'en' => 'Status'],
            'linked_artist_id' => ['ar' => 'الفنان المرتبط', 'en' => 'Linked artist'],
            'linked_artwork_id' => ['ar' => 'العمل المرتبط', 'en' => 'Linked artwork'],
            'authorization_letter_status' => ['ar' => 'حالة خطاب التفويض', 'en' => 'Authorization letter status'],
            'staff_notes' => ['ar' => 'ملاحظات الفريق', 'en' => 'Staff notes'],
        ];
    }
}

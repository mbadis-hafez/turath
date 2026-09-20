<?php

namespace App\Models;

use App\Concerns\LogsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** An artist or artwork taking part in an event (D117). */
class EventParticipant extends Model
{
    use LogsChanges;

    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function participant(): MorphTo
    {
        return $this->morphTo();
    }

    public function activitySubjectLabel(): string
    {
        return "Event #{$this->event_id} participant ({$this->role})";
    }

    /**
     * @return array<string, array{ar: string, en: string}>
     */
    public static function activityFieldLabels(): array
    {
        return [
            'role' => ['ar' => 'الدور', 'en' => 'Role'],
            'note' => ['ar' => 'ملاحظة', 'en' => 'Note'],
        ];
    }
}

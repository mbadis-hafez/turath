<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Event\EventRequest;
use App\Http\Resources\ArchiveItemResource;
use App\Http\Resources\HolderResource;
use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\Event;
use App\Support\ArabicNormalizer;
use App\Support\Completeness\CompletenessCalculator;
use App\Support\Events\EventPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EventController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'event_type' => ['nullable', 'string', 'max:20'],
            'year_from' => ['nullable', 'integer'],
            'year_to' => ['nullable', 'integer'],
            'theme_id' => ['nullable', 'integer'],
            'holder_id' => ['nullable', 'integer'],
            'artist_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:all'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $manage = $request->user()?->can('events.manage') ?? false;

        $query = Event::query()->withCount('participants');

        if (! (($data['status'] ?? null) === 'all' && $manage)) {
            $query->where('publication_status', 'published');
        }
        if (! empty($data['event_type'])) {
            $query->where('event_type', $data['event_type']);
        }
        if (! empty($data['holder_id'])) {
            $query->where('holder_id', $data['holder_id']);
        }
        if (! empty($data['year_from'])) {
            $query->where(fn (Builder $q) => $q->whereNull('start_year_from')->orWhere(fn (Builder $w) => $w->whereRaw('COALESCE(end_year_to, start_year_to, start_year_from) >= ?', [$data['year_from']])));
        }
        if (! empty($data['year_to'])) {
            $query->where(fn (Builder $q) => $q->whereNull('start_year_from')->orWhere('start_year_from', '<=', $data['year_to']));
        }
        if (! empty($data['theme_id'])) {
            $query->whereHas('themes', fn (Builder $q) => $q->where('themes.id', $data['theme_id']));
        }
        if (! empty($data['artist_id'])) {
            $query->whereHas('participants', fn (Builder $q) => $q->where('participant_type', Artist::class)->where('participant_id', $data['artist_id']));
        }
        if (! empty($data['q'])) {
            foreach (array_filter(preg_split('/\s+/u', ArabicNormalizer::normalize(mb_substr($data['q'], 0, 100))) ?: []) as $token) {
                $query->where('search_text', 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $token).'%');
            }
        }

        $paginated = $query->orderByRaw('start_year_from IS NULL')->orderBy('start_year_from')->orderBy('id')->paginate((int) ($data['per_page'] ?? 24));

        return response()->json([
            'data' => $paginated->getCollection()->map(fn (Event $e) => [...EventPresenter::summary($e), 'participant_count' => $e->participants_count])->values(),
            'meta' => [
                'current_page' => $paginated->currentPage(), 'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(), 'total' => $paginated->total(),
            ],
        ]);
    }

    public function show(Request $request, int $event): JsonResponse
    {
        $manage = $request->user()?->can('events.manage') ?? false;
        $model = Event::withTrashed()->with(['holder', 'themes', 'participants.participant'])->findOrFail($event);

        abort_unless($manage || (! $model->trashed() && $model->publication_status === 'published'), 404);

        return response()->json(['data' => $this->bundle($model, $manage)]);
    }

    public function store(EventRequest $request): JsonResponse
    {
        $event = Event::create($request->mappedAttributes());

        return response()->json(['data' => $this->bundle($event->load(['holder', 'themes', 'participants.participant']), true)], 201);
    }

    public function update(EventRequest $request, Event $event): JsonResponse
    {
        $event->fill($request->mappedAttributes());
        $event->save();

        return response()->json(['data' => $this->bundle($event->load(['holder', 'themes', 'participants.participant']), true)]);
    }

    public function publish(Event $event): JsonResponse
    {
        $calculator = new CompletenessCalculator;
        $rules = $calculator->rulesFor(Event::class);
        $errors = [];

        foreach ($calculator->evaluate($event)['blocking'] as $key) {
            $errors["completeness.{$key}"] = ["Missing required field: {$rules->fieldLabel($key)['en']}."];
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $event->publication_status = 'published';
        $event->save();

        return response()->json(['data' => $this->bundle($event->load(['holder', 'themes', 'participants.participant']), true)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function bundle(Event $event, bool $manage): array
    {
        $items = ArchiveItem::query()->with(['links.linkable', 'files'])
            ->whereHas('links', fn (Builder $q) => $q->where('linkable_type', Event::class)->where('linkable_id', $event->id))
            ->where('publication_status', 'published')->orderBy('id')->get();

        $evaluation = (new CompletenessCalculator)->evaluate($event);

        return [
            ...EventPresenter::summary($event),
            'description' => ['ar' => $event->description_ar, 'en' => $event->description_en],
            'date_note' => $event->date_note,
            'access_level' => $event->access_level,
            'holder' => $event->holder ? new HolderResource($event->holder) : null,
            'themes' => $event->themes->map(fn ($t) => ['id' => $t->id, 'label' => ['ar' => $t->label_ar, 'en' => $t->label_en]])->values(),
            'participants' => $event->participants->map(fn ($p) => EventPresenter::participant($p, $manage))->filter()->values(),
            'archive_items' => ArchiveItemResource::collection($items)->resolve(),
            ...($manage ? [
                'completeness' => ['pct' => $evaluation['completeness_pct'], 'blocking' => $evaluation['blocking'], 'minor' => $evaluation['minor']],
            ] : []),
        ];
    }
}

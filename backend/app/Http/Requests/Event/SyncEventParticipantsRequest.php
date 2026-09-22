<?php

namespace App\Http\Requests\Event;

use App\Enums\EventParticipantRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncEventParticipantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'participants' => ['required', 'array', 'max:500'],
            'participants.*.id' => ['nullable', 'integer'],
            'participants.*.type' => ['required', Rule::in(['artist', 'artwork'])],
            'participants.*.participant_id' => ['required', 'integer'],
            'participants.*.role' => ['required', Rule::enum(EventParticipantRole::class)],
            'participants.*.note' => ['nullable', 'string', 'max:255'],
            'edit_summary' => ['nullable', 'string', 'max:255'],
        ];
    }
}

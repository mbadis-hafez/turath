<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Artist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ArtistCurationUpdateController
{
    private const CONTACT_FIELDS = ['contact_email', 'contact_phone'];

    public function __invoke(Request $request, Artist $artist): JsonResponse
    {
        $data = $request->validate([
            'identified_through_note' => ['sometimes', 'nullable', 'string', 'max:255'],
            'identified_through_date' => ['sometimes', 'nullable', 'date'],
            'name_as_in_sources' => ['sometimes', 'nullable', 'array'],
            'key_contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'owner_type' => ['sometimes', 'nullable', Rule::in(['artist', 'heir_or_estate', 'gallery', 'institution', 'other'])],
            'contact_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'authorization_letter_status' => ['sometimes', Rule::in(['not_started', 'pending', 'signed', 'not_applicable'])],
            'authorization_letter_file_id' => ['sometimes', 'nullable', 'integer', 'exists:files,id'],
            'owner_pre_agreement_status' => ['sometimes', Rule::in(['not_started', 'pending', 'yes', 'no', 'not_applicable'])],
            'bio_source_type' => ['sometimes', Rule::in(['citation', 'derived_from_linked_materials', 'unspecified'])],
            'ref_supervisor_note' => ['sometimes', 'nullable', 'string', 'max:255'],
            'edit_summary' => ['nullable', 'string', 'max:255'],
        ]);
        unset($data['edit_summary']);

        $artist->fill($data);
        $changedContact = array_values(array_filter(self::CONTACT_FIELDS, fn ($f) => $artist->isDirty($f)));
        $artist->save();

        // Encrypted values stay out of the diff; record which fields changed.
        if ($changedContact !== []) {
            activity($artist->getTable())->performedOn($artist)->causedBy($request->user())->event('updated')
                ->withProperties(['edit_summary' => $request->input('edit_summary'), 'contact_fields_changed' => $changedContact])
                ->log('contact fields changed');
        }

        return (new ArtistCurationShowController)($artist->refresh());
    }
}

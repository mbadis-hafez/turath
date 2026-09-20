<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Artist;
use App\Support\Curation\ChildSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ArtistCurationUpdateController
{
    public function __invoke(Request $request, Artist $artist): JsonResponse
    {
        $data = $request->validate([
            'identified_through_note' => ['sometimes', 'nullable', 'string', 'max:255'],
            'identified_through_date' => ['sometimes', 'nullable', 'date'],
            'name_as_in_sources' => ['sometimes', 'nullable', 'array'],
            'owner_type' => ['sometimes', 'nullable', Rule::in(['artist', 'heir_or_estate', 'gallery', 'institution', 'other'])],
            'contacts' => ['sometimes', 'array', 'max:30'],
            'contacts.*.id' => ['nullable', 'integer'],
            'contacts.*.name' => ['nullable', 'string', 'max:255'],
            'contacts.*.role_note' => ['nullable', 'string', 'max:255'],
            'contacts.*.email' => ['nullable', 'email', 'max:255'],
            'contacts.*.phone' => ['nullable', 'string', 'max:50'],
            'authorization_letter_status' => ['sometimes', Rule::in(['not_started', 'pending', 'signed', 'not_applicable'])],
            'authorization_letter_file_id' => ['sometimes', 'nullable', 'integer', 'exists:files,id'],
            'owner_pre_agreement_status' => ['sometimes', Rule::in(['not_started', 'pending', 'yes', 'no', 'not_applicable'])],
            'bio_source_type' => ['sometimes', Rule::in(['citation', 'derived_from_linked_materials', 'unspecified'])],
            'ref_supervisor_note' => ['sometimes', 'nullable', 'string', 'max:255'],
            'edit_summary' => ['nullable', 'string', 'max:255'],
        ]);
        unset($data['edit_summary']);

        $contacts = $data['contacts'] ?? null;
        unset($data['contacts']);

        $artist->fill($data);
        $artist->save();

        $contactCounts = null;
        if ($contacts !== null) {
            $items = array_map(fn (array $c) => [
                'id' => $c['id'] ?? null, 'name' => $c['name'] ?? null, 'role_note' => $c['role_note'] ?? null,
                'email' => $c['email'] ?? null, 'phone' => $c['phone'] ?? null,
            ], array_filter($contacts, fn (array $c) => ($c['name'] ?? null) !== null || ($c['email'] ?? null) !== null || ($c['phone'] ?? null) !== null));
            $contactCounts = ChildSync::sync($artist->contacts(), $items);
        }

        // Encrypted contact values never enter the audit diff; record what changed by count.
        if ($contactCounts !== null && array_sum($contactCounts) > 0) {
            activity($artist->getTable())->performedOn($artist)->causedBy($request->user())->event('updated')
                ->withProperties(['edit_summary' => $request->input('edit_summary'), 'contacts_changed' => $contactCounts])
                ->log('contacts changed');
        }

        return (new ArtistCurationShowController)($artist->refresh());
    }
}

<?php

namespace App\Http\Requests\Artist;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateArtistCurationRequest extends FormRequest
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
            'contacts.*.address' => ['nullable', 'string', 'max:500'],
            'authorization_letter_status' => ['sometimes', Rule::in(['not_started', 'pending', 'signed', 'not_applicable'])],
            'authorization_letter_file_id' => ['sometimes', 'nullable', 'integer', 'exists:files,id'],
            'owner_pre_agreement_status' => ['sometimes', Rule::in(['not_started', 'pending', 'yes', 'no', 'not_applicable'])],
            'bio_source_type' => ['sometimes', Rule::in(['citation', 'derived_from_linked_materials', 'unspecified'])],
            'ref_supervisor_note' => ['sometimes', 'nullable', 'string', 'max:255'],
            'edit_summary' => ['nullable', 'string', 'max:255'],
        ];
    }
}

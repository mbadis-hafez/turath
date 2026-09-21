<?php

namespace App\Http\Requests\MaterialSubmission;

use App\Enums\SubmitterRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** D150/D154: anonymous, so every check here is a real gate, not a courtesy. */
class StoreMaterialSubmissionRequest extends FormRequest
{
    /** JPG, TIFF, PDF, MP3 — matched against the file's actual content, never the client-declared type. */
    public const MIME_TYPES = ['image/jpeg', 'image/tiff', 'application/pdf', 'audio/mpeg'];

    /** 2 GB per submission (D154). */
    public const MAX_TOTAL_BYTES = 2 * 1024 * 1024 * 1024;

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
            'submitter_name' => ['required', 'string', 'max:255'],
            // Free text on purpose (D150): a strict format check would turn away genuine donors.
            'submitter_contact' => ['required', 'string', 'max:255'],
            'submitter_role' => ['required', Rule::enum(SubmitterRole::class)],
            'city' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:20000'],
            'attestation' => ['accepted'],
            'files' => ['nullable', 'array', 'max:100'],
            'files.*' => ['file', 'mimetypes:'.implode(',', self::MIME_TYPES)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $total = 0;
            foreach ((array) $this->file('files', []) as $file) {
                if ($file instanceof UploadedFile) {
                    $total += (int) $file->getSize();
                }
            }
            if ($total > self::MAX_TOTAL_BYTES) {
                $v->errors()->add('files', 'The files together are larger than the 2 GB limit.');
            }
        });
    }
}

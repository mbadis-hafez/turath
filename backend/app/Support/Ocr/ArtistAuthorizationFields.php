<?php

namespace App\Support\Ocr;

use App\Enums\ExtractionMethod;
use App\Models\FileOcrFormField;
use App\Support\ArabicNormalizer;

/**
 * Picks the artist-identity and contact form fields out of an authorization
 * letter's detected "label: value" pairs, by whole-word keyword match on the
 * normalized label. Keywords rather than exact labels, because the label text
 * is itself OCR output: on the benchmark letter "رقم الجوال:" came back as
 * "دك الجوال:" and "اسم الفنان/ة:" as "3 الفنان/ة:" — the distinctive word
 * survives even when the rest of the label doesn't.
 *
 * This only pre-fills the reviewer's form. A key with no matching field (the
 * benchmark's email label was never detected at all) is simply empty, and the
 * reviewer enters the value by hand.
 */
class ArtistAuthorizationFields
{
    /**
     * Checked in this order — "عنوان البريد الإلكتروني" is an email label, not an address.
     * Also the authorization letter's label keywords in DocumentFieldSchema.
     */
    public const LABEL_KEYWORDS = [
        'email' => ['البريد', 'الايميل', 'ايميل', 'email', 'e mail'],
        'phone' => ['الجوال', 'جوال', 'الهاتف', 'هاتف', 'الموبايل', 'phone', 'mobile', 'tel'],
        'artist_name' => ['الفنان', 'الفنانه', 'artist'],
        'address' => ['العنوان', 'address'],
    ];

    public const KEYS = ['artist_name', 'email', 'phone', 'address'];

    /**
     * @param  iterable<FileOcrFormField>  $formFields  in document order
     * @return array<string, array{form_field_id: int|null, field_label: string|null, value: string|null, method: string|null, needs_transcription: bool}>
     */
    public function extract(iterable $formFields): array
    {
        $matched = array_fill_keys(self::KEYS, null);

        foreach ($formFields as $field) {
            $key = $this->keyFor($field->field_label);
            if ($key !== null && $matched[$key] === null) {
                $matched[$key] = $field;
            }
        }

        return array_map(fn (?FileOcrFormField $field) => $this->present($field), $matched);
    }

    public function keyFor(string $label): ?string
    {
        $padded = ' '.ArabicNormalizer::normalize($label).' ';

        foreach (self::LABEL_KEYWORDS as $key => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($padded, ' '.$keyword.' ')) {
                    return $key;
                }
            }
        }

        return null;
    }

    /**
     * A reviewer's transcription always wins over machine text; machine text
     * is only offered when the pipeline judged the value region printed.
     *
     * @return array{form_field_id: int|null, field_label: string|null, value: string|null, method: string|null, needs_transcription: bool}
     */
    private function present(?FileOcrFormField $field): array
    {
        if ($field === null) {
            return ['form_field_id' => null, 'field_label' => null, 'value' => null, 'method' => null, 'needs_transcription' => false];
        }

        [$value, $method] = match (true) {
            $field->manual_value !== null => [$field->manual_value, ExtractionMethod::ManuallyTranscribed->value],
            $field->machine_value !== null && ! $field->requires_manual_transcription => [$field->machine_value, ExtractionMethod::OcrDerived->value],
            default => [null, null],
        };

        return [
            'form_field_id' => $field->id,
            'field_label' => $field->field_label,
            'value' => $value,
            'method' => $method,
            'needs_transcription' => $value === null,
        ];
    }
}

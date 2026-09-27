<?php

namespace App\Http\Requests\Artwork;

use App\Models\Artwork;
use App\Models\ArtworkImage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Accepts either the legacy single `image` field or the new `images[]` batch
 * field, never both. Per-file mime/size validity is checked separately, in
 * the controller, so one bad file never fails its valid siblings (FR-004) —
 * this request only enforces what must hold for the whole call: exactly one
 * of the two shapes, a valid rights_status, and the 20-image cap (FR-014).
 */
class StoreArtworkImagesRequest extends FormRequest
{
    public const MAX_IMAGES_PER_ARTWORK = 20;

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
            'image' => ['required_without:images', 'prohibits:images', 'file'],
            'images' => ['required_without:image', 'array', 'min:1'],
            'images.*' => ['file'],
            'rights_status' => ['nullable', Rule::in(ArtworkImage::RIGHTS)],
            'edit_summary' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $artwork = $this->route('artwork');
            $existing = $artwork instanceof Artwork ? $artwork->images()->count() : 0;
            $incoming = count($this->file('images') ?? array_filter([$this->file('image')]));

            if ($existing + $incoming > self::MAX_IMAGES_PER_ARTWORK) {
                $validator->errors()->add('images', "Attaching {$incoming} image(s) would exceed the ".self::MAX_IMAGES_PER_ARTWORK.'-image limit for this artwork ('.$existing.' already attached).');
            }
        });
    }

    /**
     * The submitted files, normalised to a list regardless of which of the
     * two accepted shapes (`images[]` or `image`) was sent.
     *
     * @return array<UploadedFile>
     */
    public function uploadedFiles(): array
    {
        return $this->file('images') ?? array_filter([$this->file('image')]);
    }
}

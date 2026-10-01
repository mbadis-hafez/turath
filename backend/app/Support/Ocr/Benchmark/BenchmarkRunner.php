<?php

namespace App\Support\Ocr\Benchmark;

use App\Enums\DocumentType;
use App\Models\ArchiveItem;
use App\Models\File;
use App\Models\FileEntityMatch;
use App\Models\FileExtractedDate;
use App\Models\FileExtractedField;
use App\Models\FileOcrRegionCorrection;
use App\Support\Ocr\Correction\ValueCorrection;
use App\Support\Ocr\Pipeline\OcrPipeline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File as Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Runs one benchmark document through the real pipeline — every stage, as
 * production runs it — and reads back what it produced. Nothing is kept: the
 * records it needs are created inside a transaction that is always rolled
 * back, its files go to a scratch disk that is deleted, and it refuses to run
 * in production at all.
 *
 * AI correction runs only when asked (it costs money and the document may
 * carry personal data), and then under the normal provider and privacy
 * policy, with the document treated as institution-only material.
 */
class BenchmarkRunner
{
    private const DISK = 'ocr-benchmark';

    public function __construct(private readonly OcrPipeline $pipeline) {}

    /**
     * @return array{document_type: ?string, fields: array<string, list<string>>, dates: list<array{value: string, calendar: string, date_type: string}>, pages: array<string, array<int, string>>, matches: list<array{entity_type: string, source_text: string, top_id: mixed, top_strength: mixed}>, corrections: list<array{field_key: string, ocr: string, ai: ?string, changed: bool, needs_review: bool}>, stages: array<string, ?string>}
     */
    public function run(BenchmarkDocument $document, bool $withCorrection = false): array
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The OCR benchmark never runs in production: it creates (and rolls back) records.');
        }

        $root = storage_path('app/ocr-benchmark-runs/'.Str::uuid());
        $restore = ['queue.default' => config('queue.default'), 'ocr.correction.enabled' => config('ocr.correction.enabled'), 'filesystems.disks.'.self::DISK => config('filesystems.disks.'.self::DISK)];
        config([
            'filesystems.disks.'.self::DISK => ['driver' => 'local', 'root' => $root],
            // Every stage runs here and now, inside the transaction.
            'queue.default' => 'sync',
            'ocr.correction.enabled' => $withCorrection && (bool) config('ocr.correction.enabled'),
        ]);
        Storage::forgetDisk(self::DISK);

        DB::beginTransaction();
        try {
            $extension = strtolower(pathinfo($document->file, PATHINFO_EXTENSION));
            $path = 'document.'.$extension;
            Storage::disk(self::DISK)->put($path, (string) file_get_contents($document->file));

            $item = ArchiveItem::create(['item_type' => 'document', 'title_en' => "OCR benchmark: {$document->id}", 'access_level' => 'institution_only']);
            $file = File::create([
                'archive_item_id' => $item->id,
                'role' => 'original',
                'disk' => self::DISK,
                'path' => $path,
                'mime_type' => $this->mimeType($document->file),
                'size_bytes' => (int) filesize($document->file),
                'sha256' => (string) hash_file('sha256', $document->file),
            ]);

            $this->pipeline->start($file);

            return $this->observe($file->refresh());
        } finally {
            DB::rollBack();
            config($restore);
            Storage::forgetDisk(self::DISK);
            Filesystem::deleteDirectory($root);
        }
    }

    /**
     * @return array{document_type: ?string, fields: array<string, list<string>>, dates: list<array{value: string, calendar: string, date_type: string}>, pages: array<string, array<int, string>>, matches: list<array{entity_type: string, source_text: string, top_id: mixed, top_strength: mixed}>, corrections: list<array{field_key: string, ocr: string, ai: ?string, changed: bool, needs_review: bool}>, stages: array<string, ?string>}
     */
    private function observe(File $file): array
    {
        $type = $file->document_type ?? DocumentType::Unknown;

        $fields = [];
        $corrections = [];
        $regionCorrections = FileOcrRegionCorrection::query()->with('ocrCorrection')->where('file_id', $file->id)->orderBy('id')->get()->keyBy('region_id');
        foreach ($file->extractedFields()->orderBy('ordinal')->get() as $field) {
            /** @var FileExtractedField $field */
            if ($field->extracted_value === null || ($field->document_type !== null && $field->document_type !== $type)) {
                continue;
            }
            $fields[$field->field_key][] = $field->extracted_value;

            $regionCorrection = $field->region_id === null ? null : $regionCorrections->get($field->region_id);
            if ($regionCorrection?->ocrCorrection !== null) {
                $narrowed = ValueCorrection::narrow($regionCorrection->ocrCorrection, $field->extracted_value);
                $corrections[] = [
                    'field_key' => $field->field_key,
                    'ocr' => $field->extracted_value,
                    'ai' => $regionCorrection->status === FileOcrRegionCorrection::STATUS_REJECTED ? null : ($narrowed['suggested_value'] ?? $field->extracted_value),
                    'changed' => $narrowed['suggested_value'] !== null,
                    'needs_review' => $regionCorrection->needs_review,
                ];
            }
        }

        $pages = [];
        foreach ($file->extractedTexts()->orderBy('page_number')->get() as $text) {
            $pages[$text->language][$text->page_number] = (string) $text->text;
        }

        return [
            'document_type' => $file->document_type?->value,
            'fields' => $fields,
            'dates' => $file->extractedDates()->get()->map(fn (FileExtractedDate $d) => [
                'value' => $d->value, 'calendar' => $d->calendar->value, 'date_type' => $d->date_type->value,
            ])->values()->all(),
            'pages' => $pages,
            'matches' => FileEntityMatch::query()->where('file_id', $file->id)->get()->map(fn (FileEntityMatch $m) => [
                'entity_type' => $m->entity_type,
                'source_text' => $m->source_text,
                'top_id' => $m->candidates[0]['id'] ?? $m->candidates[0]['key'] ?? null,
                'top_strength' => $m->candidates[0]['strength'] ?? null,
            ])->values()->all(),
            'corrections' => $corrections,
            'stages' => collect($this->pipeline->describe($file))->mapWithKeys(fn (array $s) => [$s['stage'] => $s['status']])->all(),
        ];
    }

    private function mimeType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'tif', 'tiff' => 'image/tiff',
            'webp' => 'image/webp',
            default => (string) mime_content_type($path),
        };
    }
}

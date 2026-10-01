<?php

use App\Enums\ExtractedFieldStatus;
use App\Enums\OcrStage;
use App\Jobs\MatchEntitiesJob;
use App\Models\ArchiveItem;
use App\Models\ArchiveItemLink;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\EditProposal;
use App\Models\Event;
use App\Models\File;
use App\Models\FileEntityMatch;
use App\Models\FileExtractedField;
use App\Models\Holder;
use App\Models\Source;
use App\Models\User;
use App\Support\Ocr\Matching\EntityMatcher;
use App\Support\Ocr\Matching\EntityMatchingService;
use App\Support\Ocr\Matching\MatchContext;
use App\Support\Ocr\Pipeline\OcrPipeline;
use App\ValueObjects\ParsedDimensions;
use Illuminate\Support\Facades\Queue;

function candidatesOf(string $type, string $text, array $context = []): array
{
    $arguments = ['archiveItem' => $context['item'] ?? ArchiveItem::factory()->create(), ...array_diff_key($context, ['item' => true])];

    return (new EntityMatcher)->match($type, $text, new MatchContext(...$arguments));
}

function matchEvent(array $attributes): Event
{
    return Event::create(['event_type' => 'exhibition', 'publication_status' => 'draft', ...$attributes]);
}

/** A biography already through recognition and extraction, with the given extracted fields (key => value, or key => [values]). */
function extractedBiography(array $fields, array $fileAttributes = []): File
{
    $item = ArchiveItem::factory()->create();
    $file = File::factory()->create(['archive_item_id' => $item->id, 'mime_type' => 'image/png', 'ocr_status' => 'completed', 'document_type' => 'artist_biography', ...$fileAttributes]);
    foreach ($fields as $key => $values) {
        foreach ((array) $values as $ordinal => $value) {
            FileExtractedField::create([
                'file_id' => $file->id, 'field_key' => $key, 'document_type' => $file->document_type->value, 'ordinal' => $ordinal,
                'extracted_value' => $value, 'confidence' => 70, 'status' => ExtractedFieldStatus::Pending->value, 'extraction_method' => 'ocr_derived',
            ]);
        }
    }

    return $file;
}

function runMatching(File $file): void
{
    (new MatchEntitiesJob($file->id))->handle(app(EntityMatchingService::class));
}

function matchFor(File $file, string $key, int $ordinal = 0): FileEntityMatch
{
    return FileEntityMatch::query()->where('extracted_field_id', $file->extractedFields()->where('field_key', $key)->where('ordinal', $ordinal)->sole()->id)->sole();
}

function matchUrl(File $file, FileEntityMatch $match, string $action): string
{
    return "/api/v1/archive-items/{$file->archive_item_id}/file/ocr/matches/{$match->id}/{$action}";
}

describe('candidates', function () {
    it('finds artists by name and recorded variant, and never treats a shared name as proof', function () {
        $first = Artist::factory()->create(['name_ar' => 'محمد عبدالله السليم', 'name_en' => 'Mohammed Alsaleem']);
        $namesake = Artist::factory()->create(['name_ar' => 'محمد عبدالله السليم', 'name_en' => null]);
        $variant = Artist::factory()->create(['name_ar' => 'صفية بن زقر']);
        $variant->variants()->create(['name' => 'صفيه بنت زقر', 'type' => 'typo', 'language' => 'ar']);

        $same = candidatesOf('artist', 'محمد عبداللـه السليم');
        expect(array_column($same, 'id'))->toEqualCanonicalizing([$first->id, $namesake->id])
            ->and($same[0])->toMatchArray(['score' => 1.0, 'strength' => 'high'])
            ->and($same[0]['basis'])->toContain('exact_name');

        expect(candidatesOf('artist', 'صفيه بنت زقر')[0])->toMatchArray(['id' => $variant->id, 'basis' => ['exact_variant']]);

        $partial = candidatesOf('artist', 'محمد السليم');
        expect($partial[0]['score'])->toBeLessThan(1.0)->and($partial[0]['basis'])->toBe(['shared_name_parts']);
    });

    it('does not offer an artist linked to the item whose name shares nothing with the text', function () {
        $item = ArchiveItem::factory()->create();
        $linked = Artist::factory()->create(['name_ar' => 'عبدالحليم رضوي']);
        ArchiveItemLink::create(['archive_item_id' => $item->id, 'linkable_type' => Artist::class, 'linkable_id' => $linked->id, 'role' => 'about']);

        expect(candidatesOf('artist', 'فنان مجهول تماما', ['item' => $item]))->toBe([]);
    });

    it("matches an artwork by its reference, or by title ranked like the importer's dedupe — never by context alone", function () {
        $artist = Artist::factory()->create();
        $work = Artwork::factory()->create(['artist_id' => $artist->id, 'title_ar' => 'صورة زيتية مع سمك', 'holder_inventory_no' => 'INV-204', 'height_cm' => 50, 'width_cm' => 70]);
        Artwork::factory()->create(['artist_id' => $artist->id, 'title_ar' => 'منظر طبيعي', 'height_cm' => 50, 'width_cm' => 70]);

        expect(candidatesOf('artwork', 'INV-204')[0])->toMatchArray(['id' => $work->id, 'strength' => 'high', 'basis' => ['exact_reference']]);

        $byTitle = candidatesOf('artwork', 'صورة زيتية مع سمك', ['artistIds' => [$artist->id], 'dimensions' => new ParsedDimensions(heightCm: 50.2, widthCm: 70.0)]);
        expect($byTitle)->toHaveCount(1)
            ->and($byTitle[0])->toMatchArray(['id' => $work->id, 'strength' => 'high'])
            ->and($byTitle[0]['basis'])->toBe(['exact_title', 'same_artist', 'same_height', 'same_width']);

        expect(candidatesOf('artwork', 'صورة زيتية')[0]['strength'])->toBe('low');
    });

    it('matches an exhibition by title, ranked by the year and city the text gives', function () {
        $riyadh = matchEvent(['title_ar' => 'معرض الرياض الأول', 'city' => 'الرياض', 'start_year_from' => 1972]);
        matchEvent(['title_ar' => 'معرض الرياض الثاني', 'city' => 'الرياض', 'start_year_from' => 1974]);

        $found = candidatesOf('event', 'معرض الرياض الأول 1972');

        expect($found[0])->toMatchArray(['id' => $riyadh->id, 'strength' => 'high'])
            ->and($found[0]['basis'])->toBe(['exact_title', 'same_year', 'same_city']);
    });

    it('matches institutions only by their exact name or their whole name in the text (D34), showing private ones by display name', function () {
        $museum = Holder::factory()->create(['name_ar' => 'متحف الملك فهد الوطني', 'name_en' => null]);
        $private = Holder::factory()->create(['name_ar' => 'مجموعة آل خالد', 'type' => 'private_collector', 'is_public_name' => false, 'city_en' => 'Jeddah']);

        expect(candidatesOf('holder', 'متحف الملك فهد الوطني')[0])->toMatchArray(['id' => $museum->id, 'strength' => 'high'])
            ->and(candidatesOf('holder', 'مقتنيات متحف الملك فهد الوطني بالرياض')[0])->toMatchArray(['id' => $museum->id, 'strength' => 'medium', 'basis' => ['name_within_text']])
            // A resemblance is not enough for an institution.
            ->and(candidatesOf('holder', 'متحف الملك فهد'))->toBe([]);

        $file = extractedBiography(['collections' => ['مجموعة آل خالد']]);
        runMatching($file);
        $label = $this->actingAs(editorUser())->getJson("/api/v1/archive-items/{$file->archive_item_id}/file/ocr")->json('data.fields.0.match.candidates.0.label.en');
        expect($label)->not->toContain('خالد')->and($private->exists)->toBeTrue();
    });

    it('matches sources by title and year, and places by the spellings already used in records', function () {
        $source = Source::factory()->create(['title_ar' => 'الفن التشكيلي السعودي', 'year' => 1985]);
        Artist::factory()->create(['birth_place_ar' => 'الرياض']);

        expect(candidatesOf('source', 'كتاب الفن التشكيلي السعودي 1985')[0])->toMatchArray(['id' => $source->id])
            ->and(candidatesOf('source', 'كتاب الفن التشكيلي السعودي 1985')[0]['basis'])->toContain('same_year')
            ->and(candidatesOf('place', 'الرياض')[0])->toMatchArray(['id' => null, 'key' => 'الرياض', 'strength' => 'high'])
            ->and(candidatesOf('place', 'مدينة الرياض')[0])->toMatchArray(['key' => 'الرياض', 'strength' => 'medium']);
    });
});

describe('the match stage', function () {
    it('stores candidates for every extracted name, pending review, and leaves contact identities to the contact panel', function () {
        $artist = Artist::factory()->create(['name_ar' => 'محمد عبدالله السليم']);
        $file = extractedBiography(['artist' => 'محمد عبدالله السليم', 'exhibitions' => ['معرض لا يعرفه أحد']]);
        $letter = extractedBiography(['artist_name' => 'محمد عبدالله السليم'], ['document_type' => 'artist_authorization']);

        runMatching($file);
        runMatching($letter);

        $match = matchFor($file, 'artist');
        expect($match->status)->toBe('pending')
            ->and($match->candidates[0]['id'])->toBe($artist->id)
            ->and(matchFor($file, 'exhibitions')->candidates)->toBe([])
            ->and(FileEntityMatch::query()->where('file_id', $letter->id)->exists())->toBeFalse()
            ->and($file->ocrStageRuns()->where('stage', 'match')->sole()->summary)->toMatchArray(['mentions' => 2, 'with_candidates' => 1, 'strong' => 1]);
    });

    it('keeps decided matches, refreshes pending ones, and drops those whose reading was rejected', function () {
        $file = extractedBiography(['artist' => 'محمد عبدالله السليم', 'collections' => ['متحف الملك فهد الوطني', 'مجموعة لا وجود لها']]);
        runMatching($file);
        matchFor($file, 'collections')->update(['status' => 'no_match']);
        $file->extractedFields()->where('field_key', 'collections')->where('ordinal', 1)->update(['status' => ExtractedFieldStatus::Rejected->value]);

        $artist = Artist::factory()->create(['name_ar' => 'محمد عبدالله السليم']);
        runMatching($file);

        expect(matchFor($file, 'artist')->candidates[0]['id'])->toBe($artist->id)
            ->and(matchFor($file, 'collections')->status)->toBe('no_match')
            ->and(FileEntityMatch::query()->where('file_id', $file->id)->count())->toBe(2);
    });

    it('goes stale when the records change, so a new artist is found on the next run', function () {
        $file = extractedBiography(['artist' => 'محمد عبدالله السليم']);
        app(OcrPipeline::class)->start($file, OcrStage::MatchEntities);
        runQueuedOcrStages();
        expect(app(OcrPipeline::class)->plan($file, OcrStage::MatchEntities)['match'])->toBe('up_to_date');

        Artist::factory()->create(['name_ar' => 'محمد عبدالله السليم']);

        expect(app(OcrPipeline::class)->plan($file, OcrStage::MatchEntities)['match'])->toBe('will_run');
    });
});

describe('reviewing matches', function () {
    it('confirms a candidate, verifying the reading and changing no record', function () {
        $artist = Artist::factory()->create(['name_ar' => 'محمد عبدالله السليم', 'birth_place_ar' => null]);
        $file = extractedBiography(['artist' => 'محمد عبدالله السليم', 'birth_place' => 'الرياض']);
        runMatching($file);
        $match = matchFor($file, 'artist');

        $this->actingAs(editorUser())->postJson(matchUrl($file, $match, 'confirm'), ['entity_id' => $artist->id])->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.requires_review', false)
            ->assertJsonPath('data.confirmed.id', (string) $artist->id)
            ->assertJsonPath('data.confirmed.label.ar', 'محمد عبدالله السليم');

        $field = $file->extractedFields()->where('field_key', 'artist')->sole();
        expect($field->status)->toBe(ExtractedFieldStatus::Accepted)
            ->and($field->verified_value)->toBe('محمد عبدالله السليم')
            ->and($artist->fresh()->birth_place_ar)->toBeNull()
            ->and(EditProposal::query()->exists())->toBeFalse();
        // The rest of the document's matches are refreshed with the confirmed artist.
        Queue::assertPushed(MatchEntitiesJob::class);
    });

    it('confirms any existing record, refuses one that does not exist, and a place only by a suggested spelling', function () {
        $other = Artist::factory()->create(['name_ar' => 'اسم مختلف تماما']);
        Artist::factory()->create(['birth_place_ar' => 'الرياض']);
        $file = extractedBiography(['artist' => 'محمد عبدالله السليم', 'birth_place' => 'الرياض']);
        runMatching($file);
        $editor = editorUser();

        $this->actingAs($editor)->postJson(matchUrl($file, matchFor($file, 'artist'), 'confirm'), ['entity_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('entity_id');
        $this->actingAs($editor)->postJson(matchUrl($file, matchFor($file, 'artist'), 'confirm'), ['entity_id' => $other->id])->assertOk();

        $this->actingAs($editor)->postJson(matchUrl($file, matchFor($file, 'birth_place'), 'confirm'), ['key' => 'جدة'])->assertUnprocessable()->assertJsonValidationErrors('key');
        $this->actingAs($editor)->postJson(matchUrl($file, matchFor($file, 'birth_place'), 'confirm'), ['key' => 'الرياض'])->assertOk()->assertJsonPath('data.confirmed.key', 'الرياض');
    });

    it('records "none of these", undoes a decision, and only for the item the match belongs to', function () {
        Artist::factory()->create(['name_ar' => 'محمد عبدالله السليم']);
        $file = extractedBiography(['artist' => 'محمد عبدالله السليم']);
        $other = extractedBiography([]);
        runMatching($file);
        $match = matchFor($file, 'artist');
        $editor = editorUser();

        $this->actingAs($editor)->postJson(matchUrl($file, $match, 'no-match'))->assertOk()->assertJsonPath('data.status', 'no_match');
        $this->actingAs($editor)->postJson(matchUrl($file, $match, 'reset'))->assertOk()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.requires_review', true);
        $this->actingAs($editor)->postJson(matchUrl($other, $match, 'no-match'))->assertNotFound();
        $this->actingAs(User::factory()->create())->postJson(matchUrl($file, $match, 'no-match'))->assertForbidden();
    });

    it('shows each field\'s candidates in the review bundle, labelled from the records', function () {
        $artist = Artist::factory()->create(['name_ar' => 'محمد عبدالله السليم', 'name_en' => null, 'birth_year_from' => 1945]);
        $file = extractedBiography(['artist' => 'محمد عبدالله السليم']);
        runMatching($file);

        $match = $this->actingAs(editorUser())->getJson("/api/v1/archive-items/{$file->archive_item_id}/file/ocr")->assertOk()->json('data.fields.0.match');

        expect($match)->toMatchArray(['entity_type' => 'artist', 'status' => 'pending', 'requires_review' => true])
            ->and($match['candidates'][0])->toMatchArray(['id' => $artist->id, 'score' => 1, 'strength' => 'high', 'missing' => false, 'label' => ['ar' => 'محمد عبدالله السليم', 'en' => 'محمد عبدالله السليم']])
            ->and($match['candidates'][0]['detail'])->toStartWith('1945');
    });
});

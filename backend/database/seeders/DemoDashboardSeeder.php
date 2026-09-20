<?php

namespace Database\Seeders;

use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\FieldCitation;
use App\Models\Holder;
use App\Models\ReviewQueueItem;
use App\Models\Source;
use App\Models\User;
use App\Support\Completeness\CompletenessCalculator;
use App\Support\Completeness\ConflictDetector;
use App\Support\Completeness\DashboardStatsCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * Local-only demo data so the contributor dashboard shows every severity
 * (blocking / conflict / minor / pending review / clear) for the editor.
 * Titles mirror the design mockup; the facts are fictional placeholders.
 */
class DemoDashboardSeeder extends Seeder
{
    private User $owner;

    public function run(): void
    {
        $owner = User::where('email', 'editor@bidayaat.test')->first();

        if ($owner === null) {
            return;
        }

        $this->owner = $owner;
        auth()->login($owner);

        $sabban = Artist::firstOrCreate(['legacy_code' => 'AR201'], [
            'name_ar' => 'طه الصبان', 'name_en' => 'Taha Al-Sabban', 'living_status' => 'deceased',
            'death_year_from' => 1990, 'death_year_to' => 1990, 'publication_status' => 'draft',
        ]);
        $this->own($sabban);

        $radwi = Artist::where('legacy_code', 'AR013')->first();
        if ($radwi !== null) {
            $radwi->update(['created_by_user_id' => $owner->id, 'living_status' => 'deceased', 'death_year_from' => 2016, 'death_year_to' => 2016, 'bio_en' => 'Painter.', 'birth_place_ar' => 'مكة', 'birth_place_en' => 'Makkah']);
            $this->cite($radwi, 'death_year', ['year' => 2016]);
            $this->cite($radwi, 'birth_year', ['year' => 1939], 'كتالوج بينالي القاهرة ١٩٨٤');
            $this->cite($radwi, 'birth_year', ['year' => 1941], 'أرشيف الرياض');
            $this->finish($radwi, 'birth_year');
        }

        $safeya = Artist::where('name_en', 'Safeya Binzagr')->first();
        if ($safeya !== null) {
            $safeya->update(['created_by_user_id' => $owner->id, 'living_status' => 'living', 'birth_place_ar' => null, 'birth_place_en' => null, 'bio_en' => null]);
            $this->cite($safeya, 'name', ['name' => 'Safeya Binzagr']);
            $this->finish($safeya);
        }

        $holder = Holder::where('name_en', 'MoC Collection')->first();

        $untitled = Artwork::firstOrCreate(['legacy_ref' => 'DEMO_ARW_001'], [
            'created_by_user_id' => $owner->id, 'is_untitled' => false, 'title_ar' => 'بلا عنوان، من سلسلة الصحراء', 'title_en' => 'Untitled, Desert series',
            'category' => 'painting', 'attribution_certainty' => 'unattributed', 'holder_id' => null,
        ]);
        $this->finish($untitled);

        $palms = Artwork::firstOrCreate(['legacy_ref' => 'DEMO_ARW_002'], [
            'created_by_user_id' => $owner->id, 'title_ar' => 'النخيل عند المغيب', 'title_en' => 'Palms at Sunset', 'category' => 'painting',
            'attribution_certainty' => $radwi ? 'confirmed' : 'unattributed', 'artist_id' => $radwi?->id, 'holder_id' => $holder?->id,
            'medium_ar' => 'زيت على قماش', 'medium_en' => 'Oil on canvas', 'height_cm' => 60, 'width_cm' => 90,
        ]);
        $this->queue($palms, 'archivist_review', 'بانتظار مراجعة أمين الأرشيف', 3);
        $this->finish($palms);

        $opening = ArchiveItem::firstOrCreate(['legacy_ref' => 'DEMO_ARC_001'], [
            'created_by_user_id' => $owner->id, 'item_type' => 'image', 'title_ar' => 'افتتاح معرض الفنانات السعوديات، دار الفنون، جدة',
            'title_en' => "Opening of the Saudi women artists' exhibition, Dar Al-Funun, Jeddah",
        ]);
        $this->finish($opening);

        $season = ArchiveItem::firstOrCreate(['legacy_ref' => 'DEMO_ARC_002'], [
            'created_by_user_id' => $owner->id, 'item_type' => 'article', 'title_ar' => 'الفن التشكيلي السعودي يفتتح موسمه في صالة الرياض',
            'title_en' => 'Saudi art opens its season in a Riyadh hall', 'rights_holder_en' => 'Al Riyadh Newspaper', 'rights_status' => 'licensed',
            'digitized_at' => '2024-12-22',
        ]);
        $this->cite($season, 'rights_holder', ['name' => 'Al Riyadh Newspaper']);
        $this->cite($season, 'rights_status', ['status' => 'licensed']);
        $this->cite($season, 'publication_date', ['date' => '1979-03-14'], 'جريدة الرياض');
        $this->cite($season, 'publication_date', ['date' => '1979-03-17'], 'أرشيف عائلي');
        $this->finish($season, 'publication_date');

        $this->queue($radwi ?? $sabban, 'data_audit', 'كتالوج بينالي القاهرة ١٩٨٤ · ٣٦ صفحة مرقمنة', 6);
        $this->queue($sabban, 'second_source_needed', 'جائزة الأمير فيصل بن فهد ١٩٨٨ — قائمة الفائزين', 11);

        (new DashboardStatsCalculator)->recompute($owner->id);
        auth()->logout();
    }

    private function own(Model $record): void
    {
        $record->forceFill(['created_by_user_id' => $this->owner->id])->save();
        $this->finish($record);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function cite(Model $record, string $field, array $value, ?string $sourceTitle = null): void
    {
        $source = Source::firstOrCreate(
            ['title_ar' => $sourceTitle ?? 'مصدر منشور', 'added_by_user_id' => $this->owner->id],
            ['source_type' => 'newspaper_article', 'title_en' => 'Published source'],
        );

        $exists = FieldCitation::where('citable_type', $record::class)->where('citable_id', $record->getKey())
            ->where('field_key', $field)->where('source_id', $source->id)->exists();

        if (! $exists) {
            FieldCitation::create([
                'citable_type' => $record::class, 'citable_id' => $record->getKey(), 'field_key' => $field,
                'source_id' => $source->id, 'claimed_value' => $value, 'created_by_user_id' => $this->owner->id,
            ]);
        }
    }

    private function queue(Model $record, string $type, string $note, int $daysAgo = 3): void
    {
        ReviewQueueItem::firstOrCreate(
            ['citable_type' => $record::class, 'citable_id' => $record->getKey(), 'review_type' => $type],
            ['note' => $note, 'submitted_by_user_id' => $this->owner->id, 'submitted_at' => now()->subDays($daysAgo)],
        );
    }

    private function finish(Model $record, ?string $conflictField = null): void
    {
        if ($conflictField !== null) {
            (new ConflictDetector)->check($record::class, $record->getKey(), $conflictField);
        }

        (new CompletenessCalculator)->recompute($record);
    }
}

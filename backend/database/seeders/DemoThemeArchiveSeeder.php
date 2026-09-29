<?php

namespace Database\Seeders;

use App\Models\ArchiveItem;
use App\Models\ArchiveItemLink;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\File;
use App\Models\Theme;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Demo dataset for local development: ~15 Saudi-culture themes and ~35
 * archive items (articles, photos, catalogues, posters, invitations,
 * certificates, documents, audio/video metadata) linked to the demo
 * artists/artworks seeded by DemoArtworkSeeder. Visual item types get a
 * generated placeholder file on the local disk. Idempotent via
 * legacy_ref keys, local-only. Run with:
 *
 *     php artisan db:seed --class=DemoThemeArchiveSeeder
 */
class DemoThemeArchiveSeeder extends Seeder
{
    /**
     * Saudi-culture themes, keyed by a stable slug used by the items below.
     *
     * @var array<string, array{en: string, ar: string}>
     */
    private const THEMES = [
        'arabic_calligraphy' => ['en' => 'Arabic Calligraphy', 'ar' => 'الخط العربي'],
        'desert_bedouin' => ['en' => 'Desert & Bedouin Life', 'ar' => 'البادية والحياة البدوية'],
        'coastal_seafaring' => ['en' => 'Sea & Seafaring', 'ar' => 'البحر والملاحة'],
        'palm_oases' => ['en' => 'Palms & Oases', 'ar' => 'النخيل والواحات'],
        'urban_heritage' => ['en' => 'Architectural Heritage', 'ar' => 'التراث العمراني'],
        'old_jeddah' => ['en' => 'Historic Jeddah (Al-Balad)', 'ar' => 'جدة التاريخية (البلد)'],
        'traditional_crafts' => ['en' => 'Traditional Crafts', 'ar' => 'الحرف التقليدية'],
        'souqs' => ['en' => 'Traditional Souqs', 'ar' => 'الأسواق الشعبية'],
        'folklore' => ['en' => 'Folklore & Ardah', 'ar' => 'الفلكلور والعرضة'],
        'modern_art' => ['en' => 'Saudi Modern Art', 'ar' => 'الفن التشكيلي السعودي الحديث'],
        'women_in_art' => ['en' => 'Women in Saudi Art', 'ar' => 'المرأة في الفن السعودي'],
        'photography' => ['en' => 'Documentary Photography', 'ar' => 'التصوير التوثيقي'],
        'islamic_art' => ['en' => 'Islamic Art', 'ar' => 'الفن الإسلامي'],
        'exhibitions' => ['en' => 'Exhibitions & Biennials', 'ar' => 'المعارض والبيينالات'],
        'holy_cities' => ['en' => 'Makkah & Madinah', 'ar' => 'مكة والمدينة المنورة'],
    ];

    /**
     * Item types that get a generated image file.
     *
     * @var array<int, string>
     */
    private const VISUAL_TYPES = ['image', 'poster', 'invitation', 'catalogue', 'certificate', 'document', 'article'];

    /**
     * @var array<int, array{ref: string, type: string, en: string, ar: string, desc_en: string, desc_ar: string, year: int, place_en: string, place_ar: string, themes: array<int, string>, pub: int, creator: string|null, people: array<int, string>|null}>
     */
    private const ITEMS = [
        // Articles
        ['ref' => 'DEMO-ARC-001', 'type' => 'article', 'en' => 'The rise of the Saudi art movement in the 1970s', 'ar' => 'صعود الحركة الفنية السعودية في السبعينيات', 'desc_en' => 'Press feature on the first generation of Saudi painters and their group exhibitions in Riyadh and Jeddah.', 'desc_ar' => 'تغطية صحفية عن الجيل الأول من الرسامين السعوديين ومعارضهم الجماعية في الرياض وجدة.', 'year' => 1982, 'place_en' => 'Riyadh', 'place_ar' => 'الرياض', 'themes' => ['modern_art', 'exhibitions'], 'pub' => 0, 'creator' => null, 'people' => null],
        ['ref' => 'DEMO-ARC-002', 'type' => 'article', 'en' => "Jeddah's open-air museum: sculptures on the corniche", 'ar' => 'متحف جدة المفتوح: منحوتات الكورنيش', 'desc_en' => 'Overview of the public sculpture program that turned the Jeddah corniche into an open-air museum.', 'desc_ar' => 'عرض لبرنامج النحت العام الذي حوّل كورنيش جدة إلى متحف مفتوح.', 'year' => 1979, 'place_en' => 'Jeddah', 'place_ar' => 'جدة', 'themes' => ['urban_heritage', 'modern_art'], 'pub' => 1, 'creator' => null, 'people' => null],
        ['ref' => 'DEMO-ARC-003', 'type' => 'article', 'en' => 'Preserving Hijazi architecture in Al-Balad', 'ar' => 'الحفاظ على العمارة الحجازية في البلد', 'desc_en' => 'Report on the restoration of coral-stone houses and wooden rosan windows in historic Jeddah.', 'desc_ar' => 'تقرير عن ترميم منازل الطوب البحري والرواشين الخشبية في جدة التاريخية.', 'year' => 1995, 'place_en' => 'Jeddah', 'place_ar' => 'جدة', 'themes' => ['old_jeddah', 'urban_heritage'], 'pub' => 1, 'creator' => null, 'people' => null],
        ['ref' => 'DEMO-ARC-004', 'type' => 'article', 'en' => 'The craft of Sadu weaving', 'ar' => 'حرفة النسيج السدوي', 'desc_en' => 'Study of Sadu weaving patterns, dyes and their symbolic meanings across Bedouin communities.', 'desc_ar' => 'دراسة عن نقوش السدو وأصباغه ودلالاته الرمزية في المجتمعات البدوية.', 'year' => 1988, 'place_en' => 'Qassim', 'place_ar' => 'القصيم', 'themes' => ['traditional_crafts', 'desert_bedouin'], 'pub' => 2, 'creator' => null, 'people' => null],
        ['ref' => 'DEMO-ARC-005', 'type' => 'article', 'en' => 'Women pioneers of Saudi painting', 'ar' => 'رائدات الرسم السعودي', 'desc_en' => 'Profiles of the first Saudi women to hold solo painting exhibitions.', 'desc_ar' => 'بروفايلات لأولى السعوديات اللواتي أقمن معارض فردية للرسم.', 'year' => 1994, 'place_en' => 'Jeddah', 'place_ar' => 'جدة', 'themes' => ['women_in_art', 'modern_art'], 'pub' => 0, 'creator' => null, 'people' => null],
        ['ref' => 'DEMO-ARC-006', 'type' => 'article', 'en' => 'Dhow building in the Eastern Province', 'ar' => 'بناء السفن الشراعية في المنطقة الشرقية', 'desc_en' => 'Documentation of the remaining dhow shipyards and the craft of wooden boat building.', 'desc_ar' => 'توثيق لورش بناء السفن الباقية وحرفة صناعة القوارب الخشبية.', 'year' => 1976, 'place_en' => 'Al-Ahsa', 'place_ar' => 'الأحساء', 'themes' => ['coastal_seafaring', 'traditional_crafts'], 'pub' => 1, 'creator' => null, 'people' => null],
        ['ref' => 'DEMO-ARC-007', 'type' => 'article', 'en' => 'Arabic calligraphy between tradition and modernity', 'ar' => 'الخط العربي بين الأصالة والمعاصرة', 'desc_en' => 'Critical essay on contemporary Saudi calligraphers and the Kufic revival.', 'desc_ar' => 'مقال نقدي عن الخطاطين المعاصرين والنهضة الخطية الكوفية.', 'year' => 1990, 'place_en' => 'Riyadh', 'place_ar' => 'الرياض', 'themes' => ['arabic_calligraphy', 'islamic_art'], 'pub' => 0, 'creator' => null, 'people' => null],
        ['ref' => 'DEMO-ARC-008', 'type' => 'article', 'en' => 'The first art exhibitions in Riyadh', 'ar' => 'أولى المعارض الفنية في الرياض', 'desc_en' => 'Retrospective on the earliest group exhibitions held in Riyadh in the late 1960s.', 'desc_ar' => 'استعادة لأولى المعارض الجماعية التي أقيمت في الرياض أواخر الستينيات.', 'year' => 1971, 'place_en' => 'Riyadh', 'place_ar' => 'الرياض', 'themes' => ['modern_art', 'exhibitions'], 'pub' => 2, 'creator' => null, 'people' => null],

        // Images
        ['ref' => 'DEMO-ARC-009', 'type' => 'image', 'en' => 'Artists at the Jeddah art week, 1974', 'ar' => 'فنانون في أسبوع جدة الفني ١٩٧٤', 'desc_en' => 'Group photograph of participating artists in front of the exhibition hall.', 'desc_ar' => 'صورة جماعية للفنانين المشاركين أمام قاعة المعرض.', 'year' => 1974, 'place_en' => 'Jeddah', 'place_ar' => 'جدة', 'themes' => ['modern_art', 'exhibitions', 'photography'], 'pub' => 3, 'creator' => 'Saudi Press Agency', 'people' => null],
        ['ref' => 'DEMO-ARC-010', 'type' => 'image', 'en' => 'Sadu weavers at work, Qassim', 'ar' => 'نسّاجات السدو في القصيم', 'desc_en' => 'Weavers working on horizontal looms in a Qassim craft house.', 'desc_ar' => 'نسّاجات يعملن على الأنوال الأفقية في دار حرفية بالقصيم.', 'year' => 1985, 'place_en' => 'Qassim', 'place_ar' => 'القصيم', 'themes' => ['traditional_crafts', 'desert_bedouin'], 'pub' => 3, 'creator' => null, 'people' => null],
        ['ref' => 'DEMO-ARC-011', 'type' => 'image', 'en' => 'The old souq of Riyadh, 1962', 'ar' => 'سوق الرياض القديم ١٩٦٢', 'desc_en' => 'Street view of the old covered souq before modern redevelopment.', 'desc_ar' => 'مشهد لسوق الرياض القديم المسقوف قبل التطوير الحديث.', 'year' => 1962, 'place_en' => 'Riyadh', 'place_ar' => 'الرياض', 'themes' => ['souqs', 'urban_heritage', 'photography'], 'pub' => 3, 'creator' => null, 'people' => null],
        ['ref' => 'DEMO-ARC-012', 'type' => 'image', 'en' => 'Al-Balad wooden rosan windows', 'ar' => 'الرواشين الخشبية في البلد', 'desc_en' => 'Detail study of carved wooden rosan balconies in historic Jeddah.', 'desc_ar' => 'دراسة تفصيلية للرواشين الخشبية المنقوشة في جدة التاريخية.', 'year' => 1991, 'place_en' => 'Jeddah', 'place_ar' => 'جدة', 'themes' => ['old_jeddah', 'urban_heritage'], 'pub' => 3, 'creator' => null, 'people' => null],
        ['ref' => 'DEMO-ARC-013', 'type' => 'image', 'en' => 'Ardah dancers performing at Jenadriyah', 'ar' => 'رقصة العرضة في مهرجان الجنادرية', 'desc_en' => 'Performance of the Ardah folk dance at the national heritage festival.', 'desc_ar' => 'عرضة أداء رقصة العرضة الشعبية في المهرجان الوطني للتراث.', 'year' => 1987, 'place_en' => 'Riyadh', 'place_ar' => 'الرياض', 'themes' => ['folklore'], 'pub' => 3, 'creator' => null, 'people' => null],
        ['ref' => 'DEMO-ARC-014', 'type' => 'image', 'en' => 'Date palm harvest in Al-Ahsa', 'ar' => 'حصاد التمور في الأحساء', 'desc_en' => 'Harvest season in the Al-Ahsa oasis palm groves.', 'desc_ar' => 'موسم الحصاد في بساتين نخيل واحة الأحساء.', 'year' => 1978, 'place_en' => 'Al-Ahsa', 'place_ar' => 'الأحساء', 'themes' => ['palm_oases'], 'pub' => 3, 'creator' => null, 'people' => null],
        ['ref' => 'DEMO-ARC-015', 'type' => 'image', 'en' => 'Pottery workshops in Najran', 'ar' => 'ورش الفخار في نجران', 'desc_en' => 'Potters shaping the distinctive ochreware of the Najran region.', 'desc_ar' => 'فخاريون يشكّلون الفخار المميز بلون نجران.', 'year' => 1993, 'place_en' => 'Najran', 'place_ar' => 'نجران', 'themes' => ['traditional_crafts'], 'pub' => 3, 'creator' => null, 'people' => null],
        ['ref' => 'DEMO-ARC-016', 'type' => 'image', 'en' => 'Calligraphy students at a Makkah art school', 'ar' => 'طلبة الخط في مدرسة فنية بمكة المكرمة', 'desc_en' => 'Classroom scene from a traditional calligraphy school.', 'desc_ar' => 'مشهد من أحد مدارس الخط العربي التقليدية.', 'year' => 1969, 'place_en' => 'Makkah', 'place_ar' => 'مكة المكرمة', 'themes' => ['arabic_calligraphy', 'holy_cities', 'islamic_art'], 'pub' => 3, 'creator' => null, 'people' => null],

        // Video / audio (metadata only, no files)
        ['ref' => 'DEMO-ARC-017', 'type' => 'video', 'en' => 'Interview with a pioneer of Saudi sculpture', 'ar' => 'لقاء مع رائد من رواد النحت السعودي', 'desc_en' => 'Recorded interview on the early years of public sculpture in Jeddah.', 'desc_ar' => 'مقابلة مسجلة عن سنوات النحت العام الأولى في جدة.', 'year' => 1998, 'place_en' => 'Jeddah', 'place_ar' => 'جدة', 'themes' => ['modern_art'], 'pub' => 3, 'creator' => null, 'people' => null],
        ['ref' => 'DEMO-ARC-018', 'type' => 'video', 'en' => 'Footage of the first national art exhibition', 'ar' => 'لقطات من المعرض الفني الأول', 'desc_en' => 'Archival footage of the opening ceremony and visitor crowds.', 'desc_ar' => 'لقطات أرشيفية من حفل الافتتاح وروّاد المعرض.', 'year' => 1970, 'place_en' => 'Riyadh', 'place_ar' => 'الرياض', 'themes' => ['modern_art', 'exhibitions'], 'pub' => 3, 'creator' => null, 'people' => null],
        ['ref' => 'DEMO-ARC-019', 'type' => 'audio', 'en' => 'Oral history: memories of the first art classes', 'ar' => 'تاريخ شفهي: ذكريات أولى الدروس الفنية', 'desc_en' => 'Oral history recording with early art students.', 'desc_ar' => 'تسجيل تاريخ شفهي مع أوائل طلبة الفن.', 'year' => 2001, 'place_en' => 'Riyadh', 'place_ar' => 'الرياض', 'themes' => ['modern_art'], 'pub' => 3, 'creator' => null, 'people' => null],
        ['ref' => 'DEMO-ARC-020', 'type' => 'audio', 'en' => 'Recorded interview with a Sadu master weaver', 'ar' => 'مقابلة مسجلة مع معلمة نسيج سدو', 'desc_en' => 'Interview on motifs, dyeing techniques and apprenticeship.', 'desc_ar' => 'مقابلة عن النقوش وتقنيات الصبغ والتعلّم الحرفي.', 'year' => 1996, 'place_en' => 'Qassim', 'place_ar' => 'القصيم', 'themes' => ['traditional_crafts', 'desert_bedouin'], 'pub' => 3, 'creator' => null, 'people' => null],

        // Catalogues
        ['ref' => 'DEMO-ARC-021', 'type' => 'catalogue', 'en' => 'Contemporary Saudi Artists, exhibition catalogue', 'ar' => 'فنانو السعودية المعاصرون، كتالوج معرض', 'desc_en' => 'Catalogue of a group exhibition surveying contemporary painting.', 'desc_ar' => 'كتالوج معرض جماعي يستعرض الرسم المعاصر.', 'year' => 2005, 'place_en' => 'Riyadh', 'place_ar' => 'الرياض', 'themes' => ['modern_art', 'exhibitions'], 'pub' => 0, 'creator' => null, 'people' => null],
        ['ref' => 'DEMO-ARC-022', 'type' => 'catalogue', 'en' => 'Echoes of the Desert, group show catalogue', 'ar' => 'أصداء الصحراء، كتالوج معرض جماعي', 'desc_en' => 'Catalogue of works inspired by desert landscapes and Bedouin life.', 'desc_ar' => 'كتالوج أعمال مستوحاة من المناظر الصحراوية والحياة البدوية.', 'year' => 2010, 'place_en' => 'Abha', 'place_ar' => 'أبها', 'themes' => ['desert_bedouin', 'exhibitions'], 'pub' => 0, 'creator' => null, 'people' => null],
        ['ref' => 'DEMO-ARC-023', 'type' => 'catalogue', 'en' => 'Heritage and Modernity biennial catalogue', 'ar' => 'كتالوج بينالي التراث والحداثة', 'desc_en' => 'Official catalogue of the biennial on heritage and contemporary practice.', 'desc_ar' => 'الكتالوج الرسمي لبينالي التراث والممارسة المعاصرة.', 'year' => 2016, 'place_en' => 'Riyadh', 'place_ar' => 'الرياض', 'themes' => ['exhibitions', 'traditional_crafts', 'modern_art'], 'pub' => 0, 'creator' => null, 'people' => null],
        ['ref' => 'DEMO-ARC-024', 'type' => 'catalogue', 'en' => 'Gifts of the Palm, crafts exhibition catalogue', 'ar' => 'إبداعات النخيل، كتالوج معرض الحرف', 'desc_en' => 'Catalogue of palm-based crafts from the eastern oases.', 'desc_ar' => 'كتالوج حرف النخيل من واحات المنطقة الشرقية.', 'year' => 2013, 'place_en' => 'Al-Ahsa', 'place_ar' => 'الأحساء', 'themes' => ['palm_oases', 'traditional_crafts'], 'pub' => 0, 'creator' => null, 'people' => null],

        // Posters
        ['ref' => 'DEMO-ARC-025', 'type' => 'poster', 'en' => 'National Festival of Heritage poster', 'ar' => 'ملصق المهرجان الوطني للتراث', 'desc_en' => 'Official poster of the national heritage festival.', 'desc_ar' => 'الملصق الرسمي للمهرجان الوطني للتراث.', 'year' => 1989, 'place_en' => 'Riyadh', 'place_ar' => 'الرياض', 'themes' => ['folklore', 'traditional_crafts'], 'pub' => 3, 'creator' => null, 'people' => null],
        ['ref' => 'DEMO-ARC-026', 'type' => 'poster', 'en' => 'Jeddah sculpture symposium poster', 'ar' => 'ملصق ملتقى جدة للنحت', 'desc_en' => 'Poster for the international sculpture symposium.', 'desc_ar' => 'ملصق الملتقى الدولي للنحت.', 'year' => 2012, 'place_en' => 'Jeddah', 'place_ar' => 'جدة', 'themes' => ['modern_art', 'exhibitions'], 'pub' => 3, 'creator' => null, 'people' => null],
        ['ref' => 'DEMO-ARC-027', 'type' => 'poster', 'en' => 'Arabic calligraphy exhibition poster', 'ar' => 'ملصق معرض الخط العربي', 'desc_en' => 'Poster designed with monumental Thuluth lettering.', 'desc_ar' => 'ملصق صُمم بخط الثلث الجلي.', 'year' => 2008, 'place_en' => 'Madinah', 'place_ar' => 'المدينة المنورة', 'themes' => ['arabic_calligraphy', 'islamic_art'], 'pub' => 3, 'creator' => null, 'people' => null],
        ['ref' => 'DEMO-ARC-028', 'type' => 'poster', 'en' => 'Riyadh arts week poster', 'ar' => 'ملصق أسبوع الرياض للفنون', 'desc_en' => 'Commemorative poster for the city arts week.', 'desc_ar' => 'ملصق تذكاري لأسبوع الفنون بالرياض.', 'year' => 2019, 'place_en' => 'Riyadh', 'place_ar' => 'الرياض', 'themes' => ['exhibitions', 'modern_art'], 'pub' => 3, 'creator' => null, 'people' => null],

        // Invitations
        ['ref' => 'DEMO-ARC-029', 'type' => 'invitation', 'en' => 'Opening invitation, Al-Balad gallery', 'ar' => 'دعوة افتتاح معرض البلد', 'desc_en' => 'Printed invitation card for the gallery opening in historic Jeddah.', 'desc_ar' => 'بطاقة دعوة مطبوعة لافتتاح المعرض في جدة التاريخية.', 'year' => 2002, 'place_en' => 'Jeddah', 'place_ar' => 'جدة', 'themes' => ['old_jeddah', 'exhibitions'], 'pub' => 3, 'creator' => null, 'people' => null],
        ['ref' => 'DEMO-ARC-030', 'type' => 'invitation', 'en' => 'Vernissage invitation, 1985', 'ar' => 'دعوة حفل الافتتاح ١٩٨٥', 'desc_en' => 'Invitation to a solo exhibition vernissage.', 'desc_ar' => 'دعوة لحفل افتتاح معرض فردي.', 'year' => 1985, 'place_en' => 'Khobar', 'place_ar' => 'الخبر', 'themes' => ['modern_art', 'exhibitions'], 'pub' => 3, 'creator' => null, 'people' => null],
        ['ref' => 'DEMO-ARC-031', 'type' => 'invitation', 'en' => 'Exhibition opening, cultural hall invitation', 'ar' => 'دعوة افتتاح معرض في القاعة الثقافية', 'desc_en' => 'Formal invitation signed by the organizing committee.', 'desc_ar' => 'دعوة رسمية موقعة من اللجنة المنظمة.', 'year' => 1992, 'place_en' => 'Riyadh', 'place_ar' => 'الرياض', 'themes' => ['exhibitions'], 'pub' => 3, 'creator' => null, 'people' => null],

        // Certificates
        ['ref' => 'DEMO-ARC-032', 'type' => 'certificate', 'en' => 'Certificate of participation, national exhibition', 'ar' => 'شهادة مشاركة في المعرض الوطني', 'desc_en' => 'Participation certificate awarded to exhibiting artists.', 'desc_ar' => 'شهادة مشاركة مُنحت للفنانين المشاركين.', 'year' => 1980, 'place_en' => 'Riyadh', 'place_ar' => 'الرياض', 'themes' => ['modern_art', 'exhibitions'], 'pub' => 3, 'creator' => null, 'people' => null],
        ['ref' => 'DEMO-ARC-033', 'type' => 'certificate', 'en' => 'Appreciation certificate for cultural patronage', 'ar' => 'شهادة تقدير للرعاية الثقافية', 'desc_en' => 'Certificate of appreciation for supporting the arts.', 'desc_ar' => 'شهادة تقدير لدعم الفنون.', 'year' => 1997, 'place_en' => 'Jeddah', 'place_ar' => 'جدة', 'themes' => ['modern_art'], 'pub' => 3, 'creator' => null, 'people' => null],

        // Documents
        ['ref' => 'DEMO-ARC-034', 'type' => 'document', 'en' => 'Minutes of the founding meeting of the art society', 'ar' => 'محضر الاجتماع التأسيسي للجمعية الفنية', 'desc_en' => 'Founding minutes listing founding members and first objectives.', 'desc_ar' => 'المحضر التأسيسي مع قائمة الأعضاء المؤسسين والأهداف الأولى.', 'year' => 1972, 'place_en' => 'Riyadh', 'place_ar' => 'الرياض', 'themes' => ['modern_art'], 'pub' => 3, 'creator' => null, 'people' => null],
        ['ref' => 'DEMO-ARC-035', 'type' => 'document', 'en' => 'Letter regarding the preservation of old Jeddah', 'ar' => 'خطاب بشأن الحفاظ على جدة القديمة', 'desc_en' => 'Official correspondence urging the registration of historic buildings.', 'desc_ar' => 'مراسلة رسمية تدعو لتسجيل المباني التاريخية.', 'year' => 1983, 'place_en' => 'Jeddah', 'place_ar' => 'جدة', 'themes' => ['old_jeddah', 'urban_heritage'], 'pub' => 3, 'creator' => null, 'people' => null],
        ['ref' => 'DEMO-ARC-036', 'type' => 'document', 'en' => 'Inventory of traditional crafts workshops', 'ar' => 'جرد ورش الحرف التقليدية', 'desc_en' => 'Field inventory of active craft workshops across four regions.', 'desc_ar' => 'جرد ميداني لورش الحرف النشطة في أربع مناطق.', 'year' => 2009, 'place_en' => 'Riyadh', 'place_ar' => 'الرياض', 'themes' => ['traditional_crafts'], 'pub' => 3, 'creator' => null, 'people' => null],
    ];

    /** @var array<int, array{en: string, ar: string}> */
    private const PUBLICATIONS = [
        ['en' => 'Arts & Culture Magazine', 'ar' => 'مجلة الفنون والثقافة'],
        ['en' => 'Al-Riyadh Daily', 'ar' => 'جريدة الرياض'],
        ['en' => 'Fine Art Magazine', 'ar' => 'مجلة الفن التشكيلي'],
        ['en' => 'Saudi Press Agency', 'ar' => 'وكالة الأنباء السعودية'],
    ];

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('DemoThemeArchiveSeeder only runs in the local environment.');

            return;
        }

        $faker = fake('en_US');

        $themes = [];

        foreach (self::THEMES as $slug => $labels) {
            $themes[$slug] = Theme::updateOrCreate(
                ['label_en' => $labels['en']],
                ['label_ar' => $labels['ar']],
            );
        }

        $demoArtists = Artist::where('legacy_code', 'like', 'DEMO-%')->orderBy('legacy_code')->get();
        $demoArtworks = Artwork::where('legacy_ref', 'like', 'DEMO-ART-%')->orderBy('legacy_ref')->get();

        foreach (self::ITEMS as $index => $data) {
            $published = $faker->boolean(80);
            $publication = self::PUBLICATIONS[$data['pub']];

            $item = ArchiveItem::withTrashed()->updateOrCreate(
                ['legacy_ref' => $data['ref']],
                [
                    'item_type' => $data['type'],
                    'title_en' => $data['en'],
                    'title_ar' => $data['ar'],
                    'description_en' => $data['desc_en'],
                    'description_ar' => $data['desc_ar'],
                    'creator_name' => $data['creator'],
                    'publication_name_en' => in_array($data['type'], ['article'], true) ? $publication['en'] : null,
                    'publication_name_ar' => in_array($data['type'], ['article'], true) ? $publication['ar'] : null,
                    'issue_no' => $data['type'] === 'article' ? (string) $faker->numberBetween(1, 40) : null,
                    'page' => $data['type'] === 'article' ? (string) $faker->numberBetween(1, 30) : null,
                    'language' => $faker->randomElement(['ar', 'en', 'und']),
                    'original_format' => 'physical',
                    'quality_flag' => $faker->boolean(85) ? 'high' : 'needs_rescan',
                    'content_date_display' => (string) $data['year'],
                    'content_year_from' => $data['year'],
                    'content_year_to' => $data['year'],
                    'content_calendar' => 'gregorian',
                    'content_certainty' => 'exact',
                    'digitized_at' => $faker->dateTimeBetween("{$data['year']}-01-01", 'now'),
                    'access_level' => $faker->randomElement(['public', 'public', 'registered', 'researcher', 'institution_only']),
                    'rights_status' => $faker->randomElement(['public_domain', 'licensed', 'licensed']),
                    'rights_holder_en' => 'King Abdulaziz Public Library',
                    'rights_holder_ar' => 'مكتبة الملك عبدالعزيز العامة',
                    'license' => $faker->randomElement(['CC BY-SA 4.0', 'CC BY-NC 4.0', null]),
                    'consent_status' => $faker->randomElement(['signed', 'unknown']),
                    'publication_status' => $published ? 'published' : 'draft',
                    'published_at' => $published ? now() : null,
                    'place_en' => $data['place_en'],
                    'place_ar' => $data['place_ar'],
                    'people_names' => $data['people'],
                    'keywords' => $faker->randomElements(
                        ['التراث', 'فن تشكيلي', 'حرف يدوية', 'خط عربي', 'عمارة', 'تصوير', 'معرض', 'السدو', 'heritage', 'art', 'crafts', 'exhibition'],
                        $faker->numberBetween(2, 4),
                    ),
                    'source_name' => $faker->randomElement(['Legacy digitization project', 'Founding collection', 'Field documentation']),
                    'verification_reference' => 'DEMO-VERIFY-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'creation_approved_at' => now(),
                ],
            );

            $item->themes()->syncWithoutDetaching(
                collect($data['themes'])->map(fn (string $slug) => $themes[$slug]->id)->all(),
            );

            $this->linkToDemoRecords($item, $index, $demoArtists, $demoArtworks);
            $this->ensureFile($item, $faker);
        }

        $this->command?->info(sprintf(
            'Seeded %d themes and %d archive items.',
            count(self::THEMES),
            count(self::ITEMS),
        ));
    }

    /**
     * Deterministically link each item to one demo artist and, for roughly
     * half of them, one demo artwork, so the archive graph is navigable.
     *
     * @param  Collection<int, Artist>  $artists
     * @param  Collection<int, Artwork>  $artworks
     */
    private function linkToDemoRecords(ArchiveItem $item, int $index, $artists, $artworks): void
    {
        if ($artists->isEmpty()) {
            return;
        }

        $artist = $artists[$index % $artists->count()];

        ArchiveItemLink::firstOrCreate([
            'archive_item_id' => $item->id,
            'linkable_type' => Artist::class,
            'linkable_id' => $artist->id,
            'role' => $item->item_type === 'article' ? 'mentions' : 'subject',
        ]);

        if ($artworks->isNotEmpty() && $index % 2 === 0) {
            ArchiveItemLink::firstOrCreate([
                'archive_item_id' => $item->id,
                'linkable_type' => Artwork::class,
                'linkable_id' => $artworks[($index * 3) % $artworks->count()]->id,
                'role' => 'depicts',
            ]);
        }
    }

    private function ensureFile(ArchiveItem $item, Generator $faker): void
    {
        if (! in_array($item->item_type, self::VISUAL_TYPES, true) || $item->files()->exists()) {
            return;
        }

        [$path, $widthPx, $heightPx, $bytes] = $this->generateImage($item->legacy_ref);
        $contents = Storage::disk('local')->get($path);

        File::create([
            'archive_item_id' => $item->id,
            'role' => 'original',
            'disk' => 'local',
            'path' => $path,
            'mime_type' => 'image/jpeg',
            'size_bytes' => $bytes,
            'sha256' => hash('sha256', $contents),
            'width_px' => $widthPx,
            'height_px' => $heightPx,
            'original_filename' => $item->legacy_ref.'.jpg',
        ]);
    }

    /**
     * @return array{0: string, 1: int, 2: int, 3: int} path, width, height, size in bytes
     */
    private function generateImage(string $name): array
    {
        $width = random_int(900, 1400);
        $height = random_int(700, 1600);
        $image = imagecreatetruecolor($width, $height);

        $base = imagecolorallocate($image, random_int(150, 235), random_int(140, 220), random_int(120, 200));
        imagefill($image, 0, 0, $base);

        for ($i = 0; $i < 8; $i++) {
            $color = imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255));

            if (random_int(0, 1) === 1) {
                imagefilledrectangle($image, random_int(0, $width), random_int(0, $height), random_int(0, $width), random_int(0, $height), $color);
            } else {
                imagefilledellipse($image, random_int(0, $width), random_int(0, $height), random_int(20, (int) ($width / 2)), random_int(20, (int) ($height / 2)), $color);
            }
        }

        ob_start();
        imagejpeg($image, null, 80);
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        $path = "demo-archive/files/{$name}.jpg";
        Storage::disk('local')->put($path, $contents);

        return [$path, $width, $height, strlen($contents)];
    }
}

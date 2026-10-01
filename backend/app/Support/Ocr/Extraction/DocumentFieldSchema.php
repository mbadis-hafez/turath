<?php

namespace App\Support\Ocr\Extraction;

use App\Enums\DocumentType;
use App\Enums\ExtractedDateType;
use App\Support\Ocr\ArtistAuthorizationFields;

/**
 * The fields each document type exposes, in display order — one schema per
 * type, not one universal one. Every field maps onto something that already
 * exists: an archive-item field, an Artist/Artwork/Event/Holder/Source
 * record or attribute, an ArtistContact, or (where nothing fits) evidence
 * only. No new record columns are implied.
 *
 * Keywords are written naturally; they are compared after ArabicNormalizer,
 * so alef/hamza/ta-marbuta spellings and Arabic-Indic digits all match.
 */
final class DocumentFieldSchema
{
    private const REFERENCES_SECTIONS = ['المراجع', 'المصادر', 'references', 'sources', 'bibliography'];

    /**
     * @return list<FieldDefinition>
     */
    public static function for(DocumentType $type): array
    {
        return match ($type) {
            DocumentType::ArtistAuthorization => self::artistAuthorization(),
            DocumentType::ArtistBiography => self::artistBiography(),
            DocumentType::ArtworkConditionReport => self::artworkConditionReport(),
            DocumentType::ExhibitionDocument => self::exhibitionDocument(),
            DocumentType::Unknown => [],
        };
    }

    public static function field(DocumentType $type, string $key): ?FieldDefinition
    {
        foreach (self::for($type) as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Contact details come first so their labels win (see ArtistAuthorizationFields).
     *
     * @return list<FieldDefinition>
     */
    private static function artistAuthorization(): array
    {
        $contact = FieldDefinition::ROUTE_ARTIST_CONTACT;
        $keywords = ArtistAuthorizationFields::LABEL_KEYWORDS;

        return [
            self::text('email', 'البريد الإلكتروني', 'Email', $contact, 'artist_contact.email', $keywords['email']),
            self::text('phone', 'رقم الجوال', 'Phone', $contact, 'artist_contact.phone', $keywords['phone']),
            self::text('artist_name', 'اسم الفنان/ة', 'Artist name', $contact, 'artist', $keywords['artist_name']),
            self::text('address', 'العنوان', 'Address', $contact, 'artist_contact.address', $keywords['address']),
            self::statement('authorization_statement', 'نص التفويض', 'Authorization statement', [['أفوض'], ['أخول'], ['authorize'], ['authorise']]),
            // Either an explicit "I agree to publication", or the letter's publication clause the artist accepts by signing.
            self::statement('publication_consent', 'الموافقة على النشر', 'Publication consent', [
                ['أوافق', 'نشر'], ['موافقتي', 'نشر'], ['الموافقة', 'نشر'], ['نشر', 'كتاب'], ['منشورات'],
                ['consent', 'publish'], ['agree', 'publish'], ['publication', 'book'],
            ]),
            self::statement('copyright_consent', 'حقوق الطبع والنشر', 'Copyright consent', [['حقوق', 'الطبع'], ['حقوق', 'النشر'], ['حقوق', 'الملكية'], ['copyright']]),
            self::date('document_issue_date', 'تاريخ الخطاب', 'Document issue date', ExtractedDateType::DocumentIssueDate),
            self::date('signature_date', 'تاريخ التوقيع', 'Signature date', ExtractedDateType::SignatureDate),
        ];
    }

    /**
     * @return list<FieldDefinition>
     */
    private static function artistBiography(): array
    {
        $entity = FieldDefinition::ROUTE_ENTITY;

        return [
            self::text('artist', 'الفنان/ة', 'Artist', $entity, 'artist', ['اسم الفنان', 'الفنان', 'الفنانة', 'الاسم', 'artist', 'name'], 'artist'),
            self::date('birth_date', 'تاريخ الميلاد', 'Birth date', ExtractedDateType::BirthDate),
            self::text('birth_place', 'مكان الميلاد', 'Birth place', $entity, 'artist.birth_place', ['مكان الميلاد', 'محل الميلاد', 'place of birth', 'birthplace'], 'place'),
            self::date('death_date', 'تاريخ الوفاة', 'Death date', ExtractedDateType::DeathDate),
            new FieldDefinition('biography', ['ar' => 'السيرة الذاتية', 'en' => 'Biography'], FieldDefinition::KIND_TEXT, $entity, 'artist.bio',
                sectionKeywords: ['السيرة الذاتية', 'السيرة', 'نبذة', 'نبذة عن الفنان', 'biography', 'bio', 'about the artist']),
            self::list('education', 'التعليم', 'Education', FieldDefinition::ROUTE_EVIDENCE, null, ['التعليم', 'المؤهلات', 'المؤهل العلمي', 'الدراسة', 'education', 'studies']),
            self::list('exhibitions', 'المعارض', 'Exhibitions', $entity, 'event', ['المعارض', 'معارض', 'المعارض الفردية', 'المعارض الجماعية', 'exhibitions', 'solo exhibitions', 'group exhibitions'], 'event'),
            self::list('collections', 'المقتنيات', 'Collections', $entity, 'holder', ['المقتنيات', 'مقتنيات', 'الاقتناءات', 'collections', 'public collections'], 'holder'),
            self::list('institutions', 'الجهات والعضويات', 'Institutions', $entity, 'holder', ['العضويات', 'الجهات', 'المؤسسات', 'institutions', 'memberships', 'affiliations'], 'holder'),
            self::list('references', 'المراجع', 'References', $entity, 'source', self::REFERENCES_SECTIONS, 'source'),
        ];
    }

    /**
     * @return list<FieldDefinition>
     */
    private static function artworkConditionReport(): array
    {
        $entity = FieldDefinition::ROUTE_ENTITY;
        $evidence = FieldDefinition::ROUTE_EVIDENCE;

        return [
            self::text('artist', 'الفنان/ة', 'Artist', $entity, 'artist', ['اسم الفنان', 'الفنان', 'الفنانة', 'artist'], 'artist'),
            // The artwork's own identifier — matched against an artwork's inventory number or legacy reference.
            self::text('artwork', 'رقم العمل', 'Artwork reference', $entity, 'artwork', ['رقم العمل', 'رقم الجرد', 'رقم القطعة', 'الرقم المرجعي', 'inventory', 'accession', 'object number'], 'artwork'),
            self::text('artwork_title', 'عنوان العمل', 'Artwork title', $entity, 'artwork.title', ['اسم العمل', 'عنوان العمل', 'العنوان', 'title'], 'artwork'),
            self::text('dimensions', 'الأبعاد', 'Dimensions', $entity, 'artwork.dimensions', ['الأبعاد', 'المقاس', 'المقاسات', 'الحجم', 'dimensions', 'size']),
            self::text('medium', 'الخامة', 'Medium', $entity, 'artwork.medium', ['الخامة', 'الخامات', 'الوسيط', 'المادة', 'medium', 'materials', 'material']),
            self::text('technique', 'التقنية', 'Technique', $evidence, null, ['التقنية', 'الأسلوب', 'technique']),
            self::text('condition', 'الحالة', 'Condition', $evidence, null, ['الحالة', 'حالة العمل', 'الحالة العامة', 'condition', 'overall condition']),
            self::text('conservation_notes', 'ملاحظات الترميم', 'Conservation notes', $evidence, null, ['ملاحظات الترميم', 'الترميم', 'التوصيات', 'conservation', 'treatment', 'recommendations']),
            self::date('report_date', 'تاريخ التقرير', 'Report date', ExtractedDateType::DocumentIssueDate),
            self::text('institution', 'الجهة', 'Institution', $entity, 'holder', ['الجهة', 'المؤسسة', 'المتحف', 'الجهة المالكة', 'المالك', 'institution', 'museum', 'owner'], 'holder'),
        ];
    }

    /**
     * @return list<FieldDefinition>
     */
    private static function exhibitionDocument(): array
    {
        $entity = FieldDefinition::ROUTE_ENTITY;

        return [
            new FieldDefinition('exhibition', ['ar' => 'المعرض', 'en' => 'Exhibition'], FieldDefinition::KIND_LINK, $entity, 'event'),
            self::text('exhibition_title', 'عنوان المعرض', 'Exhibition title', $entity, 'event.title', ['اسم المعرض', 'عنوان المعرض', 'المعرض', 'exhibition', 'exhibition title', 'title'], 'event'),
            self::text('venue', 'المكان', 'Venue', $entity, 'event.venue_name', ['المكان', 'مكان المعرض', 'مقر المعرض', 'القاعة', 'الصالة', 'venue', 'location'], 'holder'),
            self::text('city', 'المدينة', 'City', $entity, 'event.city', ['المدينة', 'city'], 'place'),
            self::date('opening_date', 'تاريخ الافتتاح', 'Opening date', ExtractedDateType::EventDate),
            self::date('closing_date', 'تاريخ الختام', 'Closing date', ExtractedDateType::EventDate),
            self::list('participating_artists', 'الفنانون المشاركون', 'Participating artists', $entity, 'artist', ['الفنانون المشاركون', 'الفنانين المشاركين', 'المشاركون', 'المشاركين', 'الفنانون', 'participating artists', 'artists'], 'artist'),
            self::list('artworks', 'الأعمال', 'Artworks', $entity, 'artwork', ['الأعمال', 'الأعمال المعروضة', 'قائمة الأعمال', 'artworks', 'works', 'list of works'], 'artwork'),
            self::list('references', 'المراجع', 'References', $entity, 'source', self::REFERENCES_SECTIONS, 'source'),
        ];
    }

    /**
     * @param  list<string>  $keywords
     */
    private static function text(string $key, string $ar, string $en, string $route, ?string $target, array $keywords, ?string $matchAs = null): FieldDefinition
    {
        return new FieldDefinition($key, ['ar' => $ar, 'en' => $en], FieldDefinition::KIND_TEXT, $route, $target, labelKeywords: $keywords, matchAs: $matchAs);
    }

    /**
     * @param  list<string>  $sections
     */
    private static function list(string $key, string $ar, string $en, string $route, ?string $target, array $sections, ?string $matchAs = null): FieldDefinition
    {
        return new FieldDefinition($key, ['ar' => $ar, 'en' => $en], FieldDefinition::KIND_LIST, $route, $target, labelKeywords: $sections, sectionKeywords: $sections, matchAs: $matchAs);
    }

    /**
     * @param  list<list<string>>  $phrases
     */
    private static function statement(string $key, string $ar, string $en, array $phrases): FieldDefinition
    {
        return new FieldDefinition($key, ['ar' => $ar, 'en' => $en], FieldDefinition::KIND_TEXT, FieldDefinition::ROUTE_EVIDENCE, null, phrases: $phrases);
    }

    private static function date(string $key, string $ar, string $en, ExtractedDateType $role): FieldDefinition
    {
        return new FieldDefinition($key, ['ar' => $ar, 'en' => $en], FieldDefinition::KIND_DATE, FieldDefinition::ROUTE_EVIDENCE, null, dateRole: $role);
    }
}

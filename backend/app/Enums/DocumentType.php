<?php

namespace App\Enums;

/**
 * A first-pass classification of what kind of archival document this file is.
 * Different document types expose different semantic fields — an authorization
 * letter has no artwork dimensions, a condition report has no email address.
 * See docs/ocr-pipeline.md for why this exists instead of one universal schema.
 */
enum DocumentType: string
{
    case ArtistAuthorization = 'artist_authorization';
    case ArtworkConditionReport = 'artwork_condition_report';
    case ArtistBiography = 'artist_biography';
    case Unknown = 'unknown';

    /**
     * The candidate field keys this document type can expose, purely as labels
     * for the reviewer UI and future extractors — this does not itself extract
     * anything, and none of these keys are wired into ExtractedFieldPayloadMapper.
     *
     * @return array<string, array{ar: string, en: string}>
     */
    public function fieldSchema(): array
    {
        return match ($this) {
            self::ArtistAuthorization => [
                'artist_name' => ['ar' => 'اسم الفنان/ة', 'en' => 'Artist name'],
                'artist_address' => ['ar' => 'العنوان', 'en' => 'Address'],
                'artist_email' => ['ar' => 'البريد الإلكتروني', 'en' => 'Email'],
                'artist_phone' => ['ar' => 'رقم الجوال', 'en' => 'Phone'],
                'authorization_statement' => ['ar' => 'نص التفويض', 'en' => 'Authorization statement'],
                'document_issue_date' => ['ar' => 'تاريخ الخطاب', 'en' => 'Document issue date'],
                'signature_date' => ['ar' => 'تاريخ التوقيع', 'en' => 'Signature date'],
                'source_reference' => ['ar' => 'المرجع/المشروع', 'en' => 'Source/project reference'],
            ],
            self::ArtworkConditionReport => [
                'artwork' => ['ar' => 'العمل الفني', 'en' => 'Artwork'],
                'artist' => ['ar' => 'الفنان/ة', 'en' => 'Artist'],
                'title' => ['ar' => 'العنوان', 'en' => 'Title'],
                'dimensions' => ['ar' => 'الأبعاد', 'en' => 'Dimensions'],
                'medium' => ['ar' => 'الخامة', 'en' => 'Medium'],
                'condition' => ['ar' => 'الحالة', 'en' => 'Condition'],
                'conservation_notes' => ['ar' => 'ملاحظات الترميم', 'en' => 'Conservation notes'],
                'date' => ['ar' => 'التاريخ', 'en' => 'Date'],
            ],
            self::ArtistBiography => [
                'artist' => ['ar' => 'الفنان/ة', 'en' => 'Artist'],
                'birth_date' => ['ar' => 'تاريخ الميلاد', 'en' => 'Birth date'],
                'birth_place' => ['ar' => 'مكان الميلاد', 'en' => 'Birth place'],
                'biography' => ['ar' => 'السيرة الذاتية', 'en' => 'Biography'],
                'education' => ['ar' => 'التعليم', 'en' => 'Education'],
                'exhibitions' => ['ar' => 'المعارض', 'en' => 'Exhibitions'],
                'collections' => ['ar' => 'المقتنيات', 'en' => 'Collections'],
                'references' => ['ar' => 'المراجع', 'en' => 'References'],
            ],
            self::Unknown => [],
        };
    }
}

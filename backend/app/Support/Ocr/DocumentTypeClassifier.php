<?php

namespace App\Support\Ocr;

use App\Enums\DocumentType;
use App\Support\ArabicNormalizer;

/**
 * Scores OCR'd text against a small keyword set per document type and picks
 * the highest-scoring one above a minimum bar. This decides which field
 * schema (Extraction\DocumentFieldSchema) is relevant for a document — it
 * does not itself extract anything. A reviewer can override the result.
 *
 * Both sides are compared after ArabicNormalizer, so an OCR reading of
 * "حالة" as "حاله" or "الأبعاد" as "الابعاد" still counts. On a tie the type
 * listed first wins: an exhibition document is last because biographies
 * mention exhibitions too.
 */
class DocumentTypeClassifier
{
    private const MIN_SCORE = 2;

    /** @var array<string, array<int, string>> */
    private const KEYWORDS = [
        'artist_authorization' => ['تفويض', 'أفوض', 'الفنان/ة', 'الموافقة', 'حقوق الطبع والنشر'],
        'artwork_condition_report' => ['تقرير حالة', 'الأبعاد', 'الخامة', 'الترميم', 'حالة العمل'],
        'artist_biography' => ['السيرة الذاتية', 'ولد عام', 'تخرج', 'المعارض', 'نشأ في'],
        'exhibition_document' => ['معرض', 'افتتاح', 'الفنانين المشاركين', 'الفنانون المشاركون', 'exhibition', 'opening'],
    ];

    /**
     * @param  array<string, array<int, array{text: string, confidence: int}>>  $pagesByLanguage
     */
    public function classify(array $pagesByLanguage): DocumentType
    {
        $combined = ArabicNormalizer::normalize(implode("\n", array_merge(
            array_column($pagesByLanguage['ar'] ?? [], 'text'),
            array_column($pagesByLanguage['en'] ?? [], 'text'),
        )));

        $bestType = null;
        $bestScore = 0;

        foreach (self::KEYWORDS as $type => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                if (mb_stripos($combined, ArabicNormalizer::normalize($keyword)) !== false) {
                    $score++;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestType = $type;
            }
        }

        if ($bestType === null || $bestScore < self::MIN_SCORE) {
            return DocumentType::Unknown;
        }

        return DocumentType::from($bestType);
    }
}

<?php

namespace App\Support\Ocr\Extraction;

use App\Enums\DocumentType;
use App\Enums\ExtractionMethod;
use App\Support\ArabicDigits;
use App\Support\ArabicNormalizer;

/**
 * Reads a document's schema fields (DocumentFieldSchema) off its printed
 * text and form fields. Input is what the OCR layer already trusts: lines
 * of printed-text regions (never handwriting, whose regions carry no text)
 * and detected "label: value" form pairs, including values a reviewer
 * transcribed by hand. It never reads a value out of handwriting OCR.
 *
 * Rules, most reliable first; for a single-value field the first reading
 * wins (see FoundFields):
 *
 *   form_field     a detected form label naming the field, and its value
 *   inline_label   a printed "label: value" line
 *   section        the lines under a heading ("المعارض", "Collections")
 *   statement      the paragraph containing a consent/authorization phrase
 *   pattern:*      a few type-specific shapes: dimensions, "ولد في …",
 *                  "في قاعة …". Contact details are never taken from a
 *                  pattern — a letterhead or footer carries the
 *                  organisation's own email and phone, not the artist's.
 *
 * Everything returned is a suggestion with its provenance; nothing here is
 * trusted until a reviewer says so.
 */
class DocumentFieldExtractor
{
    private const MAX_STATEMENT_CHARS = 1000;

    /** A heading is short: the keyword and at most a couple of words around it. */
    private const MAX_HEADING_WORDS = 4;

    /** A narrative paragraph shorter than this isn't taken as a biography. */
    private const MIN_NARRATIVE_CHARS = 120;

    /**
     * @param  list<array{page: int, region_id: ?int, text: string, confidence: ?int}>  $lines  printed text, one line each, in reading order
     * @param  list<array{id: int, label: string, value: ?string, method: ?string, raw: ?string, page: ?int, region_id: ?int, crop_path: ?string, confidence: ?int}>  $formFields  in document order
     * @return list<array{field_key: string, value: ?string, confidence: int, page: ?int, region_id: ?int, form_field_id: ?int, method: string, rule: string, original: ?string, crop_path: ?string}>
     */
    public function extract(DocumentType $type, array $lines, array $formFields): array
    {
        $schema = DocumentFieldSchema::for($type);
        if ($schema === []) {
            return [];
        }

        $found = new FoundFields($schema);
        $this->fromFormFields($schema, $formFields, $found);
        $this->fromInlineLabels($schema, $lines, $found);
        $sectionLines = $this->fromSections($schema, $lines, $found);
        $this->fromStatements($schema, $lines, $found);
        $this->fromPatterns($type, $lines, $found);
        $this->biographyNarrative($type, $lines, $sectionLines, $found);

        return $found->all();
    }

    /**
     * @param  list<FieldDefinition>  $schema
     * @param  list<array{id: int, label: string, value: ?string, method: ?string, raw: ?string, page: ?int, region_id: ?int, crop_path: ?string, confidence: ?int}>  $formFields
     */
    private function fromFormFields(array $schema, array $formFields, FoundFields $found): void
    {
        foreach ($formFields as $field) {
            $definition = $this->fieldForLabel($field['label'], $schema);
            if ($definition === null) {
                continue;
            }

            $manual = $field['method'] === ExtractionMethod::ManuallyTranscribed->value;
            $found->add($definition, [
                'value' => $field['value'],
                // A transcription is a person's reading; a missing value (handwriting not yet transcribed) is no reading at all.
                'confidence' => $field['value'] === null ? 0 : ($manual ? 100 : min($field['confidence'] ?? 70, 85)),
                'page' => $field['page'],
                'region_id' => $field['region_id'],
                'form_field_id' => $field['id'],
                'method' => $manual ? ExtractionMethod::ManuallyTranscribed->value : ExtractionMethod::OcrDerived->value,
                'rule' => 'form_field',
                'original' => $field['raw'],
                'crop_path' => $field['crop_path'],
            ]);
        }
    }

    /**
     * @param  list<FieldDefinition>  $schema
     * @param  list<array{page: int, region_id: ?int, text: string, confidence: ?int}>  $lines
     */
    private function fromInlineLabels(array $schema, array $lines, FoundFields $found): void
    {
        foreach ($lines as $line) {
            if (preg_match('/^\s*([^:：]{2,60}?)\s*[:：]\s*(.+?)\s*$/u', $line['text'], $m) !== 1) {
                continue;
            }
            if (count(preg_split('/\s+/u', trim($m[1])) ?: []) > 5) {
                continue; // a sentence with a colon in it, not a label
            }
            $definition = $this->fieldForLabel($m[1], $schema);
            if ($definition === null) {
                continue;
            }

            foreach ($definition->isMultiple() ? $this->splitItems($m[2]) : [$m[2]] as $value) {
                $found->add($definition, $this->fromLine($line, $value, 'inline_label', min($line['confidence'] ?? 70, 80)));
            }
        }
    }

    /**
     * Lines under a heading belong to that heading's field until the next heading.
     *
     * @param  list<FieldDefinition>  $schema
     * @param  list<array{page: int, region_id: ?int, text: string, confidence: ?int}>  $lines
     * @return array<int, true> indexes of the headings and the lines under them
     */
    private function fromSections(array $schema, array $lines, FoundFields $found): array
    {
        $sectioned = array_values(array_filter($schema, fn (FieldDefinition $f) => $f->sectionKeywords !== []));
        if ($sectioned === []) {
            return [];
        }

        $used = [];
        $current = null;
        /** @var list<array{page: int, region_id: ?int, text: string, confidence: ?int}> $textLines */
        $textLines = [];

        foreach ($lines as $index => $line) {
            $heading = $this->headingFor($line['text'], $sectioned);
            if ($heading !== null) {
                $this->addSectionText($current, $textLines, $found);
                [$current, $textLines, $used[$index]] = [$heading, [], true];

                continue;
            }
            $item = $this->stripBullet($line['text']);
            if ($current === null || preg_match_all('/\p{L}/u', $item) < 3) {
                continue;
            }

            $used[$index] = true;
            if ($current->isMultiple()) {
                $found->add($current, $this->fromLine($line, $item, 'section', min($line['confidence'] ?? 70, 70)));
            } else {
                $textLines[] = [...$line, 'text' => $item];
            }
        }
        $this->addSectionText($current, $textLines, $found);

        return $used;
    }

    /**
     * A single-value field under a heading (a biography) takes all its lines as one text.
     *
     * @param  list<array{page: int, region_id: ?int, text: string, confidence: ?int}>  $textLines
     */
    private function addSectionText(?FieldDefinition $field, array $textLines, FoundFields $found): void
    {
        if ($field === null || $field->isMultiple() || $textLines === []) {
            return;
        }
        $first = $textLines[0];
        $found->add($field, $this->fromLine($first, implode("\n", array_column($textLines, 'text')), 'section', min($first['confidence'] ?? 70, 60)));
    }

    /**
     * @param  list<FieldDefinition>  $schema
     * @param  list<array{page: int, region_id: ?int, text: string, confidence: ?int}>  $lines
     */
    private function fromStatements(array $schema, array $lines, FoundFields $found): void
    {
        $statements = array_filter($schema, fn (FieldDefinition $f) => $f->phrases !== []);
        if ($statements === []) {
            return;
        }

        foreach ($this->paragraphs($lines) as $paragraph) {
            $words = ' '.ArabicNormalizer::normalize($paragraph['text']).' ';
            foreach ($statements as $definition) {
                foreach ($definition->phrases as $group) {
                    if ($this->containsAllWords($words, $group)) {
                        $found->add($definition, $this->fromLine($paragraph, mb_substr($paragraph['text'], 0, self::MAX_STATEMENT_CHARS), 'statement', 60));

                        break;
                    }
                }
            }
        }
    }

    /**
     * @param  list<array{page: int, region_id: ?int, text: string, confidence: ?int}>  $lines
     */
    private function fromPatterns(DocumentType $type, array $lines, FoundFields $found): void
    {
        foreach ($this->typePatterns($type) as [$key, $rule, $pattern, $confidence]) {
            $definition = DocumentFieldSchema::field($type, $key);
            if ($definition === null || $found->has($key)) {
                continue;
            }
            foreach ($lines as $line) {
                $value = ArabicDigits::match($pattern, $line['text']);
                if ($value !== null && trim($value) !== '') {
                    $found->add($definition, $this->fromLine($line, trim($value), "pattern:{$rule}", $confidence));

                    break;
                }
            }
        }

        $title = DocumentFieldSchema::field($type, 'exhibition_title');
        if ($type === DocumentType::ExhibitionDocument && $title !== null && ! $found->has('exhibition_title')) {
            // An invitation's heading: an early line that starts "معرض …" or names an exhibition.
            foreach (array_slice($lines, 0, 6) as $line) {
                $wordCount = count(preg_split('/\s+/u', trim($line['text'])) ?: []);
                $normalized = ArabicNormalizer::normalize($line['text']);
                if ($wordCount >= 2 && $wordCount <= 12 && (str_starts_with($normalized, 'معرض ') || str_contains($normalized, 'exhibition'))) {
                    $found->add($title, $this->fromLine($line, trim($line['text']), 'pattern:heading', 50));

                    break;
                }
            }
        }
    }

    /**
     * A biography with no "السيرة الذاتية" heading: its longest paragraph that isn't a list.
     *
     * @param  list<array{page: int, region_id: ?int, text: string, confidence: ?int}>  $lines
     * @param  array<int, true>  $sectionLines
     */
    private function biographyNarrative(DocumentType $type, array $lines, array $sectionLines, FoundFields $found): void
    {
        $biography = DocumentFieldSchema::field($type, 'biography');
        if ($biography === null || $found->has('biography')) {
            return;
        }

        $outside = array_values(array_filter($lines, fn (int $index) => ! isset($sectionLines[$index]), ARRAY_FILTER_USE_KEY));
        $paragraphs = $this->paragraphs($outside);
        usort($paragraphs, fn ($a, $b) => mb_strlen($b['text']) <=> mb_strlen($a['text']));
        if ($paragraphs !== [] && mb_strlen($paragraphs[0]['text']) >= self::MIN_NARRATIVE_CHARS) {
            $found->add($biography, $this->fromLine($paragraphs[0], $paragraphs[0]['text'], 'pattern:narrative', 45));
        }
    }

    /**
     * Type-specific shapes, each capturing (?<value>…), matched with Arabic-Indic digits read as ASCII.
     *
     * @return list<array{0: string, 1: string, 2: string, 3: int}> field key, rule name, pattern, confidence
     */
    private function typePatterns(DocumentType $type): array
    {
        $words = '[\p{Arabic}]{2,}(?:\s+[\p{Arabic}]{2,}){0,2}?';
        // Stop words end at a word boundary: "في" must not stop "فيصل".
        $placeEnd = '(?=\s+(?:(?:عام|سنة|في|بتاريخ|حيث)(?!\p{L})|و\S*)|\s*[،,.;\d]|\s*$)';

        return match ($type) {
            DocumentType::ArtworkConditionReport => [
                ['dimensions', 'dimensions', '/(?<value>(?<!\d)\d+(?:[.,]\d+)?\s*[x×X*]\s*\d+(?:[.,]\d+)?(?:\s*[x×X*]\s*\d+(?:[.,]\d+)?)?(?:\s*(?:سم|cm|مم|mm))?)/u', 60],
            ],
            DocumentType::ArtistBiography => [
                ['artist', 'biography_heading', '/السيرة الذاتية\s+(?:لل|ل)(?:فنان|فنانة)\s+(?<value>[\p{Arabic}]{2,}(?:\s+[\p{Arabic}]{2,}){1,4})/u', 55],
                ['birth_place', 'born_in', '/(?:^|\s)و?(?:ولد|ولدت)(?:\s+\S+){0,4}?\s+(?:في\s+|ب(?=ال))(?:مدينة\s+|محافظة\s+|قرية\s+)?(?<value>'.$words.')'.$placeEnd.'/u', 55],
                ['birth_place', 'born_in', '/(?:^|\s)من مواليد\s+(?:مدينة\s+|محافظة\s+)?(?<value>'.$words.')'.$placeEnd.'/u', 55],
                ['birth_place', 'born_in', '/\bborn\b(?:\s+(?:on|in)\s+[\w\s,]*?\d{4})?\s+in\s+(?<value>[A-Z][\p{L}\'\-]+(?:[\s-][A-Z][\p{L}\'\-]+){0,2})/u', 55],
            ],
            DocumentType::ExhibitionDocument => [
                ['venue', 'venue', '/(?:في|ب)\s*(?<value>(?:قاعة|صالة|مركز|متحف|دار|جاليري|غاليري|بيت|مؤسسة|جمعية)\s+[\p{Arabic}\s]{2,40}?)(?=\s+(?:بمدينة|في|يوم|من|خلال|حتى|وذلك)(?!\p{L})|\s*[،,.\d]|\s*$)/u', 50],
                ['venue', 'venue', '/\bat\s+(?:the\s+)?(?<value>(?:[A-Z][\p{L}\'&\-]*\s+){0,4}(?:Gallery|Museum|Center|Centre|Hall|House|Foundation))/u', 50],
                ['city', 'city', '/(?:بمدينة|في مدينة)\s+(?<value>[\p{Arabic}]{2,}(?:\s+(?:المكرمة|المنورة))?)/u', 50],
            ],
            default => [],
        };
    }

    /**
     * @param  list<FieldDefinition>  $schema
     */
    private function fieldForLabel(string $label, array $schema): ?FieldDefinition
    {
        $padded = ' '.ArabicNormalizer::normalize($label).' ';
        foreach ($schema as $definition) {
            if ($definition->kind === FieldDefinition::KIND_DATE || $definition->kind === FieldDefinition::KIND_LINK) {
                continue; // dates are the DateExtractor's; links come from entity matching
            }
            foreach ($definition->labelKeywords as $keyword) {
                if (str_contains($padded, ' '.ArabicNormalizer::normalize($keyword).' ')) {
                    return $definition;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<FieldDefinition>  $sectioned
     */
    private function headingFor(string $text, array $sectioned): ?FieldDefinition
    {
        $stripped = trim((string) preg_replace('/[:：]\s*$/u', '', $this->stripBullet($text)));
        if ($stripped === '' || preg_match('/[:：]/u', $stripped) === 1) {
            return null;
        }
        $normalized = ArabicNormalizer::normalize($stripped);
        if ($normalized === '' || count(explode(' ', $normalized)) > self::MAX_HEADING_WORDS) {
            return null;
        }

        $padded = " {$normalized} ";
        foreach ($sectioned as $definition) {
            foreach ($definition->sectionKeywords as $keyword) {
                if (str_contains($padded, ' '.ArabicNormalizer::normalize($keyword).' ')) {
                    return $definition;
                }
            }
        }

        return null;
    }

    /**
     * Runs of lines from one region; without regions (page text), runs of
     * lines on one page up to a blank line.
     *
     * @param  list<array{page: int, region_id: ?int, text: string, confidence: ?int}>  $lines
     * @return list<array{page: int, region_id: ?int, text: string, confidence: ?int}>
     */
    private function paragraphs(array $lines): array
    {
        $paragraphs = [];
        $current = null;
        foreach ($lines as $line) {
            $text = trim($line['text']);
            if ($text === '') {
                if ($current !== null) {
                    $paragraphs[] = $current;
                }
                $current = null;

                continue;
            }
            if ($current !== null && $current['page'] === $line['page'] && $current['region_id'] === $line['region_id']) {
                $current['text'] .= ' '.$text;

                continue;
            }
            if ($current !== null) {
                $paragraphs[] = $current;
            }
            $current = [...$line, 'text' => $text];
        }
        if ($current !== null) {
            $paragraphs[] = $current;
        }

        return $paragraphs;
    }

    /**
     * Every word present as a whole word, allowing Arabic's attached "و" and "ال".
     *
     * @param  list<string>  $words
     */
    private function containsAllWords(string $padded, array $words): bool
    {
        foreach ($words as $word) {
            $w = ArabicNormalizer::normalize($word);
            $present = false;
            foreach ([$w, "و{$w}", "ال{$w}", "وال{$w}"] as $form) {
                $present = $present || str_contains($padded, " {$form} ");
            }
            if (! $present) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function splitItems(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[،,؛;]/u', $value) ?: []), fn (string $item) => $item !== ''));
    }

    private function stripBullet(string $text): string
    {
        return trim((string) preg_replace('/^\s*(?:[\-–—•*·▪◦●]+|[\d٠-٩]{1,3}\s*[.\)\-–]|\(?[\d٠-٩]{1,3}\))\s*/u', '', $text));
    }

    /**
     * @param  array{page: int, region_id: ?int, text: string, confidence: ?int}  $line
     * @return array{value: ?string, confidence: int, page: ?int, region_id: ?int, form_field_id: ?int, method: string, rule: string, original: ?string, crop_path: ?string}
     */
    private function fromLine(array $line, string $value, string $rule, int $confidence): array
    {
        return [
            'value' => $value,
            'confidence' => $confidence,
            'page' => $line['page'],
            'region_id' => $line['region_id'],
            'form_field_id' => null,
            'method' => ExtractionMethod::OcrDerived->value,
            'rule' => $rule,
            'original' => $line['text'],
            'crop_path' => null,
        ];
    }
}

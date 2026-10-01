<?php

namespace App\Support\Ocr;

use App\Enums\DateCalendar;
use App\Enums\DocumentType;
use App\Enums\ExtractedDateType;
use App\Support\ArabicDigits;
use App\Support\ArabicNormalizer;
use App\Support\Ocr\Extraction\DocumentFieldSchema;
use App\Support\Ocr\Extraction\FieldDefinition;

/**
 * Finds dates and gives each a semantic role from the words just before it.
 *
 * Three shapes: numeric (d/m/Y or Y/m/d, with / - or .), a month name with a
 * year ("12 رجب 1445", "3 March 2024", "آذار 1987"), and a bare year only
 * when a role word anchors it ("ولد عام 1945م"). Arabic-Indic and Persian
 * digits are read as digits; the stored value is always the text as it
 * appears, and `normalized` is Y[-m[-d]] in the date's *own* calendar.
 *
 * Deliberately never converts between calendars or collapses a
 * Hijri/Gregorian pair into one date: a Saudi letter's dual-dated header is
 * two distinct facts about the same document, not one fact expressed twice.
 */
class DateExtractor
{
    /** Numeric d/m/Y (header dates) and Y/m/d (handwritten signature dates), one separator used throughout. */
    private const NUMERIC_PATTERN = '/(?<!\d)(\d{1,4})([\/\-.])(\d{1,2})\2(\d{1,4})(?!\d)/u';

    private const YEAR_PATTERN = '/(?<!\d)(\d{4})(?!\d)\s*(هـ|ه|م)?/u';

    private const HIJRI_YEAR_MIN = 1300;

    private const HIJRI_YEAR_MAX = 1500;

    private const GREGORIAN_YEAR_MIN = 1900;

    /** Named-month and role-anchored years reach further back: artists' birth years start in the 1800s. */
    private const GREGORIAN_ANCHORED_YEAR_MIN = 1800;

    private const GREGORIAN_YEAR_MAX = 2099;

    /** How far before a date its role word may be. */
    private const CONTEXT_CHARS = 40;

    /** How far after a role word a bare year may be, within the same sentence. */
    private const ANCHOR_LOOKAHEAD_CHARS = 50;

    /** Month name => [month number, calendar]; spelling variants listed separately, longer names first. */
    private const MONTHS = [
        'ربيع الأول' => [3, 'hijri'], 'ربيع الاول' => [3, 'hijri'], 'ربيع الآخر' => [4, 'hijri'], 'ربيع الاخر' => [4, 'hijri'], 'ربيع الثاني' => [4, 'hijri'],
        'جمادى الأولى' => [5, 'hijri'], 'جمادى الاولى' => [5, 'hijri'], 'جمادى الآخرة' => [6, 'hijri'], 'جمادى الاخرة' => [6, 'hijri'], 'جمادى الثانية' => [6, 'hijri'],
        'ذو القعدة' => [11, 'hijri'], 'ذي القعدة' => [11, 'hijri'], 'ذو الحجة' => [12, 'hijri'], 'ذي الحجة' => [12, 'hijri'],
        'محرم' => [1, 'hijri'], 'صفر' => [2, 'hijri'], 'رجب' => [7, 'hijri'], 'شعبان' => [8, 'hijri'], 'رمضان' => [9, 'hijri'], 'شوال' => [10, 'hijri'],
        'كانون الثاني' => [1, 'gregorian'], 'تشرين الأول' => [10, 'gregorian'], 'تشرين الاول' => [10, 'gregorian'], 'تشرين الثاني' => [11, 'gregorian'],
        'كانون الأول' => [12, 'gregorian'], 'كانون الاول' => [12, 'gregorian'], 'شباط' => [2, 'gregorian'], 'آذار' => [3, 'gregorian'], 'اذار' => [3, 'gregorian'],
        'نيسان' => [4, 'gregorian'], 'أيار' => [5, 'gregorian'], 'ايار' => [5, 'gregorian'], 'حزيران' => [6, 'gregorian'], 'تموز' => [7, 'gregorian'],
        'آب' => [8, 'gregorian'], 'أيلول' => [9, 'gregorian'], 'ايلول' => [9, 'gregorian'],
        'يناير' => [1, 'gregorian'], 'فبراير' => [2, 'gregorian'], 'مارس' => [3, 'gregorian'], 'أبريل' => [4, 'gregorian'], 'ابريل' => [4, 'gregorian'], 'إبريل' => [4, 'gregorian'],
        'مايو' => [5, 'gregorian'], 'يونيو' => [6, 'gregorian'], 'يوليو' => [7, 'gregorian'], 'أغسطس' => [8, 'gregorian'], 'اغسطس' => [8, 'gregorian'],
        'سبتمبر' => [9, 'gregorian'], 'أكتوبر' => [10, 'gregorian'], 'اكتوبر' => [10, 'gregorian'], 'نوفمبر' => [11, 'gregorian'], 'ديسمبر' => [12, 'gregorian'],
        'january' => [1, 'gregorian'], 'february' => [2, 'gregorian'], 'march' => [3, 'gregorian'], 'april' => [4, 'gregorian'], 'may' => [5, 'gregorian'],
        'june' => [6, 'gregorian'], 'july' => [7, 'gregorian'], 'august' => [8, 'gregorian'], 'september' => [9, 'gregorian'], 'october' => [10, 'gregorian'],
        'november' => [11, 'gregorian'], 'december' => [12, 'gregorian'],
        'jan' => [1, 'gregorian'], 'feb' => [2, 'gregorian'], 'mar' => [3, 'gregorian'], 'apr' => [4, 'gregorian'], 'jun' => [6, 'gregorian'], 'jul' => [7, 'gregorian'],
        'aug' => [8, 'gregorian'], 'sept' => [9, 'gregorian'], 'sep' => [9, 'gregorian'], 'oct' => [10, 'gregorian'], 'nov' => [11, 'gregorian'], 'dec' => [12, 'gregorian'],
    ];

    /**
     * Role words: the one nearest before a date decides its role. A role
     * limited to some document types only applies there — "حتى" means a
     * closing date on an exhibition invitation, and nothing on a letter.
     *
     * @var list<array{0: list<string>, 1: ExtractedDateType, 2: string, 3: list<DocumentType>|null}>
     */
    private const ROLES = [
        [['توقيع', 'التوقيع', 'signature', 'signed'], ExtractedDateType::SignatureDate, 'signature_date', null],
        [['ولد', 'ولدت', 'مواليد', 'الميلاد', 'born'], ExtractedDateType::BirthDate, 'birth_date', null],
        [['توفي', 'توفيت', 'الوفاة', 'وفاة', 'died'], ExtractedDateType::DeathDate, 'death_date', null],
        [['النشر', 'نشر', 'صدر', 'published', 'publication'], ExtractedDateType::PublicationDate, 'publication_date', null],
        [['الافتتاح', 'افتتاح', 'يفتتح', 'opening', 'opens'], ExtractedDateType::EventDate, 'opening_date', [DocumentType::ExhibitionDocument]],
        [['الختام', 'يختتم', 'حتى', 'closing', 'closes', 'until'], ExtractedDateType::EventDate, 'closing_date', [DocumentType::ExhibitionDocument]],
        [['تاريخ العمل', 'تاريخ الإنجاز', 'تاريخ الإنتاج', 'created', 'executed'], ExtractedDateType::ArtworkDate, 'artwork_date', [DocumentType::ArtworkConditionReport]],
        [['تاريخ التقرير', 'تاريخ الفحص', 'report date', 'inspection date'], ExtractedDateType::DocumentIssueDate, 'report_date', [DocumentType::ArtworkConditionReport]],
    ];

    /**
     * @param  array<string, array<int, array{text: string, confidence: int}>>  $pagesByLanguage  language => [page_number => ['text' => ..., ...]]
     * @return array<int, array{value: string, calendar: DateCalendar, date_type: ExtractedDateType, source_page: ?int, field_key: ?string, normalized: ?string, context: string}>
     */
    public function extract(array $pagesByLanguage, DocumentType $type = DocumentType::Unknown): array
    {
        $roles = array_values(array_filter(self::ROLES, fn (array $role) => $role[3] === null || in_array($type, $role[3], true)));
        $seen = [];
        $dates = [];

        foreach (['ar', 'en'] as $lang) {
            foreach ($pagesByLanguage[$lang] ?? [] as $pageNumber => $page) {
                foreach ($this->datesIn($page['text'], $roles, $type) as $date) {
                    $dedupeKey = $pageNumber.'|'.($date['normalized'] ?? ArabicDigits::toAscii($date['value']));
                    if (isset($seen[$dedupeKey])) {
                        continue;
                    }
                    $seen[$dedupeKey] = true;
                    $dates[] = [...$date, 'source_page' => (int) $pageNumber];
                }
            }
        }

        return $dates;
    }

    /**
     * @param  list<array{0: list<string>, 1: ExtractedDateType, 2: string, 3: list<DocumentType>|null}>  $roles
     * @return list<array{value: string, calendar: DateCalendar, date_type: ExtractedDateType, field_key: ?string, normalized: ?string, context: string}>
     */
    private function datesIn(string $text, array $roles, DocumentType $type): array
    {
        // Same length in characters as $text: Arabic-Indic digits are one character each, as are ASCII ones.
        $ascii = ArabicDigits::toAscii($text);
        $found = [];
        $taken = [];

        foreach ($this->matches(self::NUMERIC_PATTERN, $ascii) as [$start, $length, $groups]) {
            $parsed = $this->numeric($groups);
            if ($parsed !== null) {
                $found[] = [$start, $length, ...$parsed, null];
                $taken[] = [$start, $start + $length];
            }
        }

        foreach ($this->matches($this->monthPattern(), $ascii) as [$start, $length, $groups]) {
            $parsed = $this->named($groups);
            if ($parsed !== null && ! $this->overlaps($start, $length, $taken)) {
                $found[] = [$start, $length, ...$parsed, null];
                $taken[] = [$start, $start + $length];
            }
        }

        foreach ($this->anchoredYears($ascii, $roles, $taken) as $anchored) {
            $found[] = $anchored;
        }

        usort($found, fn ($a, $b) => $a[0] <=> $b[0]);

        $dates = [];
        foreach ($found as [$start, $length, $calendar, $normalized, $anchorRole]) {
            $context = mb_substr($text, max(0, $start - self::CONTEXT_CHARS), min($start, self::CONTEXT_CHARS));
            [$role, $fieldKey] = $anchorRole ?? $this->roleFor($context, $roles, $type);
            $field = $fieldKey === null ? null : DocumentFieldSchema::field($type, $fieldKey);

            $dates[] = [
                'value' => mb_substr($text, $start, $length),
                'calendar' => $calendar,
                'date_type' => $role,
                'field_key' => $field?->kind === FieldDefinition::KIND_DATE ? $fieldKey : null,
                'normalized' => $normalized,
                'context' => trim($context),
            ];
        }

        return $dates;
    }

    /**
     * Bare years right after a role word: "ولد عام 1945م", "توفي سنة ١٤٠٥هـ".
     *
     * @param  list<array{0: list<string>, 1: ExtractedDateType, 2: string, 3: list<DocumentType>|null}>  $roles
     * @param  list<array{0: int, 1: int}>  $taken
     * @return list<array{0: int, 1: int, 2: DateCalendar, 3: string, 4: array{0: ExtractedDateType, 1: string}}>
     */
    private function anchoredYears(string $ascii, array $roles, array $taken): array
    {
        $years = [];
        foreach ($roles as [$keywords, $role, $fieldKey]) {
            foreach ($keywords as $keyword) {
                // Arabic joins "and"/"then" onto the next word: "وتوفي" is "توفي".
                $pattern = '/(?<![\p{L}\p{N}])(?:و|ف)?'.preg_quote($keyword, '/').'(?![\p{L}\p{N}])/iu';
                foreach ($this->matches($pattern, $ascii) as [$start, $length]) {
                    $after = $start + $length;
                    $window = mb_substr($ascii, $after, self::ANCHOR_LOOKAHEAD_CHARS);
                    $sentence = preg_split('/[.\n؛;]/u', $window);
                    $window = $sentence === false ? $window : $sentence[0];
                    if (preg_match(self::YEAR_PATTERN, $window, $m, PREG_OFFSET_CAPTURE) !== 1) {
                        continue;
                    }
                    $yearStart = $after + mb_strlen(substr($window, 0, $m[0][1]));
                    $yearLength = mb_strlen(rtrim($m[0][0]));
                    $calendar = $this->anchoredCalendar((int) $m[1][0], $m[2][0] ?? '');
                    if ($calendar === null || $this->overlaps($yearStart, $yearLength, $taken)) {
                        continue;
                    }
                    $taken[] = [$yearStart, $yearStart + $yearLength];
                    $years[] = [$yearStart, $yearLength, $calendar, $m[1][0], [$role, $fieldKey]];
                }
            }
        }

        return $years;
    }

    /**
     * @param  array<int, string>  $groups
     * @return array{0: DateCalendar, 1: ?string}|null
     */
    private function numeric(array $groups): ?array
    {
        [$first, , $middle, $last] = [$groups[1], $groups[2], $groups[3], $groups[4]];
        $yearFirst = strlen($first) >= 3;
        if (! $yearFirst && strlen($last) < 3) {
            return null;
        }
        $year = (int) ($yearFirst ? $first : $last);
        $calendar = $this->calendarFor($year, self::GREGORIAN_YEAR_MIN);
        if ($calendar === null) {
            return null;
        }

        $day = (int) ($yearFirst ? $last : $first);

        return [$calendar, $this->ymd($year, (int) $middle, $day, $calendar)];
    }

    /**
     * @param  array<int, string>  $groups
     * @return array{0: DateCalendar, 1: ?string}|null
     */
    private function named(array $groups): ?array
    {
        [$month, $calendar] = self::MONTHS[mb_strtolower($groups[2])] ?? [null, null];
        if ($month === null) {
            return null;
        }
        $year = (int) $groups[3];
        $expected = $calendar === 'hijri' ? DateCalendar::Hijri : DateCalendar::Gregorian;
        if ($this->calendarFor($year, self::GREGORIAN_ANCHORED_YEAR_MIN) !== $expected) {
            return null;
        }

        return [$expected, $groups[1] !== '' ? $this->ymd($year, $month, (int) $groups[1], $expected) : sprintf('%04d-%02d', $year, $month)];
    }

    private function monthPattern(): string
    {
        $names = implode('|', array_map(fn (string $name) => preg_quote($name, '/'), array_keys(self::MONTHS)));

        return '/(?:(?<!\d)(\d{1,2})\s+)?(?<![\p{L}])('.$names.')\.?(?![\p{L}])\s*[،,]?\s*(\d{4})(?!\d)/iu';
    }

    /**
     * The role word ending nearest the date wins.
     *
     * @param  list<array{0: list<string>, 1: ExtractedDateType, 2: string, 3: list<DocumentType>|null}>  $roles
     * @return array{0: ExtractedDateType, 1: ?string}
     */
    private function roleFor(string $context, array $roles, DocumentType $type): array
    {
        $padded = ' '.ArabicNormalizer::normalize($context).' ';
        $best = null;
        $bestEnd = -1;
        foreach ($roles as [$keywords, $role, $fieldKey]) {
            foreach ($keywords as $keyword) {
                $word = ArabicNormalizer::normalize($keyword);
                foreach (["{$word} ", "و{$word} ", "ف{$word} "] as $needle) {
                    $at = mb_strrpos($padded, ' '.$needle);
                    if ($at !== false && $at + mb_strlen($needle) + 1 > $bestEnd) {
                        $bestEnd = $at + mb_strlen($needle) + 1;
                        $best = [$role, $fieldKey];
                    }
                }
            }
        }

        return $best ?? $this->defaultRole($type);
    }

    /**
     * A date with no role word: on a letter or report the undated header date
     * is when it was issued; in a biography or an invitation it could be
     * anything.
     *
     * @return array{0: ExtractedDateType, 1: ?string}
     */
    private function defaultRole(DocumentType $type): array
    {
        return match ($type) {
            DocumentType::ArtworkConditionReport => [ExtractedDateType::DocumentIssueDate, 'report_date'],
            DocumentType::ArtistBiography, DocumentType::ExhibitionDocument => [ExtractedDateType::Other, null],
            default => [ExtractedDateType::DocumentIssueDate, 'document_issue_date'],
        };
    }

    private function calendarFor(int $year, int $gregorianMin): ?DateCalendar
    {
        if ($year >= self::HIJRI_YEAR_MIN && $year <= self::HIJRI_YEAR_MAX) {
            return DateCalendar::Hijri;
        }
        if ($year >= $gregorianMin && $year <= self::GREGORIAN_YEAR_MAX) {
            return DateCalendar::Gregorian;
        }

        return null;
    }

    /** A bare year's calendar: its suffix says, when there is one (هـ Hijri, م Gregorian); otherwise its range. */
    private function anchoredCalendar(int $year, string $suffix): ?DateCalendar
    {
        $calendar = $this->calendarFor($year, self::GREGORIAN_ANCHORED_YEAR_MIN);
        $claimed = match ($suffix) {
            'هـ', 'ه' => DateCalendar::Hijri,
            'م' => DateCalendar::Gregorian,
            default => null,
        };

        return $claimed === null || $claimed === $calendar ? $calendar : null;
    }

    /** Y-m-d in the date's own calendar, or null when the month or day can't be right. */
    private function ymd(int $year, int $month, int $day, DateCalendar $calendar): ?string
    {
        $maxDay = $calendar === DateCalendar::Hijri ? 30 : 31;

        return $month >= 1 && $month <= 12 && $day >= 1 && $day <= $maxDay ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
    }

    /**
     * Matches as character offsets (preg reports bytes).
     *
     * @return list<array{0: int, 1: int, 2: array<int, string>}>
     */
    private function matches(string $pattern, string $subject): array
    {
        if (preg_match_all($pattern, $subject, $all, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === false) {
            return [];
        }

        return array_map(fn (array $m) => [
            mb_strlen(substr($subject, 0, $m[0][1])),
            mb_strlen($m[0][0]),
            array_map(fn ($group) => $group[0], $m),
        ], $all);
    }

    /**
     * @param  list<array{0: int, 1: int}>  $taken
     */
    private function overlaps(int $start, int $length, array $taken): bool
    {
        foreach ($taken as [$from, $to]) {
            if ($start < $to && $start + $length > $from) {
                return true;
            }
        }

        return false;
    }
}

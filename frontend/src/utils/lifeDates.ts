import type { PartialDate } from "@/types/artist";

export interface FormattedLifeDates {
  /** The rendered date string, always in Western digits. */
  text: string;
  /**
   * The verbatim source display text, present only when it differs from the
   * rendered `text` (used for the "as recorded" tooltip).
   */
  asRecorded: string | null;
}

const LABELS = {
  ar: { circa: "نحو", hijri: "هـ" },
  en: { circa: "c.", hijri: "AH" },
} as const;

export type LifeDatesLocale = keyof typeof LABELS;

function structuredYears(date: PartialDate, locale: LifeDatesLocale): string | null {
  const from = date.year_from;
  const to = date.year_to;
  if (from === null && to === null) return null;

  const labels = LABELS[locale];
  const parts: string[] = [];
  if (date.certainty === "circa") parts.push(labels.circa);

  if (from !== null && to !== null && from !== to) {
    parts.push(`${from}–${to}`);
  } else {
    parts.push(String(from ?? to));
  }

  if (date.calendar === "hijri") parts.push(labels.hijri);
  return parts.join(" ");
}

/**
 * Renders a partial date for display. Structured years win over free-text
 * display; numbers are always emitted as Western digits regardless of locale.
 * Returns null when nothing at all is known about the date.
 */
export function formatLifeDates(
  date: PartialDate | null | undefined,
  locale: LifeDatesLocale,
): FormattedLifeDates | null {
  if (!date) return null;

  const display = date.display?.trim() ? date.display.trim() : null;

  const years = structuredYears(date, locale);
  if (years !== null) {
    return {
      text: years,
      asRecorded: display !== null && display !== years ? display : null,
    };
  }

  if (display !== null) return { text: display, asRecorded: null };
  return null;
}

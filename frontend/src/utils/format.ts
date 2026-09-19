import type { AppLocale } from "@/i18n";

/** Both locales render with Western digits for consistency. */
const NUMBERING_SYSTEM = "latn";

export function formatDateTime(iso: string, locale: AppLocale): string {
  return new Intl.DateTimeFormat(locale, {
    dateStyle: "medium",
    timeStyle: "short",
    numberingSystem: NUMBERING_SYSTEM,
  }).format(new Date(iso));
}

export function formatRelativeTime(
  iso: string,
  locale: AppLocale,
  now: Date = new Date(),
): string {
  const then = new Date(iso).getTime();
  const diffSeconds = Math.round((then - now.getTime()) / 1000);
  const rtf = new Intl.RelativeTimeFormat(locale, { numeric: "auto" });
  const divisions: Array<{
    amount: number;
    unit: Intl.RelativeTimeFormatUnit;
  }> = [
    { amount: 60, unit: "second" },
    { amount: 60, unit: "minute" },
    { amount: 24, unit: "hour" },
    { amount: 7, unit: "day" },
    { amount: 4.34524, unit: "week" },
    { amount: 12, unit: "month" },
    { amount: Number.POSITIVE_INFINITY, unit: "year" },
  ];
  let duration = diffSeconds;
  for (const division of divisions) {
    if (Math.abs(duration) < division.amount) {
      return rtf.format(Math.round(duration), division.unit);
    }
    duration /= division.amount;
  }
  return rtf.format(Math.round(duration), "year");
}

/** Stringifies a change value: objects become JSON, null/undefined stay null. */
export function formatFieldValue(value: unknown): string | null {
  if (value === null || value === undefined || value === "") return null;
  if (typeof value === "object") return JSON.stringify(value);
  return String(value);
}

/** Both locales render with Western digits for consistency. */
export function formatNumber(value: number, locale: AppLocale): string {
  return new Intl.NumberFormat(locale, {
    numberingSystem: NUMBERING_SYSTEM,
  }).format(value);
}

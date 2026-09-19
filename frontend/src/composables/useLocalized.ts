import { useI18n } from "vue-i18n";

export interface LocalizedValue {
  ar: string | null;
  en: string | null;
}

export interface PickedLocalized {
  text: string;
  lang: "ar" | "en";
  dir: "rtl" | "ltr";
  isFallback: boolean;
}

const DIRS = { ar: "rtl", en: "ltr" } as const;

function nonEmpty(value: string | null | undefined): string | null {
  return typeof value === "string" && value.trim() !== "" ? value : null;
}

export function pickLocalized(
  value: LocalizedValue | null | undefined,
  locale: "ar" | "en",
): PickedLocalized | null {
  if (!value) return null;

  const current = nonEmpty(value[locale]);
  if (current !== null) {
    return {
      text: current,
      lang: locale,
      dir: DIRS[locale],
      isFallback: false,
    };
  }

  const otherLocale = locale === "ar" ? "en" : "ar";
  const other = nonEmpty(value[otherLocale]);
  if (other !== null) {
    return {
      text: other,
      lang: otherLocale,
      dir: DIRS[otherLocale],
      isFallback: true,
    };
  }

  return null;
}

export function useLocalized() {
  const { locale } = useI18n();

  function pick(
    value: LocalizedValue | null | undefined,
  ): PickedLocalized | null {
    return pickLocalized(value, locale.value as "ar" | "en");
  }

  return { pick };
}

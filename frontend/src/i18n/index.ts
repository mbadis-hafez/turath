import { createI18n } from "vue-i18n";

import arActivity from "./locales/ar/activity.json";
import arAuth from "./locales/ar/auth.json";
import arCommon from "./locales/ar/common.json";
import arErrors from "./locales/ar/errors.json";
import arHome from "./locales/ar/home.json";
import arNav from "./locales/ar/nav.json";
import enActivity from "./locales/en/activity.json";
import enAuth from "./locales/en/auth.json";
import enCommon from "./locales/en/common.json";
import enErrors from "./locales/en/errors.json";
import enHome from "./locales/en/home.json";
import enNav from "./locales/en/nav.json";

export const SUPPORTED_LOCALES = ["ar", "en"] as const;
export type AppLocale = (typeof SUPPORTED_LOCALES)[number];

export const DEFAULT_LOCALE: AppLocale = "ar";
export const LOCALE_STORAGE_KEY = "locale";

export function isLocale(value: unknown): value is AppLocale {
  return (
    typeof value === "string" &&
    (SUPPORTED_LOCALES as readonly string[]).includes(value)
  );
}

export function getStoredLocale(): AppLocale {
  try {
    const stored = localStorage.getItem(LOCALE_STORAGE_KEY);
    if (isLocale(stored)) return stored;
  } catch {
    // storage unavailable (SSR/private mode) — fall through to default
  }
  return DEFAULT_LOCALE;
}

export function setDocumentDirection(locale: AppLocale): void {
  document.documentElement.lang = locale;
  document.documentElement.dir = locale === "ar" ? "rtl" : "ltr";
}

export function applyLocale(locale: AppLocale): void {
  i18n.global.locale.value = locale;
  setDocumentDirection(locale);
  try {
    localStorage.setItem(LOCALE_STORAGE_KEY, locale);
  } catch {
    // ignore storage failures
  }
}

export const i18n = createI18n({
  legacy: false,
  locale: DEFAULT_LOCALE,
  fallbackLocale: "en",
  messages: {
    ar: {
      common: arCommon,
      nav: arNav,
      auth: arAuth,
      errors: arErrors,
      home: arHome,
      activity: arActivity,
    },
    en: {
      common: enCommon,
      nav: enNav,
      auth: enAuth,
      errors: enErrors,
      home: enHome,
      activity: enActivity,
    },
  },
});

export type { AppLocale as Locale };

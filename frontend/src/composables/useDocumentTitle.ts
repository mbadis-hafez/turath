import { watchEffect, ref, type Ref } from "vue";

import { i18n } from "@/i18n";

export const SITE_NAME = "Bidayaat · بدايات";

/**
 * Sets document.title from an i18n key (when one exists) or a literal string,
 * suffixed with the site name. Pass null to leave the title untouched.
 *
 * Uses the global i18n instance directly so it also works outside of setup
 * (e.g. from the router guard).
 */
export function useDocumentTitle(
  titleKeyOrText: Ref<string | null> | string | null,
): void {
  const input =
    typeof titleKeyOrText === "string" ? ref(titleKeyOrText) : titleKeyOrText;
  const { t, te, locale } = i18n.global;

  watchEffect(() => {
    const value = input?.value;
    if (value === null || value === undefined || value === "") return;
    // Track the locale so the title re-renders on language switches.
    const activeLocale = locale.value;
    const text = te(value, activeLocale) ? t(value, activeLocale) : value;
    document.title = text === SITE_NAME ? text : `${text} · ${SITE_NAME}`;
  });
}

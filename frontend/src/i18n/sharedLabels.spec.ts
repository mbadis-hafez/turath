import { describe, expect, it } from "vitest";

import { i18n } from "@/i18n";

const t = (key: string, locale: "en" | "ar") => i18n.global.t(key, {}, { locale });

describe("shared labels", () => {
  it.each([["en", "Year uncertain"], ["ar", "سنة غير مؤكدة"]] as const)(
    "names an unconfirmed year the same way in the artworks registry, the methodology glossary and the shared label (%s)",
    (locale, expected) => {
      const sources = [
        t("common.labels.yearUncertain", locale),
        t("curation.artworkRegistry.flags.year_uncertain", locale),
        t("methodology.labels.yearUncertain.name", locale),
      ];

      expect(sources).toEqual([expected, expected, expected]);
    },
  );
});

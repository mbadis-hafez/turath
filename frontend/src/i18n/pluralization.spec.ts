import { describe, expect, it } from "vitest";

import { i18n } from "@/i18n";

/**
 * vue-i18n's default plural selector (used for every locale without a
 * custom `pluralRules` entry) collapses any count >= 2 into the message's
 * third pipe-segment. A 6-segment message written for Arabic's CLDR
 * categories (zero/one/two/few/many/other) must not leave that segment as a
 * literal "two" — English (and any other non-`ar` locale) would render it
 * for every count from 2 upward. Regression test for that exact bug.
 */
describe("i18n pluralization", () => {
  it("never collapses an English count >= 2 to a literal 'two' form", () => {
    i18n.global.locale.value = "en";
    for (const n of [2, 3, 5, 8, 11, 20, 100]) {
      expect(i18n.global.t("artists.summaryArtist", { count: n }, n)).toContain(
        String(n),
      );
      expect(
        i18n.global.t("artists.summaryMaterial", { count: n }, n),
      ).toContain(String(n));
      expect(
        i18n.global.t("artists.materialsCount", { count: n }, n),
      ).toContain(String(n));
    }
  });

  it("uses the Arabic dual form only for exactly two, and interpolates the count otherwise", () => {
    i18n.global.locale.value = "ar";
    expect(i18n.global.t("artists.summaryArtist", { count: 2 }, 2)).toBe(
      "فنانان",
    );
    for (const n of [3, 5, 8, 11, 20, 100]) {
      expect(i18n.global.t("artists.summaryArtist", { count: n }, n)).toContain(
        String(n),
      );
    }
  });
});

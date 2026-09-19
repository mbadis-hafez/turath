import { readFileSync } from "node:fs";

import { describe, expect, it } from "vitest";

// Read the locale files from disk: @intlify/unplugin-vue-i18n precompiles
// JSON imports into message ASTs at build time, so imports won't give us
// plain strings here.
function loadLocales(name: string): [unknown, unknown] {
  const read = (locale: string) =>
    JSON.parse(
      readFileSync(`src/i18n/locales/${locale}/${name}.json`, "utf-8"),
    );
  return [read("ar"), read("en")];
}

const namespaces = {
  activity: loadLocales("activity"),
  artists: loadLocales("artists"),
  auth: loadLocales("auth"),
  common: loadLocales("common"),
  dates: loadLocales("dates"),
  errors: loadLocales("errors"),
  home: loadLocales("home"),
  nav: loadLocales("nav"),
} as const;

function collectKeys(value: unknown, prefix: string, into: Set<string>): void {
  if (typeof value !== "object" || value === null) {
    into.add(prefix);
    return;
  }
  for (const [key, child] of Object.entries(value)) {
    collectKeys(child, prefix === "" ? key : `${prefix}.${key}`, into);
  }
}

describe("i18n locale parity", () => {
  for (const [namespace, [ar, en]] of Object.entries(namespaces)) {
    it(`${namespace}: ar and en expose identical key sets`, () => {
      const arKeys = new Set<string>();
      const enKeys = new Set<string>();
      collectKeys(ar, "", arKeys);
      collectKeys(en, "", enKeys);

      const missingInEn = [...arKeys].filter((key) => !enKeys.has(key));
      const missingInAr = [...enKeys].filter((key) => !arKeys.has(key));

      expect(missingInEn).toEqual([]);
      expect(missingInAr).toEqual([]);
    });
  }

  it("contains the exact starter keys required by the spec", () => {
    const required: Record<string, string[]> = {
      nav: ["home", "login", "logout", "artists", "activity"],
      common: [
        "language",
        "search",
        "loading",
        "retry",
        "previous",
        "next",
        "shownInOtherLanguage",
      ],
      errors: ["generic", "notFound", "forbidden", "network", "throttled"],
      auth: ["email", "password", "invalid"],
      activity: [
        "title",
        "filterByType",
        "filterByUser",
        "noEntries",
        "changedFrom",
        "changedTo",
        "editSummary",
        "system",
        "event.created",
        "event.updated",
        "event.deleted",
        "event.restored",
      ],
      artists: [
        "title",
        "searchPlaceholder",
        "sortBy",
        "sort.name",
        "sort.recent",
        "verifiedOnly",
        "verified",
        "unverified",
        "disputed",
        "verifiedHelp",
        "noResults",
        "clearFilters",
        "born",
        "died",
        "alsoKnownAs",
        "biography",
        "noBio",
        "overview",
      ],
      dates: ["circa", "hijri", "asRecorded"],
    };

    for (const [namespace, [ar, en]] of Object.entries(namespaces)) {
      const keys = required[namespace] ?? [];
      for (const key of keys) {
        const path = key.split(".");
        let arValue: unknown = ar;
        let enValue: unknown = en;
        for (const segment of path) {
          arValue = (arValue as Record<string, unknown>)[segment];
          enValue = (enValue as Record<string, unknown>)[segment];
        }
        expect(typeof arValue, `${namespace}.${key} (ar)`).toBe("string");
        expect(typeof enValue, `${namespace}.${key} (en)`).toBe("string");
        expect((arValue as string).length).toBeGreaterThan(0);
        expect((enValue as string).length).toBeGreaterThan(0);
      }
    }
  });
});

import { describe, expect, it } from "vitest";

import { parseRegistryQuery } from "@/composables/useAdminArtists";

describe("parseRegistryQuery", () => {
  it("round-trips URL filters and defaults page to 1", () => {
    expect(parseRegistryQuery({ q: "radwi", unverified: "1", owner_type: "gallery", theme_id: "4", priority: "1", city: "Jeddah", page: "3" })).toEqual({
      q: "radwi", unverified: true, city: "Jeddah", ownerType: "gallery", themeId: 4, priority: true, page: 3,
    });
    expect(parseRegistryQuery({})).toMatchObject({ q: "", unverified: false, themeId: null, page: 1 });
  });
});

import { describe, expect, it } from "vitest";

import { parseArtworksQuery } from "@/composables/useAdminArtworks";

describe("parseArtworksQuery", () => {
  it("reads filters from the URL", () => {
    expect(parseArtworksQuery({ q: "oud", status: "draft", missing_dimensions: "1", sort: "title", page: "3" })).toEqual({
      q: "oud", status: "draft", missingDimensions: true, pipelineGap: false, artistId: null, holderId: null, sort: "title", page: 3,
    });
  });

  it("falls back to defaults", () => {
    expect(parseArtworksQuery({ page: "-2" })).toEqual({
      q: "", status: "", missingDimensions: false, pipelineGap: false, artistId: null, holderId: null, sort: "newest", page: 1,
    });
  });

  it("ignores an unknown sort value", () => {
    expect(parseArtworksQuery({ sort: "bogus" }).sort).toBe("newest");
  });
});

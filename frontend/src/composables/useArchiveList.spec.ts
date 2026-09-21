import { describe, expect, it } from "vitest";

import { parseArchiveQuery } from "@/composables/useArchiveList";

describe("parseArchiveQuery", () => {
  it("reads filters and ignores unknown types", () => {
    expect(parseArchiveQuery({ q: "x", type: "poster", status: "all", page: "2" })).toEqual({ q: "x", type: "poster", includeUnpublished: true, artistId: null, page: 2 });
    expect(parseArchiveQuery({ type: "nope", page: "0" })).toEqual({ q: "", type: "", includeUnpublished: false, artistId: null, page: 1 });
  });

  it("reads an artist filter from the URL", () => {
    expect(parseArchiveQuery({ artist_id: "36" }).artistId).toBe(36);
    expect(parseArchiveQuery({ artist_id: "-4" }).artistId).toBeNull();
  });
});

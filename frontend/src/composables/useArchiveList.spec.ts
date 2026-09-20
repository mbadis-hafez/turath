import { describe, expect, it } from "vitest";

import { parseArchiveQuery } from "@/composables/useArchiveList";

describe("parseArchiveQuery", () => {
  it("reads filters and ignores unknown types", () => {
    expect(parseArchiveQuery({ q: "x", type: "poster", status: "all", page: "2" })).toEqual({ q: "x", type: "poster", includeUnpublished: true, page: 2 });
    expect(parseArchiveQuery({ type: "nope", page: "0" })).toEqual({ q: "", type: "", includeUnpublished: false, page: 1 });
  });
});

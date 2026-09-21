import { describe, expect, it } from "vitest";

import { parseArchiveQuery } from "@/composables/useArchiveList";

describe("parseArchiveQuery", () => {
  it("reads every filter, treating a lone value and a repeated one alike", () => {
    expect(parseArchiveQuery({ q: "x", type: ["article", "image"], place: "الرياض", theme: ["3", "5"], access: "full", view: "list", status: "all", artist_id: "36" }))
      .toEqual({ q: "x", types: ["article", "image"], places: ["الرياض"], themeIds: [3, 5], access: ["full"], view: "list", includeUnpublished: true, artistId: 36 });
  });

  it("drops unknown types and access values, bad ids, and defaults to the grid", () => {
    expect(parseArchiveQuery({ type: ["nope", "poster"], theme: ["-1", "x", "2"], access: ["secret", "preview"], artist_id: "-4" }))
      .toEqual({ q: "", types: ["poster"], places: [], themeIds: [2], access: ["preview"], view: "grid", includeUnpublished: false, artistId: null });
  });
});

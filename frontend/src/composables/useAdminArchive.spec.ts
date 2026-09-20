import { describe, expect, it } from "vitest";

import { parseAdminArchiveQuery } from "@/composables/useAdminArchive";

describe("parseAdminArchiveQuery", () => {
  it("reads every filter from the URL", () => {
    expect(parseAdminArchiveQuery({ q: "x", type: "image", status: "incomplete", rights: "unknown", year_from: "1970", year_to: "1980", mine: "1", page: "3" }))
      .toEqual({ q: "x", type: "image", status: "incomplete", rights: "unknown", yearFrom: 1970, yearTo: 1980, mine: true, page: 3 });
  });

  it("drops unknown values", () => {
    expect(parseAdminArchiveQuery({ type: "zzz", status: "zzz", rights: "zzz", year_from: "-1" }))
      .toEqual({ q: "", type: "", status: "", rights: "", yearFrom: null, yearTo: null, mine: false, page: 1 });
  });
});

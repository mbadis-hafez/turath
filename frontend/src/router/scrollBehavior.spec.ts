import { describe, expect, it } from "vitest";
import type { RouteLocationNormalized } from "vue-router";

import { scrollBehavior } from "@/router";

const at = (path: string, hash = ""): RouteLocationNormalized => ({ path, hash, fullPath: path + hash, query: {}, params: {}, matched: [], meta: {}, name: undefined, redirectedFrom: undefined });

describe("scrollBehavior", () => {
  it("scrolls to a section from any page", () => {
    expect(scrollBehavior(at("/en", "#themes"), at("/en/artists"), null)).toMatchObject({ el: "#themes", behavior: "smooth" });
  });

  it("does not move the scroll when only the query changes on the same page", () => {
    expect(scrollBehavior(at("/en/archive"), at("/en/archive"), null)).toBe(false);
  });

  it("starts a new page at the top", () => {
    expect(scrollBehavior(at("/en/artists"), at("/en/archive"), null)).toEqual({ top: 0 });
  });

  it("restores the saved position when going back", () => {
    expect(scrollBehavior(at("/en/archive"), at("/en/artists"), { left: 0, top: 640 })).toEqual({ left: 0, top: 640 });
  });
});

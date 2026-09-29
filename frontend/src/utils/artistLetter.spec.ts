import { describe, expect, it } from "vitest";

import { artistLetter, ARABIC_ALPHABET, ENGLISH_ALPHABET } from "./artistLetter";

describe("artistLetter", () => {
  it("normalizes Arabic alef forms to أ", () => {
    expect(artistLetter("أحمد", "ar")).toBe("أ");
    expect(artistLetter("إبراهيم", "ar")).toBe("أ");
    expect(artistLetter("آمنة", "ar")).toBe("أ");
    expect(artistLetter("احمد", "ar")).toBe("أ");
    expect(artistLetter("ٱلله", "ar")).toBe("أ");
  });

  it("keeps other Arabic letters distinct", () => {
    expect(artistLetter("بدر", "ar")).toBe("ب");
    expect(artistLetter("هند", "ar")).toBe("ه");
    expect(artistLetter("ة bay", "ar")).toBe("ة");
  });

  it("returns null for empty or non-letter Arabic input", () => {
    expect(artistLetter("", "ar")).toBeNull();
    expect(artistLetter(" ", "ar")).toBeNull();
    expect(artistLetter(null, "ar")).toBeNull();
  });

  it("uppercases English first letters", () => {
    expect(artistLetter("alice", "en")).toBe("A");
    expect(artistLetter("Brian", "en")).toBe("B");
    expect(artistLetter("  celine", "en")).toBe("C");
  });

  it("returns null for non-English letters", () => {
    expect(artistLetter("123", "en")).toBeNull();
    expect(artistLetter("", "en")).toBeNull();
  });

  it("exports the full Arabic and English alphabets", () => {
    expect(ARABIC_ALPHABET).toHaveLength(28);
    expect(ENGLISH_ALPHABET).toHaveLength(26);
  });
});

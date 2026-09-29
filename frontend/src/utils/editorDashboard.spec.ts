import { describe, expect, it } from "vitest";

import {
  attentionActionFor,
  greetingPeriod,
  RECORD_ROUTE_NAMES,
} from "@/utils/editorDashboard";
import type { EditorEntityType } from "@/types/editorDashboard";

const at = (hour: number) => new Date(2026, 8, 27, hour, 0, 0);

describe("greetingPeriod", () => {
  it("is morning from 5:00 until noon", () => {
    expect(greetingPeriod(at(5))).toBe("morning");
    expect(greetingPeriod(at(11))).toBe("morning");
  });

  it("is afternoon from noon until 17:00", () => {
    expect(greetingPeriod(at(12))).toBe("afternoon");
    expect(greetingPeriod(at(16))).toBe("afternoon");
  });

  it("is evening outside those hours", () => {
    expect(greetingPeriod(at(17))).toBe("evening");
    expect(greetingPeriod(at(23))).toBe("evening");
    expect(greetingPeriod(at(4))).toBe("evening");
  });
});

describe("attentionActionFor", () => {
  it("maps each attention kind to its primary action", () => {
    expect(attentionActionFor("changes_requested")).toBe("fix");
    expect(attentionActionFor("incomplete")).toBe("complete");
    expect(attentionActionFor("creation_pending")).toBe("continue");
  });
});

describe("RECORD_ROUTE_NAMES", () => {
  it("covers every editor entity type with a manage-gated route", () => {
    const types: EditorEntityType[] = ["artist", "artwork", "event", "archive_item"];
    for (const type of types) {
      expect(RECORD_ROUTE_NAMES[type]).toMatch(/^admin\./);
    }
  });
});

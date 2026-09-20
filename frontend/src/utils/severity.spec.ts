import { describe, expect, it } from "vitest";

import { rowAction, SEVERITY_BAR_CLASS } from "@/utils/severity";

describe("severity", () => {
  it("maps each severity to one distinct color per D56", () => {
    expect(SEVERITY_BAR_CLASS.blocking).toBe("bg-danger");
    expect(SEVERITY_BAR_CLASS.conflict).toBe("bg-warn");
    expect(SEVERITY_BAR_CLASS.minor).toBe("bg-info");
    expect(SEVERITY_BAR_CLASS.pending_review).toBe("bg-info");
    expect(SEVERITY_BAR_CLASS.clear).toBe("bg-success");
  });

  it("chooses the primary action from state", () => {
    expect(rowAction("blocking")).toBe("complete");
    expect(rowAction("minor")).toBe("complete");
    expect(rowAction("conflict")).toBe("resolve");
    expect(rowAction("pending_review")).toBe("follow");
    expect(rowAction("clear")).toBe("none");
  });
});

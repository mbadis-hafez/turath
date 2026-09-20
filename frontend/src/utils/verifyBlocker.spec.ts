import { describe, expect, it } from "vitest";

import { firstBlockReason } from "@/utils/verifyBlocker";

describe("firstBlockReason", () => {
  it("returns the first message in API order, or null when nothing blocks", () => {
    expect(firstBlockReason({ "data.primary_source": ["Missing required field: Primary source."], "pipeline.authorization_letter": ["Authorization letter is not_started."] })).toBe("Missing required field: Primary source.");
    expect(firstBlockReason({})).toBeNull();
  });
});

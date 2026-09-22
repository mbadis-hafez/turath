import { describe, expect, it } from "vitest";

import { canReviewProposals } from "@/utils/permissions";

const can = (permissions: string[]) => (permission: string) => permissions.includes(permission);

describe("canReviewProposals", () => {
  it("is true for a record manager", () => {
    expect(canReviewProposals(can(["artists.manage"]))).toBe(true);
  });

  it("is true for a reviewer holding any review_queue permission", () => {
    expect(canReviewProposals(can(["review_queue.data_audit"]))).toBe(true);
  });

  it("is false for a contributor who can only submit", () => {
    expect(canReviewProposals(can(["proposals.submit"]))).toBe(false);
  });

  it("is false with no permissions at all", () => {
    expect(canReviewProposals(can([]))).toBe(false);
  });
});

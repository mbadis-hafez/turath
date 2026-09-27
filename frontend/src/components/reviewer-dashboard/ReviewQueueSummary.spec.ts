import { describe, expect, it } from "vitest";

import ReviewQueueSummary from "@/components/reviewer-dashboard/ReviewQueueSummary.vue";
import { mountWithPlugins } from "@/test/utils";
import type { ReviewQueueSummary as Summary } from "@/types/reviewerDashboard";

const summary: Summary = {
  waiting_review: 18,
  verification_issues: 7,
  changes_returned: 4,
  reviewed_today: 12,
};

describe("ReviewQueueSummary", () => {
  it("renders the four queue counts", () => {
    const wrapper = mountWithPlugins(ReviewQueueSummary, { locale: "en", props: { summary } });
    const text = wrapper.get("[data-testid=review-queue-summary]").text();

    expect(text).toContain("18");
    expect(text).toContain("7");
    expect(text).toContain("4");
    expect(text).toContain("12");
  });
});

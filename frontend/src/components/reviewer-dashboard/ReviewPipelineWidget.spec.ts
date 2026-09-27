import { describe, expect, it } from "vitest";

import ReviewPipelineWidget from "@/components/reviewer-dashboard/ReviewPipelineWidget.vue";
import { mountWithPlugins } from "@/test/utils";
import type { ReviewPipeline } from "@/types/reviewerDashboard";

const pipeline: ReviewPipeline = {
  ready_for_review: 18,
  changes_requested: 4,
  approved: 9,
  rejected: 1,
};

describe("ReviewPipelineWidget", () => {
  it("lists all four stages with their counts", () => {
    const wrapper = mountWithPlugins(ReviewPipelineWidget, { locale: "en", props: { pipeline } });
    const rows = wrapper.findAll("[data-testid=review-pipeline] li");

    expect(rows).toHaveLength(4);
    expect(wrapper.get("[data-testid=review-pipeline]").text()).toContain("Ready for review");
    expect(wrapper.get("[data-testid=review-pipeline]").text()).toContain("18");
  });

  it("highlights changes_requested when it has outstanding items", () => {
    const wrapper = mountWithPlugins(ReviewPipelineWidget, { locale: "en", props: { pipeline } });
    const rows = wrapper.findAll("[data-testid=review-pipeline] li");

    expect(rows[1]?.classes()).toContain("bg-warn-soft");
  });

  it("never renders a published/publication stage — that stays an admin concern", () => {
    const wrapper = mountWithPlugins(ReviewPipelineWidget, { locale: "en", props: { pipeline } });

    expect(wrapper.text()).not.toContain("Published");
  });
});

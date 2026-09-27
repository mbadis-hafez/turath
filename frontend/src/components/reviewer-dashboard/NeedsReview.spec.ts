import { beforeEach, describe, expect, it } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import NeedsReview from "@/components/reviewer-dashboard/NeedsReview.vue";
import { mountWithPlugins } from "@/test/utils";
import type { NeedsReviewItem } from "@/types/reviewerDashboard";

function makeItem(patch: Partial<NeedsReviewItem> = {}): NeedsReviewItem {
  return {
    id: "rq1",
    citable_type: "artists",
    citable_id: 13,
    review_type: "archivist_review",
    title: { ar: "أحمد الفلان", en: "Ahmad Al-Fulan" },
    note: null,
    status: "pending",
    submitted_by: { id: 9, name: "Fatima" },
    submitted_at: "2026-09-24T10:00:00Z",
    completeness_pct: 100,
    severity: "clear",
    verification_status: "unverified",
    ...patch,
  };
}

let router: Router;

beforeEach(async () => {
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale/proposals", name: "proposals", component: { template: "<div />" } },
      { path: "/:locale/admin/materials", name: "admin.materials", component: { template: "<div />" } },
    ],
  });
  await router.push("/en/proposals");
  await router.isReady();
});

describe("NeedsReview", () => {
  it("shows the empty state when nothing is pending", () => {
    const wrapper = mountWithPlugins(NeedsReview, { locale: "en", router, props: { items: [] } });

    expect(wrapper.find("[data-testid=needs-review-empty]").exists()).toBe(true);
    expect(wrapper.find("[data-testid=needs-review-list]").exists()).toBe(false);
  });

  it("lists a pending item with its title, submitter and a review action", () => {
    const wrapper = mountWithPlugins(NeedsReview, {
      locale: "en",
      router,
      props: { items: [makeItem()] },
    });

    const text = wrapper.get("[data-testid=needs-review-list]").text();
    expect(text).toContain("Ahmad Al-Fulan");
    expect(text).toContain("Fatima");
    const action = wrapper.get("[data-testid=needs-review-action]");
    expect(action.text()).toBe("Review");
  });

  it("never labels a record incomplete when completeness is 100% — that's a verification concern", () => {
    const wrapper = mountWithPlugins(NeedsReview, {
      locale: "en",
      router,
      props: { items: [makeItem({ completeness_pct: 100, severity: "clear" })] },
    });

    expect(wrapper.text()).not.toContain("Incomplete");
  });
});

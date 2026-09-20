import { flushPromises } from "@vue/test-utils";
import { beforeEach, describe, expect, it, vi } from "vitest";

import CompletionSidebar from "@/components/dashboard/CompletionSidebar.vue";
import { mountWithPlugins } from "@/test/utils";
import type { DashboardStats } from "@/types/completeness";

const api = vi.hoisted(() => ({ listReviewQueue: vi.fn(), acknowledgeReviewItem: vi.fn() }));
vi.mock("@/api/dashboard", () => api);

const stats: DashboardStats = {
  user: null,
  total_records: 5,
  avg_completeness_pct: 70,
  blocking_record_count: 2,
  conflict_count: 1,
  missing_field_count: 4,
  computed_at: null,
  by_entity_type: { artist: { pct: 82, note_key: "primary_source", count: 3, of: 5 } },
};

beforeEach(() => {
  api.listReviewQueue.mockReset().mockResolvedValue({
    data: [{ id: "q1", citable_type: "artists", citable_id: 1, review_type: "second_source_needed", title: { ar: null, en: "Taha Al-Sabban" }, note: "needs a 2nd source", submitted_at: new Date(Date.now() - 6 * 86400000).toISOString() }],
  });
  api.acknowledgeReviewItem.mockReset().mockResolvedValue({});
});

describe("CompletionSidebar", () => {
  it("renders per-type progress with its contextual note and the review queue", async () => {
    const wrapper = mountWithPlugins(CompletionSidebar, { locale: "en", props: { stats } });
    await flushPromises();

    expect(wrapper.text()).toContain("82%");
    expect(wrapper.text()).toContain("3 of 5 need attention");
    expect(wrapper.text()).toContain("Primary source");
    expect(wrapper.text()).toContain("Taha Al-Sabban");
    expect(wrapper.text()).toContain("6 days ago");
    expect(wrapper.text()).toContain("Second source needed");
  });

  it("acknowledges an item and removes it from the queue", async () => {
    const wrapper = mountWithPlugins(CompletionSidebar, { locale: "en", props: { stats } });
    await flushPromises();

    await wrapper.get("[data-testid=review-queue] button").trigger("click");
    await flushPromises();

    expect(api.acknowledgeReviewItem).toHaveBeenCalledWith("q1");
    expect(wrapper.find("[data-testid=review-queue]").exists()).toBe(false);
  });
});

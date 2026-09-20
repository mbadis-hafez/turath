import { flushPromises } from "@vue/test-utils";
import { beforeEach, describe, expect, it, vi } from "vitest";

import ConflictResolutionModal from "@/components/dashboard/ConflictResolutionModal.vue";
import { mountWithPlugins } from "@/test/utils";
import type { DashboardRecord } from "@/types/completeness";

const api = vi.hoisted(() => ({
  getRecordCompleteness: vi.fn(),
  resolveSourceConflict: vi.fn(),
}));
vi.mock("@/api/dashboard", () => api);

const record: DashboardRecord = {
  entity_type: "artist",
  id: 13,
  slug: "x",
  title: { ar: null, en: "X" },
  completeness_pct: 80,
  severity: "conflict",
  blocking_gaps: [],
  minor_gaps: [],
  open_conflict_count: 1,
};

beforeEach(() => {
  api.getRecordCompleteness.mockReset().mockResolvedValue({
    data: {
      open_conflicts: [
        {
          id: "c1",
          field_key: "birth_year",
          label: { ar: "سنة الميلاد", en: "Birth year" },
          citations: [
            { id: "a", field_key: "birth_year", claimed_value: { year: 1939 }, source: { id: "s1", title: { ar: null, en: "Riyadh archive" }, publisher_or_outlet: null, reference_note: "p. 4" } },
            { id: "b", field_key: "birth_year", claimed_value: { year: 1941 }, source: { id: "s2", title: { ar: null, en: "Catalogue" }, publisher_or_outlet: null, reference_note: null } },
          ],
        },
      ],
    },
  });
  api.resolveSourceConflict.mockReset().mockResolvedValue({});
});

describe("ConflictResolutionModal", () => {
  it("shows competing sources side by side and disables submit until one is chosen", async () => {
    const wrapper = mountWithPlugins(ConflictResolutionModal, { locale: "en", props: { record } });
    await flushPromises();

    expect(wrapper.text()).toContain("Riyadh archive");
    expect(wrapper.text()).toContain("Catalogue");
    expect(wrapper.findAll("input[type=radio]")).toHaveLength(2);
    expect(wrapper.get("button[type=submit]").attributes("disabled")).toBeDefined();
  });

  it("submits the chosen source and note, then emits resolved", async () => {
    const wrapper = mountWithPlugins(ConflictResolutionModal, { locale: "en", props: { record } });
    await flushPromises();

    await wrapper.findAll("input[type=radio]")[1]!.setValue(true);
    await wrapper.get("input[type=text]").setValue("Catalogue is primary");
    await wrapper.get("form").trigger("submit");
    await flushPromises();

    expect(api.resolveSourceConflict).toHaveBeenCalledWith("c1", {
      resolved_source_id: "s2",
      resolution_note: "Catalogue is primary",
    });
    expect(wrapper.emitted("resolved")).toHaveLength(1);
  });
});

import { describe, expect, it } from "vitest";

import ActivityTimeline from "@/components/activity/ActivityTimeline.vue";
import { mountWithPlugins } from "@/test/utils";
import type { ActivityEntry } from "@/types/activity";

function entry(overrides: Partial<ActivityEntry> = {}): ActivityEntry {
  return {
    id: 1,
    event: "created",
    subject_type: "Artist",
    subject_id: 10,
    subject_label: "Mahmoud Said",
    causer: { id: 2, name: "Sara" },
    edit_summary: null,
    changes: [
      {
        field: "name",
        label: { ar: "الاسم", en: "Name" },
        old: null,
        new: "Mahmoud Said",
      },
      {
        field: "metadata",
        label: { ar: "بيانات", en: "Metadata" },
        old: { a: 1 },
        new: { a: 2 },
      },
    ],
    created_at: new Date(Date.now() - 60_000).toISOString(),
    ...overrides,
  };
}

describe("ActivityTimeline", () => {
  it("renders causer name, subject label and the event badge", () => {
    const wrapper = mountWithPlugins(ActivityTimeline, {
      locale: "ar",
      props: { entries: [entry()], loading: false },
    });

    expect(wrapper.text()).toContain("Sara");
    expect(wrapper.text()).toContain("Mahmoud Said");
    expect(wrapper.get("[data-testid='event-badge']").text()).toBe("إنشاء");
  });

  it("renders the System label for entries without a causer", () => {
    const wrapper = mountWithPlugins(ActivityTimeline, {
      locale: "ar",
      props: { entries: [entry({ causer: null })], loading: false },
    });
    expect(wrapper.text()).toContain("النظام");
    expect(wrapper.text()).not.toContain("Sara");
  });

  it("reveals field diffs with translated labels and stringified values", async () => {
    const wrapper = mountWithPlugins(ActivityTimeline, {
      locale: "ar",
      props: { entries: [entry()], loading: false },
    });

    await wrapper.get("details summary").trigger("click");

    const row = wrapper.get("tbody tr");
    expect(row.text()).toContain("الاسم");
    expect(row.text()).toContain("Mahmoud Said");
    expect(row.text()).toContain("—");

    const jsonRow = wrapper.get("tbody tr:nth-child(2)");
    expect(jsonRow.text()).toContain('{"a":1}');
    expect(jsonRow.text()).toContain('{"a":2}');
  });

  it("shows the empty state when there are no entries", () => {
    const wrapper = mountWithPlugins(ActivityTimeline, {
      locale: "ar",
      props: { entries: [], loading: false },
    });
    expect(wrapper.text()).toContain("لا توجد تغييرات مسجلة");
  });

  it("shows a spinner while loading", () => {
    const wrapper = mountWithPlugins(ActivityTimeline, {
      locale: "ar",
      props: { entries: [], loading: true },
    });
    expect(wrapper.find("[role='status']").exists()).toBe(true);
  });

  it("renders distinct badges per event", () => {
    const wrapper = mountWithPlugins(ActivityTimeline, {
      locale: "en",
      props: {
        entries: [
          entry({ id: 1, event: "created" }),
          entry({ id: 2, event: "deleted" }),
        ],
        loading: false,
      },
    });
    const badges = wrapper.findAll("[data-testid='event-badge']");
    expect(badges[0].text()).toBe("Created");
    expect(badges[1].text()).toBe("Deleted");
    expect(badges[0].classes()).not.toEqual(badges[1].classes());
  });
});

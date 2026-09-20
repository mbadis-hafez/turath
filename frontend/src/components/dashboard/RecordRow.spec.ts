import { beforeEach, describe, expect, it } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import RecordRow from "@/components/dashboard/RecordRow.vue";
import { mountWithPlugins } from "@/test/utils";
import type { DashboardRecord } from "@/types/completeness";

function makeRecord(patch: Partial<DashboardRecord> = {}): DashboardRecord {
  return {
    entity_type: "artist",
    id: 13,
    slug: "abdulhalim-radwi",
    title: { ar: "عبدالحليم رضوي", en: "Abdulhalim Radwi" },
    completeness_pct: 62,
    severity: "blocking",
    blocking_gaps: ["death_year_or_living_confirmed"],
    minor_gaps: ["bio_en"],
    open_conflict_count: 0,
    ...patch,
  };
}

let router: Router;

beforeEach(async () => {
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale/artists/:slug", name: "artists.show", component: { template: "<div />" } },
      { path: "/:locale/artworks/:id", name: "artworks.show", component: { template: "<div />" } },
    ],
  });
  await router.push("/en/artists/x");
  await router.isReady();
});

const mountRow = (record: DashboardRecord) =>
  mountWithPlugins(RecordRow, { locale: "en", router, props: { record } });

describe("RecordRow", () => {
  it("uses the severity color for the bar and shows gap chips with labels", () => {
    const wrapper = mountRow(makeRecord());

    expect(wrapper.get("[data-testid=severity-bar]").classes()).toContain("bg-danger");
    expect(wrapper.text()).toContain("Blocks publishing");
    expect(wrapper.text()).toContain("Death year or living status");
    expect(wrapper.text()).toContain("English biography");
  });

  it("links the complete action to the record page", () => {
    const wrapper = mountRow(makeRecord());

    expect(wrapper.get("[data-testid=row-action]").attributes("href")).toBe("/en/artists/abdulhalim-radwi");
    expect(wrapper.get("[data-testid=row-action]").text()).toBe("Complete");
  });

  it("emits resolve from the conflict action instead of navigating", async () => {
    const record = makeRecord({ severity: "conflict", open_conflict_count: 1 });
    const wrapper = mountRow(record);

    expect(wrapper.get("[data-testid=severity-bar]").classes()).toContain("bg-warn");
    await wrapper.get("[data-testid=row-action]").trigger("click");

    expect(wrapper.emitted("resolve")?.[0]).toEqual([record]);
  });

  it("shows no action for a clear record", () => {
    const wrapper = mountRow(makeRecord({ severity: "clear", blocking_gaps: [], minor_gaps: [] }));

    expect(wrapper.find("[data-testid=row-action]").exists()).toBe(false);
    expect(wrapper.get("[data-testid=severity-bar]").classes()).toContain("bg-success");
  });
});

import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia, type Pinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import OcrEntityMatch from "@/components/curation/OcrEntityMatch.vue";
import { useAuthStore } from "@/stores/auth";
import { mountWithPlugins } from "@/test/utils";
import type { EntityMatch, EntityMatchCandidate } from "@/types/ocr";

const api = vi.hoisted(() => ({ searchEntityMatch: vi.fn() }));
vi.mock("@/api/archive", () => api);

function candidate(patch: Partial<EntityMatchCandidate> = {}): EntityMatchCandidate {
  return {
    id: 127, key: null, score: 1, strength: "high", basis: ["exact_name"],
    label: { ar: "محمد عبدالله السليم", en: "Mohammed Alsaleem" }, detail: "1945–", missing: false,
    ...patch,
  };
}

function match(patch: Partial<EntityMatch> = {}): EntityMatch {
  return {
    id: 5, entity_type: "artist", source_text: "محمد عبداللـه السليم", status: "pending", requires_review: true,
    candidates: [candidate(), candidate({ id: 128, score: 0.67, strength: "medium", basis: ["shared_name_parts"], label: { ar: "محمد السليم", en: "" }, detail: null })],
    confirmed: null, reviewed_at: null,
    ...patch,
  };
}

let router: Router;
let pinia: Pinia;
beforeEach(async () => {
  api.searchEntityMatch.mockReset();
  pinia = createPinia();
  setActivePinia(pinia);
  useAuthStore().$patch({ user: { id: 1, name: "E", email: "e@x", roles: ["editor"], permissions: ["archive.manage", "artists.manage"] } as never, initialized: true });
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale/admin/archive/:id", name: "admin.archive.show", component: { template: "<div />" } },
      { path: "/:locale/admin/artists/new", name: "admin.artists.new", component: { template: "<div />" } },
      { path: "/:locale/admin/artists/:id", name: "admin.artists.show", component: { template: "<div />" } },
      { path: "/:locale/admin/events/new", name: "admin.events.new", component: { template: "<div />" } },
      { path: "/:locale/admin/events/:id", name: "admin.events.edit", component: { template: "<div />" } },
    ],
  });
  await router.push("/en/admin/archive/1");
  await router.isReady();
});

const mount = (m: EntityMatch, canReview = true) => mountWithPlugins(OcrEntityMatch, { locale: "en", router, pinia, props: { match: m, canReview, archiveItemId: 9 } });

describe("OcrEntityMatch", () => {
  it("lists candidates with their evidence, and says a score is not proof", () => {
    const wrapper = mount(match());
    const rows = wrapper.findAll("[data-testid=entity-match-candidate]");

    expect(wrapper.text()).toContain("not proof");
    expect(rows).toHaveLength(2);
    expect(rows[0].get("a").attributes("href")).toBe("/en/admin/artists/127");
    expect(rows[0].get("[data-testid=entity-match-strength]").text()).toBe("Strong · 100%");
    expect(rows[1].get("[data-testid=entity-match-basis]").text()).toBe("shares name parts");
  });

  it("confirms a candidate, or none of them", async () => {
    const wrapper = mount(match());

    await wrapper.findAll("[data-testid=entity-match-confirm]")[1].trigger("click");
    await wrapper.get("[data-testid=entity-match-no-match]").trigger("click");

    expect(wrapper.emitted("confirm")?.[0][0]).toBe(5);
    expect((wrapper.emitted("confirm")?.[0][1] as EntityMatchCandidate).id).toBe(128);
    expect(wrapper.emitted("decide")?.[0]).toEqual([5, "no-match"]);
  });

  it("shows a decision with a way to undo it, and no candidates to pick from", async () => {
    const wrapper = mount(match({ status: "confirmed", requires_review: false, confirmed: { id: "127", key: null, label: { ar: "محمد عبدالله السليم", en: "Mohammed Alsaleem" }, detail: null, missing: false } }));

    expect(wrapper.get("[data-testid=entity-match-confirmed]").text()).toContain("Mohammed Alsaleem");
    expect(wrapper.find("[data-testid=entity-match-candidate]").exists()).toBe(false);
    await wrapper.get("[data-testid=entity-match-reset]").trigger("click");
    expect(wrapper.emitted("decide")?.[0]).toEqual([5, "reset"]);
  });

  it("offers nothing to pick for a deleted record, nothing to decide without review rights, and says when nothing matched", () => {
    expect(mount(match({ candidates: [candidate({ missing: true, label: null })] })).find("[data-testid=entity-match-confirm]").exists()).toBe(false);
    expect(mount(match(), false).find("[data-testid=entity-match-confirm]").exists()).toBe(false);
    expect(mount(match({ candidates: [] })).find("[data-testid=entity-match-empty]").exists()).toBe(true);
  });

  it("shows a place as a spelling in use, not a record link", () => {
    const place = mount(match({ entity_type: "place", candidates: [candidate({ id: null, key: "الرياض", label: { ar: "الرياض", en: "الرياض" }, basis: ["exact_place"] })] }));

    expect(place.find("[data-testid=entity-match-candidate] a").exists()).toBe(false);
    expect(place.get("[data-testid=entity-match-candidate]").text()).toContain("الرياض");
  });

  it("searches for a record the candidates missed, and links the one the reviewer picks", async () => {
    api.searchEntityMatch.mockResolvedValue({ data: [{ id: 300, label: { ar: "محمد عبدالله السليم", en: "Mohammed A. Alsaleem" }, detail: "1940–" }] });
    const wrapper = mount(match());

    await wrapper.get("[data-testid=entity-match-search-open]").trigger("click");
    expect((wrapper.get("[data-testid=entity-match-search-input]").element as HTMLInputElement).value).toBe("محمد عبداللـه السليم");
    await wrapper.get("[data-testid=entity-match-search] form").trigger("submit");
    await flushPromises();

    expect(api.searchEntityMatch).toHaveBeenCalledWith(9, 5, "محمد عبداللـه السليم", expect.any(AbortSignal));
    const result = wrapper.get("[data-testid=entity-match-search-result]");
    expect(result.text()).toContain("Mohammed A. Alsaleem");
    await result.get("[data-testid=entity-match-link]").trigger("click");
    expect(wrapper.emitted("link")?.[0]).toEqual([5, 300]);
  });

  it("says when the search finds nothing", async () => {
    api.searchEntityMatch.mockResolvedValue({ data: [] });
    const wrapper = mount(match({ status: "no_match" }));

    await wrapper.get("[data-testid=entity-match-search-open]").trigger("click");
    await wrapper.get("[data-testid=entity-match-search] form").trigger("submit");
    await flushPromises();

    expect(wrapper.find("[data-testid=entity-match-search-empty]").exists()).toBe(true);
  });

  it("opens the record's own create page with the name filled in, only for someone who may create it", () => {
    const create = mount(match()).get("[data-testid=entity-match-create]");
    expect(create.attributes("href")).toBe("/en/admin/artists/new?prefill=%D9%85%D8%AD%D9%85%D8%AF+%D8%B9%D8%A8%D8%AF%D8%A7%D9%84%D9%84%D9%80%D9%87+%D8%A7%D9%84%D8%B3%D9%84%D9%8A%D9%85");
    expect(create.attributes("target")).toBe("_blank");

    // No events.manage: no way to create an event from here.
    expect(mount(match({ entity_type: "event" })).find("[data-testid=entity-match-create]").exists()).toBe(false);
    // A place is a spelling, not a record: nothing to search or create.
    const place = mount(match({ entity_type: "place", candidates: [] }));
    expect(place.find("[data-testid=entity-match-search-open]").exists()).toBe(false);
    expect(place.find("[data-testid=entity-match-create]").exists()).toBe(false);
  });
});

import { describe, expect, it } from "vitest";

import SearchFacetSidebar from "@/components/search/SearchFacetSidebar.vue";
import { mountWithPlugins } from "@/test/utils";
import type { SectionKey } from "@/composables/useSiteSearch";
import type { ArchiveFacets } from "@/types/archive";

const totals: Record<SectionKey, number> = {
  artists: 12,
  artworks: 48,
  archive: 173,
  events: 14,
};

function mount(patch: Record<string, unknown> = {}) {
  return mountWithPlugins(SearchFacetSidebar, {
    locale: "en",
    props: {
      types: ["artists", "artworks", "archive", "events"],
      totals,
      itemTypes: [],
      archiveFacets: null,
      verifiedOnly: false,
      ...patch,
    },
  });
}

describe("SearchFacetSidebar", () => {
  it("lists all four record types with their totals, all checked by default", () => {
    const wrapper = mount();
    const options = wrapper.findAll("[data-testid=facet-type-option]");
    expect(options).toHaveLength(4);
    for (const option of options)
      expect((option.element as HTMLInputElement).checked).toBe(true);
    expect(wrapper.get("[data-testid=facet-type]").text()).toContain("173");
  });

  it("shows an unchecked type as excluded and emits toggle-type on click", async () => {
    const wrapper = mount({ types: ["artists", "artworks", "archive"] });
    const options = wrapper.findAll("[data-testid=facet-type-option]");
    expect((options[3].element as HTMLInputElement).checked).toBe(false);

    await options[3].trigger("change");
    expect(wrapper.emitted("toggle-type")?.[0]).toEqual(["events"]);
  });

  it("only shows the material-type facet once the archive section has one", () => {
    const withoutFacet = mount();
    expect(withoutFacet.find("[data-testid=facet-item-type]").exists()).toBe(
      false,
    );

    const facets: ArchiveFacets = {
      item_type: [
        { value: "article", count: 86 },
        { value: "image", count: 49 },
      ],
      place: [],
      theme_id: [],
      access: [],
    };
    const withFacet = mount({ archiveFacets: facets });
    expect(withFacet.get("[data-testid=facet-item-type]").text()).toContain(
      "86",
    );
  });

  it("toggles the verified-only switch", async () => {
    const wrapper = mount();
    const toggle = wrapper.get("[data-testid=verified-only-toggle]");
    expect(toggle.attributes("aria-checked")).toBe("false");

    await toggle.trigger("click");
    expect(wrapper.emitted("update:verified-only")?.[0]).toEqual([true]);
  });

  it("shows Clear all only when a filter is active, and clears on click", async () => {
    const wrapper = mount();
    expect(wrapper.find("[data-testid=clear-filters]").exists()).toBe(false);

    const filtered = mount({ verifiedOnly: true });
    await filtered.get("[data-testid=clear-filters]").trigger("click");
    expect(filtered.emitted("clear")).toHaveLength(1);
  });
});

import { describe, expect, it } from "vitest";

import SearchArchiveResultRow from "@/components/search/SearchArchiveResultRow.vue";
import { mountWithPlugins } from "@/test/utils";
import type { ArchiveItem } from "@/types/archive";

function makeItem(patch: Partial<ArchiveItem> = {}): ArchiveItem {
  return {
    id: 1,
    legacy_ref: "ARTCL003",
    item_type: "article",
    title: { ar: "مقال عن أحمد المغلوث", en: null },
    content: null,
    access_level: "public",
    publication_status: "published",
    restricted: false,
    description: { ar: "نشر أحمد المغلوث رأيه حول المعرض", en: null },
    ...patch,
  };
}

describe("SearchArchiveResultRow", () => {
  it("highlights the search term in the title and description", () => {
    const wrapper = mountWithPlugins(SearchArchiveResultRow, {
      locale: "ar",
      props: { item: makeItem(), highlight: "أحمد المغلوث" },
    });
    expect(wrapper.findAll("mark").length).toBeGreaterThan(0);
  });

  it("shows the restricted badge and still nothing crashes without a description", () => {
    const wrapper = mountWithPlugins(SearchArchiveResultRow, {
      locale: "ar",
      props: {
        item: makeItem({
          restricted: true,
          description: undefined,
          title: { ar: "وثيقة غير منشورة", en: null },
        }),
        highlight: "أحمد",
      },
    });
    expect(wrapper.find("[data-testid=restricted-badge]").exists()).toBe(true);
    expect(wrapper.find("mark").exists()).toBe(false);
  });

  it("shows the item-type badge", () => {
    const wrapper = mountWithPlugins(SearchArchiveResultRow, {
      locale: "en",
      props: { item: makeItem({ item_type: "image" }), highlight: "" },
    });
    expect(wrapper.get("[data-testid=type-badge]").text()).toBe("Image");
  });
});

import { beforeEach, describe, expect, it } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import ArtworkCard from "@/components/artworks/ArtworkCard.vue";
import { mountWithPlugins } from "@/test/utils";
import type { ArtworkListItem } from "@/types/artwork";

function makeArtwork(patch: Partial<ArtworkListItem> = {}): ArtworkListItem {
  return {
    id: 42,
    legacy_ref: null,
    title: { ar: "العمال", en: "The Workers" },
    is_untitled: false,
    artist: {
      id: 7,
      slug: "inji-efflatoun",
      name: { ar: "إنجي أفلاطون", en: "Inji Efflatoun" },
    },
    attribution_certainty: "confirmed",
    category: "painting",
    medium: { ar: "زيت على قماش", en: "Oil on canvas" },
    creation: {
      display: null,
      year_from: 1960,
      year_to: null,
      calendar: "gregorian",
      certainty: "exact",
    },
    dimensions: {
      height_cm: 50,
      width_cm: 70,
      depth_cm: null,
      raw: null,
    },
    ...patch,
  };
}

let router: Router;

beforeEach(async () => {
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: "/:locale/artworks/:id",
        name: "artworks.show",
        component: { template: "<div />" },
      },
    ],
  });
  await router.push("/ar/artworks/1");
  await router.isReady();
});

describe("ArtworkCard", () => {
  it("renders the title in the current locale", () => {
    const wrapper = mountWithPlugins(ArtworkCard, {
      locale: "ar",
      router,
      props: { artwork: makeArtwork() },
    });
    expect(wrapper.get("h3").text()).toContain("العمال");
  });

  it("shows the Untitled badge instead of a title when is_untitled", () => {
    const wrapper = mountWithPlugins(ArtworkCard, {
      locale: "ar",
      router,
      props: {
        artwork: makeArtwork({ is_untitled: true, title: { ar: null, en: null } }),
      },
    });
    expect(wrapper.get("h3").text()).toContain("دون عنوان");
  });

  it("links the whole card to the artwork page", async () => {
    await router.push("/en/artworks/1");
    const wrapper = mountWithPlugins(ArtworkCard, {
      locale: "en",
      router,
      props: { artwork: makeArtwork() },
    });
    expect(wrapper.get("a").attributes("href")).toBe("/en/artworks/42");
  });

  it.each(["attributed", "disputed", "unattributed"] as const)(
    "shows the attribution badge for %s works",
    (certainty) => {
      const wrapper = mountWithPlugins(ArtworkCard, {
        locale: "ar",
        router,
        props: { artwork: makeArtwork({ attribution_certainty: certainty }) },
      });
      expect(wrapper.get("h3 + span, h3 ~ span").text()).toBeTruthy();
      expect(wrapper.text()).not.toContain("دون عنوان");
    },
  );

  it("hides the attribution badge for confirmed works", () => {
    const wrapper = mountWithPlugins(ArtworkCard, {
      locale: "ar",
      router,
      props: { artwork: makeArtwork({ attribution_certainty: "confirmed" }) },
    });
    expect(wrapper.text()).not.toContain("نسب احتمالي");
    expect(wrapper.text()).not.toContain("نسب محل خلاف");
    expect(wrapper.text()).not.toContain("فنان غير معروف");
  });

  it("renders artist, year, medium and category", () => {
    const wrapper = mountWithPlugins(ArtworkCard, {
      locale: "en",
      router,
      props: { artwork: makeArtwork() },
    });
    expect(wrapper.text()).toContain("Inji Efflatoun");
    expect(wrapper.text()).toContain("1960");
    expect(wrapper.text()).toContain("Oil on canvas");
    expect(wrapper.text()).toContain("Painting");
  });

  it("omits the artist line when artist is null", () => {
    const wrapper = mountWithPlugins(ArtworkCard, {
      locale: "en",
      router,
      props: { artwork: makeArtwork({ artist: null }) },
    });
    expect(wrapper.text()).not.toContain("Inji Efflatoun");
  });
});

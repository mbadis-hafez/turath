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
    image_url: null,
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

  it("renders artist, year and medium", () => {
    const wrapper = mountWithPlugins(ArtworkCard, {
      locale: "en",
      router,
      props: { artwork: makeArtwork() },
    });
    expect(wrapper.text()).toContain("Inji Efflatoun");
    expect(wrapper.text()).toContain("1960");
    expect(wrapper.text()).toContain("Oil on canvas");
  });

  it("omits the artist line when artist is null", () => {
    const wrapper = mountWithPlugins(ArtworkCard, {
      locale: "en",
      router,
      props: { artwork: makeArtwork({ artist: null }) },
    });
    expect(wrapper.text()).not.toContain("Inji Efflatoun");
  });

  it("flags an approximate year, but not an exact one", () => {
    const exact = mountWithPlugins(ArtworkCard, {
      locale: "en",
      router,
      props: { artwork: makeArtwork() },
    });
    expect(exact.find("[data-testid=approx-year-badge]").exists()).toBe(false);

    const approx = mountWithPlugins(ArtworkCard, {
      locale: "en",
      router,
      props: { artwork: makeArtwork({ creation: { display: null, year_from: 1980, year_to: null, calendar: "gregorian", certainty: "circa" } }) },
    });
    expect(approx.get("[data-testid=approx-year-badge]").text()).toBe("Approximate year");
  });

  it("shows the image when one is public, and a no-image note when there isn't", () => {
    const withImage = mountWithPlugins(ArtworkCard, {
      locale: "en",
      router,
      props: { artwork: makeArtwork({ image_url: "/api/v1/artworks/42/images/1/file" }) },
    });
    expect(withImage.get("[data-testid=artwork-image]").attributes("src")).toBe("/api/v1/artworks/42/images/1/file");
    expect(withImage.find("[data-testid=artwork-no-image]").exists()).toBe(false);

    const withoutImage = mountWithPlugins(ArtworkCard, {
      locale: "en",
      router,
      props: { artwork: makeArtwork({ image_url: null }) },
    });
    expect(withoutImage.find("[data-testid=artwork-image]").exists()).toBe(false);
    expect(withoutImage.get("[data-testid=artwork-no-image]").text()).toBe("No image available for this work");
  });
});

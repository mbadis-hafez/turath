import { beforeEach, describe, expect, it } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import ArtistCard from "@/components/artists/ArtistCard.vue";
import type { ArtistListItem } from "@/types/artist";
import { mountWithPlugins } from "@/test/utils";

function makeArtist(
  patch: Partial<ArtistListItem> = {},
): ArtistListItem {
  return {
    id: 1,
    slug: "inji-efflatoun",
    name: { ar: "إنجي أفلاطون", en: "Inji Efflatoun" },
    birth: {
      display: null,
      year_from: 1924,
      year_to: null,
      calendar: "gregorian",
      certainty: "exact",
    },
    death: {
      display: null,
      year_from: 1989,
      year_to: null,
      calendar: "gregorian",
      certainty: "exact",
    },
    living_status: "deceased",
    verified_status: "verified",
    ...patch,
  };
}

let router: Router;

beforeEach(async () => {
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: "/:locale/artists",
        name: "artists.index",
        component: { template: "<div />" },
      },
      {
        path: "/:locale/artists/:slug",
        name: "artists.show",
        component: { template: "<div />" },
      },
    ],
  });
  await router.push("/ar/artists");
  await router.isReady();
});

describe("ArtistCard", () => {
  it("renders the primary name in the current locale and secondary in the other", () => {
    const wrapper = mountWithPlugins(ArtistCard, {
      locale: "ar",
      router,
      props: { artist: makeArtist() },
    });

    expect(wrapper.get("h3").text()).toContain("إنجي أفلاطون");
    const secondary = wrapper.get("p[lang='en']");
    expect(secondary.text()).toBe("Inji Efflatoun");
    expect(secondary.attributes("dir")).toBe("ltr");
  });

  it("swaps primary/secondary names for English", () => {
    const wrapper = mountWithPlugins(ArtistCard, {
      locale: "en",
      router,
      props: { artist: makeArtist() },
    });

    expect(wrapper.get("h3").text()).toContain("Inji Efflatoun");
    const secondary = wrapper.get("p[lang='ar']");
    expect(secondary.text()).toBe("إنجي أفلاطون");
    expect(secondary.attributes("dir")).toBe("rtl");
  });

  it("shows the fallback badge when the current-locale name is missing", () => {
    const wrapper = mountWithPlugins(ArtistCard, {
      locale: "ar",
      router,
      props: {
        artist: makeArtist({ name: { ar: null, en: "Inji Efflatoun" } }),
      },
    });

    expect(wrapper.get("h3").text()).toContain("Inji Efflatoun");
    expect(wrapper.get("[role='note']").text()).toBe("النص متوفر بلغة أخرى");
    expect(wrapper.find("h3 ~ p[lang]").exists()).toBe(false);
  });

  it("renders life dates", () => {
    const wrapper = mountWithPlugins(ArtistCard, {
      locale: "en",
      router,
      props: { artist: makeArtist() },
    });
    expect(wrapper.text()).toContain("1924");
    expect(wrapper.text()).toContain("1989");
  });

  it("renders without crashing when birth and death are null", () => {
    const wrapper = mountWithPlugins(ArtistCard, {
      locale: "ar",
      router,
      props: { artist: makeArtist({ birth: null, death: null }) },
    });

    expect(wrapper.get("h3").text()).toContain("إنجي أفلاطون");
    expect(wrapper.find("p.mt-2").exists()).toBe(false);
  });

  it("shows the verified badge only for verified or disputed artists", () => {
    const verified = mountWithPlugins(ArtistCard, {
      locale: "ar",
      router,
      props: { artist: makeArtist({ verified_status: "verified" }) },
    });
    expect(verified.text()).toContain("موثّق");

    const disputed = mountWithPlugins(ArtistCard, {
      locale: "ar",
      router,
      props: { artist: makeArtist({ verified_status: "disputed" }) },
    });
    expect(disputed.text()).toContain("محل خلاف");

    const unverified = mountWithPlugins(ArtistCard, {
      locale: "ar",
      router,
      props: { artist: makeArtist({ verified_status: "unverified" }) },
    });
    expect(unverified.text()).not.toContain("موثّق");
  });

  it("links the whole card to the artist page", () => {
    const wrapper = mountWithPlugins(ArtistCard, {
      locale: "ar",
      router,
      props: { artist: makeArtist() },
    });

    const links = wrapper.findAll("a");
    expect(links).toHaveLength(1);
    expect(links[0].attributes("href")).toBe("/ar/artists/inji-efflatoun");
  });
});

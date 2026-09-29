import { beforeEach, describe, expect, it } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import ArtistIndexCard from "@/components/artists/ArtistIndexCard.vue";
import type { ArtistListItem } from "@/types/artist";
import { mountWithPlugins } from "@/test/utils";

function makeArtist(patch: Partial<ArtistListItem> = {}): ArtistListItem {
  return {
    id: 1,
    slug: "inji-efflatoun",
    name: { ar: "إنجي أفلاطون", en: "Inji Efflatoun" },
    city: { ar: "القاهرة", en: "Cairo" },
    birth: null,
    death: null,
    living_status: "deceased",
    verified_status: "verified",
    portrait_url: null,
    materials_count: 8,
    ...patch,
  };
}

let router: Router;

beforeEach(async () => {
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale/artists/:slug", name: "artists.show", component: {} },
    ],
  });
  await router.push("/ar/artists/inji-efflatoun");
  await router.isReady();
});

describe("ArtistIndexCard", () => {
  it("renders the portrait image when present and omits it when absent", () => {
    const withPortrait = mountWithPlugins(ArtistIndexCard, {
      props: { artist: makeArtist({ portrait_url: "/portrait.jpg" }) },
      router,
    });
    expect(withPortrait.find('[data-testid="artist-portrait"]').exists()).toBe(
      true,
    );

    const withoutPortrait = mountWithPlugins(ArtistIndexCard, {
      props: { artist: makeArtist({ portrait_url: null }) },
      router,
    });
    expect(
      withoutPortrait.find('[data-testid="artist-portrait"]').exists(),
    ).toBe(false);
  });

  it("shows the pending note only when the artist is not verified", () => {
    const verified = mountWithPlugins(ArtistIndexCard, {
      props: { artist: makeArtist({ verified_status: "verified" }) },
      router,
    });
    expect(verified.find('[data-testid="artist-pending"]').exists()).toBe(
      false,
    );

    const unverified = mountWithPlugins(ArtistIndexCard, {
      props: { artist: makeArtist({ verified_status: "unverified" }) },
      router,
    });
    expect(unverified.find('[data-testid="artist-pending"]').exists()).toBe(
      true,
    );
  });

  it("shows an em-dash when the city is missing", () => {
    const wrapper = mountWithPlugins(ArtistIndexCard, {
      props: { artist: makeArtist({ city: { ar: null, en: null } }) },
      router,
    });
    expect(wrapper.text()).toContain("—");
  });

  it("shows a pluralized materials count", () => {
    const wrapper = mountWithPlugins(ArtistIndexCard, {
      props: { artist: makeArtist({ materials_count: 8 }) },
      router,
    });
    expect(wrapper.find('[data-testid="artist-materials"]').text()).toContain(
      "8",
    );
  });

  it("links to the artist show page", () => {
    const wrapper = mountWithPlugins(ArtistIndexCard, {
      props: { artist: makeArtist() },
      router,
    });
    const link = wrapper.findComponent({ name: "RouterLink" });
    expect(link.exists()).toBe(true);
    expect(link.props("to")).toMatchObject({
      name: "artists.show",
      params: { locale: "ar", slug: "inji-efflatoun" },
    });
  });

  it("puts the locale-preferred name first and the other as secondary", async () => {
    const arWrapper = mountWithPlugins(ArtistIndexCard, {
      props: { artist: makeArtist({ name: { ar: "إنجي", en: "Inji" } }) },
      router,
      locale: "ar",
    });
    expect(arWrapper.text()).toContain("إنجي");
    expect(arWrapper.text()).toContain("Inji");

    const enWrapper = mountWithPlugins(ArtistIndexCard, {
      props: { artist: makeArtist({ name: { ar: "إنجي", en: "Inji" } }) },
      router,
      locale: "en",
    });
    expect(enWrapper.text()).toContain("Inji");
    expect(enWrapper.text()).toContain("إنجي");
  });
});

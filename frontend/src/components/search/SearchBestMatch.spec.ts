import { flushPromises } from "@vue/test-utils";
import { describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter } from "vue-router";

import SearchBestMatch from "@/components/search/SearchBestMatch.vue";
import { mountWithPlugins } from "@/test/utils";
import type { Artist, ArtistListItem } from "@/types/artist";

const api = vi.hoisted(() => ({
  getArtist: vi.fn(),
  listArtistArtworks: vi.fn(),
  listArchiveItems: vi.fn(),
}));
vi.mock("@/api/artists", () => ({ getArtist: api.getArtist }));
vi.mock("@/api/artworks", () => ({
  listArtistArtworks: api.listArtistArtworks,
}));
vi.mock("@/api/archive", () => ({ listArchiveItems: api.listArchiveItems }));

function candidate(patch: Partial<ArtistListItem> = {}): ArtistListItem {
  return {
    id: 1,
    slug: "ahmad-almaghlout",
    name: { ar: "أحمد المغلوث", en: "Ahmad Almaghlout" },
    city: { ar: null, en: null },
    birth: null,
    death: null,
    living_status: "unknown",
    verified_status: "verified",
    portrait_url: null,
    ...patch,
  };
}

function artistDetail(patch: Partial<Artist> = {}): Artist {
  return {
    ...candidate(),
    birth: null,
    death: null,
    legacy_code: null,
    bio: { ar: "فنان من الأحساء", en: null },
    also_known_as: [],
    verified_at: null,
    publication_status: "published",
    created_at: "2026-01-01T00:00:00Z",
    updated_at: "2026-01-01T00:00:00Z",
    events: [{ id: 1 } as never],
    ...patch,
  };
}

async function mount(candidates: ArtistListItem[], term: string) {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: "/:locale/artists/:slug",
        name: "artists.show",
        component: { template: "<div />" },
      },
    ],
  });
  await router.push("/en/artists");
  const wrapper = mountWithPlugins(SearchBestMatch, {
    locale: "en",
    router,
    props: { candidates, term },
  });
  await flushPromises();
  return wrapper;
}

describe("SearchBestMatch", () => {
  it("renders the hero when the top artist's name starts with the query", async () => {
    api.getArtist.mockResolvedValue({ data: artistDetail() });
    api.listArtistArtworks.mockResolvedValue({ data: [], meta: { total: 32 } });
    api.listArchiveItems.mockResolvedValue({ data: [], meta: { total: 148 } });

    const wrapper = await mount([candidate()], "Ahmad");

    expect(wrapper.get("[data-testid=search-best-match]").text()).toContain(
      "Ahmad Almaghlout",
    );
    expect(wrapper.text()).toContain("32");
    expect(wrapper.text()).toContain("148");
    expect(wrapper.text()).toContain("1"); // events count, from the embedded events array
  });

  it("renders nothing when the top result doesn't start with the query", async () => {
    const wrapper = await mount(
      [candidate({ name: { ar: "أحمد المغلوث", en: "Ahmad Almaghlout" } })],
      "المغلوث",
    );

    expect(wrapper.find("[data-testid=search-best-match]").exists()).toBe(
      false,
    );
    expect(api.getArtist).not.toHaveBeenCalled();
  });

  it("renders nothing when there are no artist candidates", async () => {
    const wrapper = await mount([], "Ahmad");
    expect(wrapper.find("[data-testid=search-best-match]").exists()).toBe(
      false,
    );
  });
});

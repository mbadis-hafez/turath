import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { flushPromises, mount, type VueWrapper } from "@vue/test-utils";
import { defineComponent, h } from "vue";
import { createMemoryHistory, createRouter, type Router } from "vue-router";
import { createPinia, setActivePinia } from "pinia";

import { i18n } from "@/i18n";
import {
  ARTIST_SEARCH_DEBOUNCE_MS,
  useArtistsList,
  type UseArtistsListReturn,
} from "./useArtistsList";
import type { ArtistListItem } from "@/types/artist";
import type { PaginatedResponse } from "@/types/api";

vi.mock("@/api/artists", () => ({
  listArtists: vi.fn(),
}));

import { listArtists } from "@/api/artists";

const artist: ArtistListItem = {
  id: 1,
  slug: "inji-efflatoun",
  name: { ar: "إنجي أفلاطون", en: "Inji Efflatoun" },
  city: { ar: "القاهرة", en: "Cairo" },
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
  portrait_url: null,
  materials_count: 2,
};

const paginated: PaginatedResponse<ArtistListItem> = {
  data: [artist],
  links: [],
  meta: {
    current_page: 1,
    last_page: 3,
    per_page: 100,
    total: 3,
    from: 1,
    to: 1,
  },
};

const DummyPage = defineComponent({
  setup(_props, { expose }) {
    const list = useArtistsList();
    expose({ list });
    return () =>
      h("pre", {
        "data-loading": String(list.loading.value),
        "data-loading-more": String(list.loadingMore.value),
        "data-items": String(list.items.value.length),
        "data-q": list.query.value.q,
        "data-sort": list.query.value.sort,
        "data-city": list.query.value.city,
        "data-theme": String(list.query.value.themeId),
        "data-type": list.query.value.itemType,
        "data-error": list.error.value ? "yes" : "no",
        "data-has-more": String(list.hasMore.value),
      });
  },
});

interface ExposedVm {
  list: UseArtistsListReturn;
}

let router: Router;
let wrapper: VueWrapper;

function vm(): ExposedVm {
  return wrapper.vm as unknown as ExposedVm;
}

async function mountList(initialPath = "/ar/artists") {
  setActivePinia(createPinia());
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: "/:locale/artists",
        name: "artists.index",
        component: DummyPage,
      },
    ],
  });
  await router.push(initialPath);
  await router.isReady();
  wrapper = mount(DummyPage, { global: { plugins: [router, i18n] } });
  await flushPromises();
}

describe("useArtistsList", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.useRealTimers();
    vi.mocked(listArtists).mockResolvedValue(paginated);
  });

  afterEach(() => {
    wrapper?.unmount();
    vi.useRealTimers();
  });

  it("maps URL query to API params for Arabic locale", async () => {
    await mountList("/ar/artists?q=inji&sort=materials&city=القاهرة&theme=5&type=image");
    expect(listArtists).toHaveBeenCalledWith(
      {
        q: "inji",
        sort: "-materials_count",
        city: "القاهرة",
        theme_id: 5,
        item_type: "image",
        per_page: 100,
        page: 1,
        include_facets: 1,
      },
      expect.any(AbortSignal),
    );
  });

  it("uses English alphabetical sort in English locale", async () => {
    await mountList("/en/artists");
    expect(listArtists).toHaveBeenCalledWith(
      expect.objectContaining({ sort: "name_en" }),
      expect.any(AbortSignal),
    );
  });

  it("omits default filters from the API params", async () => {
    await mountList("/ar/artists");
    expect(listArtists).toHaveBeenCalledWith(
      {
        sort: "name_ar",
        per_page: 100,
        page: 1,
        include_facets: 1,
      },
      expect.any(AbortSignal),
    );
  });

  it("exposes fetched items and meta", async () => {
    await mountList();
    expect(wrapper.attributes("data-items")).toBe("1");
    expect(wrapper.attributes("data-loading")).toBe("false");
  });

  it("resets filters when a new filter is set", async () => {
    await mountList("/ar/artists?city=جدة");
    vm().list.setCity("الرياض");
    await flushPromises();
    expect(router.currentRoute.value.query).toEqual({ city: "الرياض" });
  });

  it("debounces search input before writing to the URL", async () => {
    vi.useFakeTimers();
    await mountList("/ar/artists");

    vm().list.setSearch("a");
    vm().list.setSearch("ab");
    vm().list.setSearch("abc");
    await vi.advanceTimersByTimeAsync(ARTIST_SEARCH_DEBOUNCE_MS - 1);
    expect(router.currentRoute.value.query.q).toBeUndefined();
    await vi.advanceTimersByTimeAsync(1);
    expect(router.currentRoute.value.query.q).toBe("abc");
  });

  it("aborts stale requests when the query changes", async () => {
    const signals: AbortSignal[] = [];
    vi.mocked(listArtists).mockImplementation((_params, signal) => {
      signals.push(signal as AbortSignal);
      return new Promise(() => {});
    });

    await mountList("/ar/artists?q=one");
    await router.push({ query: { q: "two" } });
    await flushPromises();

    expect(signals.length).toBe(2);
    expect(signals[0].aborted).toBe(true);
    expect(signals[1].aborted).toBe(false);
  });

  it("surfaces errors and clears them on retry", async () => {
    vi.mocked(listArtists).mockRejectedValueOnce(new Error("boom"));
    await mountList();
    expect(wrapper.attributes("data-error")).toBe("yes");

    vi.mocked(listArtists).mockResolvedValueOnce(paginated);
    await vm().list.retry();
    await flushPromises();
    expect(wrapper.attributes("data-error")).toBe("no");
    expect(wrapper.attributes("data-items")).toBe("1");
  });

  it("accumulates results on load more", async () => {
    const page1 = {
      ...paginated,
      data: [{ ...artist, id: 1 }],
      meta: { ...paginated.meta, current_page: 1, last_page: 2, total: 2 },
    };
    const page2 = {
      ...paginated,
      data: [{ ...artist, id: 2 }],
      meta: { ...paginated.meta, current_page: 2, last_page: 2, total: 2 },
    };

    vi.mocked(listArtists)
      .mockResolvedValueOnce(page1)
      .mockResolvedValueOnce(page2);

    await mountList();
    expect(wrapper.attributes("data-items")).toBe("1");
    expect(wrapper.attributes("data-has-more")).toBe("true");

    await vm().list.loadMore();
    await flushPromises();

    expect(wrapper.attributes("data-items")).toBe("2");
    expect(wrapper.attributes("data-loading-more")).toBe("false");
  });

  it("clears all filters", async () => {
    await mountList("/ar/artists?q=one&city=جدة&theme=3&type=image&sort=materials");
    vm().list.clearFilters();
    await flushPromises();
    expect(router.currentRoute.value.query).toEqual({});
  });
});

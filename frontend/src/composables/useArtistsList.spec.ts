import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { flushPromises, mount, type VueWrapper } from "@vue/test-utils";
import { defineComponent, h } from "vue";
import { createMemoryHistory, createRouter, type Router } from "vue-router";
import { createPinia, setActivePinia } from "pinia";

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
};

const paginated: PaginatedResponse<ArtistListItem> = {
  data: [artist],
  links: [],
  meta: {
    current_page: 1,
    last_page: 3,
    per_page: 24,
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
        "data-items": String(list.items.value.length),
        "data-q": list.query.value.q,
        "data-page": String(list.query.value.page),
        "data-error": list.error.value ? "yes" : "no",
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
  wrapper = mount(DummyPage, { global: { plugins: [router] } });
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

  it("maps URL query to API params", async () => {
    await mountList("/ar/artists?q=inji&sort=recent&verified=1&page=2");
    expect(listArtists).toHaveBeenCalledWith(
      {
        q: "inji",
        sort: "-created_at",
        verified_status: "verified",
        page: 2,
      },
      expect.any(AbortSignal),
    );
  });

  it("omits default filters from the API params", async () => {
    await mountList("/ar/artists");
    expect(listArtists).toHaveBeenCalledWith(
      { page: 1 },
      expect.any(AbortSignal),
    );
  });

  it("exposes fetched items and meta", async () => {
    await mountList();
    expect(wrapper.attributes("data-items")).toBe("1");
    expect(wrapper.attributes("data-loading")).toBe("false");
  });

  it("resets the page to 1 when a filter changes", async () => {
    await mountList("/ar/artists?page=2");
    vm().list.setVerifiedOnly(true);
    await flushPromises();
    expect(router.currentRoute.value.query).toEqual({ verified: "1" });
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
});

import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import AppLayout from "@/layouts/AppLayout.vue";
import { useAuthStore } from "@/stores/auth";
import { mountWithPlugins } from "@/test/utils";

const NAMES: [string, string][] = [
  ["home", ""], ["artists.index", "/artists"], ["artists.show", "/artists/:slug"], ["artworks.index", "/artworks"], ["archive.records", "/archive"],
  ["admin.archive", "/admin/archive"], ["timeline", "/timeline"], ["dashboard", "/dashboard"], ["proposals", "/proposals"], ["admin.activity", "/admin/activity"],
  ["admin.artists", "/admin/artists"], ["admin.artworks", "/admin/artworks"], ["admin.events", "/admin/events"], ["admin.materials", "/admin/materials"],
  ["admin.imports", "/admin/imports"], ["login", "/login"], ["submit", "/submit"], ["methodology", "/about/methodology"],
];

let router: Router;
let pinia: ReturnType<typeof createPinia>;

function signIn(permissions: string[] | null) {
  pinia = createPinia();
  setActivePinia(pinia);
  if (permissions) useAuthStore().$patch({ user: { id: 1, name: "E", email: "e@x", roles: [], permissions } as never, initialized: true });
}

async function mountAt(path: string, locale: "en" | "ar" = "en") {
  await router.push(path);
  const wrapper = mountWithPlugins(AppLayout, { locale, router, pinia });
  await flushPromises();
  return wrapper;
}

const nav = (w: Awaited<ReturnType<typeof mountAt>>) => w.get("[data-testid=main-nav]");
const link = (w: Awaited<ReturnType<typeof mountAt>>, key: string) => nav(w).get(`[data-testid=nav-${key}]`);

beforeEach(() => {
  signIn(null);
  router = createRouter({
    history: createMemoryHistory(),
    routes: NAMES.map(([name, path]) => ({ path: `/:locale${path}`, name, component: { template: "<div />" } })),
  });
});

describe("AppLayout main menu (visitor)", () => {
  it("links every public section to a real page, none to a page anchor", async () => {
    const wrapper = await mountAt("/en");

    expect(nav(wrapper).findAll("a").map((a) => a.text())).toEqual(["Artists", "Artworks", "Archive", "Events", "Themes"]);
    expect(link(wrapper, "artists").attributes("href")).toBe("/en/artists");
    expect(link(wrapper, "artworks").attributes("href")).toBe("/en/artworks");
    expect(link(wrapper, "archive").attributes("href")).toBe("/en/archive");
    expect(link(wrapper, "events").attributes("href")).toBe("/en/timeline");
    // "Themes" is a section of the home page, reachable from any page, and is the only hash link.
    expect(link(wrapper, "themes").attributes("href")).toMatch(/^\/en\/?#themes$/);
    for (const a of nav(wrapper).findAll("a")) expect(a.attributes("href")).not.toMatch(/^#/);
  });

  it("only marks the page you are on, not Archive all the time", async () => {
    const onArtist = await mountAt("/en/artists/ahmad");
    expect(link(onArtist, "artists").attributes("aria-current")).toBe("page");
    expect(link(onArtist, "archive").attributes("aria-current")).toBeUndefined();

    const onArchive = await mountAt("/en/archive?type=image");
    expect(link(onArchive, "archive").attributes("aria-current")).toBe("page");
    expect(link(onArchive, "artists").attributes("aria-current")).toBeUndefined();
  });

  it("marks nothing as current on the home page, and never the Themes section link", async () => {
    const wrapper = await mountAt("/en");

    expect(nav(wrapper).findAll("[aria-current=page]")).toHaveLength(0);
    expect(link(wrapper, "themes").attributes("aria-current")).toBeUndefined();
  });

  it("uses the current language in every link", async () => {
    const wrapper = await mountAt("/ar", "ar");

    expect(link(wrapper, "artists").attributes("href")).toBe("/ar/artists");
    expect(link(wrapper, "artists").text()).toBe("الفنانون");
    expect(link(wrapper, "events").text()).toBe("الفعاليات");
  });

  it("does not show any staff tools to a visitor", async () => {
    const wrapper = await mountAt("/en");

    for (const key of ["dashboard", "proposals", "registry", "imports", "materials"]) expect(nav(wrapper).find(`[data-testid=nav-${key}]`).exists()).toBe(false);
  });
});

describe("AppLayout main menu (signed in)", () => {
  it("keeps the public sections and adds only the staff tools the user's permissions allow", async () => {
    signIn(["artists.manage", "archive.manage", "materials.review"]);
    const wrapper = await mountAt("/en");

    const keys = nav(wrapper).findAll("a").map((a) => a.attributes("data-testid"));
    expect(keys.slice(0, 5)).toEqual(["nav-artists", "nav-artworks", "nav-archive", "nav-events", "nav-themes"]);
    expect(keys).toEqual(expect.arrayContaining(["nav-dashboard", "nav-proposals", "nav-registry", "nav-materials"]));
    expect(keys).not.toContain("nav-imports");
    expect(keys).not.toContain("nav-artworkRegistry");
    // A manager's Archive is the working registry, not the public page.
    expect(link(wrapper, "archive").attributes("href")).toBe("/en/admin/archive");
  });

  it("gives a contributor their suggestions page and nothing administrative", async () => {
    signIn(["proposals.submit"]);
    const wrapper = await mountAt("/en");

    expect(link(wrapper, "proposals").text()).toBe("My suggestions");
    expect(nav(wrapper).find("[data-testid=nav-registry]").exists()).toBe(false);
    expect(link(wrapper, "archive").attributes("href")).toBe("/en/archive");
  });
});

describe("AppLayout mobile menu", () => {
  it("is closed until opened, then lists the same links", async () => {
    const wrapper = await mountAt("/en");
    const toggle = wrapper.get("[data-testid=menu-toggle]");

    expect(wrapper.find("[data-testid=mobile-menu]").exists()).toBe(false);
    expect(toggle.attributes("aria-expanded")).toBe("false");

    await toggle.trigger("click");
    const menu = wrapper.get("[data-testid=mobile-menu]");
    expect(toggle.attributes("aria-expanded")).toBe("true");
    expect(menu.findAll("a").map((a) => a.text())).toEqual(["Artists", "Artworks", "Archive", "Events", "Themes"]);
    expect(menu.findAll("a")[2].attributes("href")).toBe("/en/archive");
  });

  it("closes after choosing a link, and on Escape", async () => {
    const wrapper = await mountAt("/en");
    await wrapper.get("[data-testid=menu-toggle]").trigger("click");

    await wrapper.get("[data-testid=mobile-menu] a").trigger("click");
    await flushPromises();
    expect(wrapper.find("[data-testid=mobile-menu]").exists()).toBe(false);

    await wrapper.get("[data-testid=menu-toggle]").trigger("click");
    expect(wrapper.find("[data-testid=mobile-menu]").exists()).toBe(true);
    window.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape" }));
    await flushPromises();
    expect(wrapper.find("[data-testid=mobile-menu]").exists()).toBe(false);
  });
});

describe("AppLayout footer", () => {
  it("links Methodology to its page, and For owners to the submission form", async () => {
    const wrapper = await mountAt("/en");
    const footer = wrapper.get("footer");
    const hrefOf = (text: string) => footer.findAll("a").find((a) => a.text() === text)?.attributes("href");

    expect(hrefOf("Methodology")).toBe("/en/about/methodology");
    expect(hrefOf("For owners")).toBe("/en/submit");
  });
});


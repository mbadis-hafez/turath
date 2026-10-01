import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import AppLayout from "@/layouts/AppLayout.vue";
import { useAuthStore } from "@/stores/auth";
import { mountWithPlugins } from "@/test/utils";

const NAMES: [string, string][] = [
  ["home", ""], ["artists.index", "/artists"], ["artists.show", "/artists/:slug"], ["artworks.index", "/artworks"], ["archive.records", "/archive"],
  ["admin.archive", "/admin/archive"], ["timeline", "/timeline"], ["dashboard", "/dashboard"], ["proposals", "/proposals"], ["admin.activity", "/admin/activity"], ["admin.users", "/admin/users"],
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

const ALL_PERMISSIONS = ["proposals.submit", "activity.view", "artists.manage", "artworks.manage", "events.manage", "archive.manage", "materials.review", "imports.manage"];
const tools = (w: Awaited<ReturnType<typeof mountAt>>) => w.get("[data-testid=tools-menu]");

describe("AppLayout main menu (signed in)", () => {
  it("puts the public sections behind a Browse button and the dashboard in the bar, with the staff tools behind one Workspace button", async () => {
    signIn(["artists.manage", "archive.manage", "materials.review"]);
    const wrapper = await mountAt("/en");

    const bar = nav(wrapper).findAll(":scope > a").map((a) => a.attributes("data-testid"));
    expect(bar).toEqual(["nav-dashboard"]);
    expect(nav(wrapper).get("[data-testid=browse-toggle]").text()).toContain("Browse");
    expect(nav(wrapper).get("[data-testid=tools-toggle]").text()).toContain("Workspace");

    await nav(wrapper).get("[data-testid=browse-toggle]").trigger("click");
    const browse = wrapper.get("[data-testid=browse-menu]");
    expect(browse.findAll("a").map((a) => a.attributes("data-testid"))).toEqual(["nav-artists", "nav-artworks", "nav-archive", "nav-events", "nav-themes"]);
    // A manager's Archive is the working registry, not the public page.
    expect(browse.get("[data-testid=nav-archive]").attributes("href")).toBe("/en/admin/archive");
  });

  it("keeps the top bar short however many permissions the user has", async () => {
    signIn(ALL_PERMISSIONS);
    const wrapper = await mountAt("/en");

    // Just the dashboard inline, plus one Browse button and one Workspace button: it fits, instead of thirteen links in a row.
    expect(nav(wrapper).findAll(":scope > a")).toHaveLength(1);
    expect(nav(wrapper).findAll("[data-testid=browse-toggle]")).toHaveLength(1);
    expect(nav(wrapper).findAll("[data-testid=tools-toggle]")).toHaveLength(1);
  });

  it("opens the Workspace menu on click with exactly the tools the permissions allow, grouped by area", async () => {
    signIn(["artists.manage", "archive.manage", "materials.review"]);
    const wrapper = await mountAt("/en");
    const toggle = wrapper.get("[data-testid=tools-toggle]");

    expect(wrapper.find("[data-testid=tools-menu]").exists()).toBe(false);
    expect(toggle.attributes("aria-expanded")).toBe("false");

    await toggle.trigger("click");
    expect(toggle.attributes("aria-expanded")).toBe("true");
    const menu = tools(wrapper);
    expect(menu.findAll("a").map((a) => a.text())).toEqual(["Review queue", "Submitted material", "Artists registry", "Archive"]);
    expect(menu.findAll("[data-testid=tools-menu] > li > p").map((p) => p.text())).toEqual(["Review", "Catalog"]);
    expect(menu.find("[data-testid=nav-imports]").exists()).toBe(false);
    expect(menu.find("[data-testid=nav-artworkRegistry]").exists()).toBe(false);
    expect(menu.get("[data-testid=nav-registry]").attributes("href")).toBe("/en/admin/artists");
    expect(menu.get("[data-testid=nav-archive]").attributes("href")).toBe("/en/admin/archive");
  });

  it("gives administration its own group for user managers", async () => {
    signIn(["users.manage", "activity.view"]);
    const wrapper = await mountAt("/en");
    await wrapper.get("[data-testid=tools-toggle]").trigger("click");

    const menu = tools(wrapper);
    expect(menu.findAll("a").map((a) => a.text())).toEqual(["Users", "Activity log"]);
    expect(menu.findAll("[data-testid=tools-menu] > li > p").map((p) => p.text())).toEqual(["Administration"]);
  });

  it("closes the Workspace menu on Escape, on a click elsewhere, and after choosing a tool", async () => {
    signIn(ALL_PERMISSIONS);
    const wrapper = await mountAt("/en");
    const open = async () => {
      if (!wrapper.find("[data-testid=tools-menu]").exists()) await wrapper.get("[data-testid=tools-toggle]").trigger("click");
      expect(wrapper.find("[data-testid=tools-menu]").exists()).toBe(true);
    };

    await open();
    window.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape" }));
    await flushPromises();
    expect(wrapper.find("[data-testid=tools-menu]").exists()).toBe(false);

    await open();
    document.body.click();
    await flushPromises();
    expect(wrapper.find("[data-testid=tools-menu]").exists()).toBe(false);

    await open();
    await tools(wrapper).get("[data-testid=nav-activity]").trigger("click");
    await flushPromises();
    expect(wrapper.find("[data-testid=tools-menu]").exists()).toBe(false);
  });

  it("marks the Workspace button as current while you are on one of its pages", async () => {
    signIn(ALL_PERMISSIONS);
    const onTool = await mountAt("/en/admin/events");
    expect(onTool.get("[data-testid=tools-toggle]").classes()).toContain("text-accent");

    const elsewhere = await mountAt("/en/artists");
    expect(elsewhere.get("[data-testid=tools-toggle]").classes()).not.toContain("text-accent");
  });

  it("closes the Browse menu on Escape, on a click elsewhere, and after choosing a section", async () => {
    signIn(ALL_PERMISSIONS);
    const wrapper = await mountAt("/en");
    const open = async () => {
      if (!wrapper.find("[data-testid=browse-menu]").exists()) await wrapper.get("[data-testid=browse-toggle]").trigger("click");
      expect(wrapper.find("[data-testid=browse-menu]").exists()).toBe(true);
    };

    await open();
    window.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape" }));
    await flushPromises();
    expect(wrapper.find("[data-testid=browse-menu]").exists()).toBe(false);

    await open();
    document.body.click();
    await flushPromises();
    expect(wrapper.find("[data-testid=browse-menu]").exists()).toBe(false);

    await open();
    await wrapper.get("[data-testid=browse-menu]").get("[data-testid=nav-archive]").trigger("click");
    await flushPromises();
    expect(wrapper.find("[data-testid=browse-menu]").exists()).toBe(false);
  });

  it("marks the Browse button as current while you are on a public section", async () => {
    signIn(ALL_PERMISSIONS);
    const onPublic = await mountAt("/en/artists");
    expect(onPublic.get("[data-testid=browse-toggle]").classes()).toContain("text-accent");

    const elsewhere = await mountAt("/en/dashboard");
    expect(elsewhere.get("[data-testid=browse-toggle]").classes()).not.toContain("text-accent");
  });

  it("gives a contributor their one tool as a plain link, not a menu of one", async () => {
    signIn(["proposals.submit"]);
    const wrapper = await mountAt("/en");

    expect(nav(wrapper).find("[data-testid=tools-toggle]").exists()).toBe(false);
    expect(link(wrapper, "proposals").text()).toBe("My suggestions");
    expect(link(wrapper, "dashboard").attributes("href")).toBe("/en/dashboard");

    await nav(wrapper).get("[data-testid=browse-toggle]").trigger("click");
    expect(link(wrapper, "archive").attributes("href")).toBe("/en/archive");
  });

  it("gives a reviewer the proposals tool labeled as the review queue, without record-manage permissions", async () => {
    signIn(["review_queue.second_source_needed"]);
    const wrapper = await mountAt("/en");

    expect(link(wrapper, "proposals").text()).toBe("Review queue");
    expect(nav(wrapper).find("[data-testid=tools-toggle]").exists()).toBe(false);
  });

  it("gives a signed-in user with no tools just the Browse menu and their dashboard", async () => {
    signIn([]);
    const wrapper = await mountAt("/en");

    expect(nav(wrapper).findAll(":scope > a").map((a) => a.attributes("data-testid"))).toEqual(["nav-dashboard"]);
    expect(nav(wrapper).get("[data-testid=browse-toggle]")).toBeTruthy();
    expect(nav(wrapper).find("[data-testid=tools-toggle]").exists()).toBe(false);

    await nav(wrapper).get("[data-testid=browse-toggle]").trigger("click");
    expect(wrapper.get("[data-testid=browse-menu]").findAll("a").map((a) => a.attributes("data-testid"))).toEqual(["nav-artists", "nav-artworks", "nav-archive", "nav-events", "nav-themes"]);
  });

  it("says 'my dashboard' in Arabic, as in the design (لوحتي)", async () => {
    signIn([]);
    const wrapper = await mountAt("/ar", "ar");

    expect(link(wrapper, "dashboard").text()).toBe("لوحتي");
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

describe("AppLayout mobile menu (signed in)", () => {
  it("lists the sections and dashboard first, then the tools under a Workspace heading", async () => {
    signIn(ALL_PERMISSIONS);
    const wrapper = await mountAt("/en");
    await wrapper.get("[data-testid=menu-toggle]").trigger("click");

    const menu = wrapper.get("[data-testid=mobile-menu]");
    expect(menu.get("[data-testid=mobile-tools-heading]").text()).toBe("Workspace");
    const labels = menu.findAll("a").map((a) => a.text());
    expect(labels.slice(0, 6)).toEqual(["Artists", "Artworks", "Archive", "Events", "Themes", "Dashboard"]);
    expect(labels.slice(6)).toEqual(expect.arrayContaining(["Review queue", "Activity log", "Artists registry", "Artworks registry", "Events registry", "Submitted material", "Imports"]));
    expect(menu.findAll("p").map((p) => p.text())).toEqual(expect.arrayContaining(["Workspace", "Review", "Catalog", "Administration"]));
  });
});

describe("AppLayout footer", () => {
  it("links Methodology to its page, and For owners to the submission form", async () => {
    const wrapper = await mountAt("/en");
    const footer = wrapper.get("footer");
    const hrefOf = (text: string) => footer.findAll("a").find((a) => a.text() === text)?.attributes("href");

    expect(hrefOf("Methodology")).toBe("/en/about/methodology");
    expect(hrefOf("For owners")).toBe("/en/submit");
    expect(hrefOf("For researchers")).toBe("/en/submit?role=researcher");
  });
});

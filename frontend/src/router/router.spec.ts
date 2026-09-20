import { beforeEach, describe, expect, it, vi } from "vitest";
import { flushPromises, mount } from "@vue/test-utils";
import { createMemoryHistory } from "vue-router";
import { createPinia, setActivePinia, type Pinia } from "pinia";

import App from "@/App.vue";
import { i18n } from "@/i18n";
import { createAppRouter } from "@/router/index";
import { useAuthStore } from "@/stores/auth";
import type { User } from "@/types/api";
import type { ActivityEntry } from "@/types/activity";

vi.mock("@/api/activity", () => ({
  fetchActivity: vi.fn(),
}));

import { fetchActivity } from "@/api/activity";

const feedEntry: ActivityEntry = {
  id: 1,
  event: "updated",
  subject_type: "Artist",
  subject_id: 7,
  subject_label: "Inji Efflatoun",
  causer: { id: 3, name: "Mona" },
  edit_summary: "Fixed dates",
  changes: [],
  created_at: new Date().toISOString(),
};

const paginated = {
  data: [feedEntry],
  links: [],
  meta: {
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 1,
    from: 1,
    to: 1,
  },
};

const editor: User = {
  id: 1,
  name: "Editor",
  email: "editor@example.com",
  roles: ["editor"],
  permissions: ["activity.view"],
};

const reader: User = {
  id: 2,
  name: "Reader",
  email: "reader@example.com",
  roles: ["reader"],
  permissions: [],
};

type AppRouterInstance = ReturnType<typeof createAppRouter>;

async function buildApp(
  user: User | null,
  initialPath: string,
): Promise<{
  wrapper: ReturnType<typeof mount>;
  router: AppRouterInstance;
  pinia: Pinia;
}> {
  const pinia = createPinia();
  setActivePinia(pinia);
  const auth = useAuthStore(pinia);
  auth.user = user;
  auth.initialized = true;

  const router = createAppRouter(createMemoryHistory());
  await router.push(initialPath);
  await router.isReady();

  const wrapper = mount(App, {
    global: { plugins: [pinia, i18n, router] },
  });
  await flushPromises();

  return { wrapper, router, pinia };
}

describe("router", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(fetchActivity).mockResolvedValue(paginated);
  });

  it("/ redirects to the default /ar", async () => {
    const { router } = await buildApp(null, "/");
    expect(router.currentRoute.value.path).toBe("/ar");
  });

  it("unknown paths render the not-found page", async () => {
    const { router, wrapper } = await buildApp(null, "/en/nowhere");
    expect(router.currentRoute.value.name).toBe("not-found");
    const locale =
      router.currentRoute.value.params.locale === "en" ? "en" : "ar";
    const expected = locale === "en" ? "Page not found" : "الصفحة غير موجودة";
    expect(wrapper.text()).toContain(expected);
  });

  it("unknown locale segments fall back to the default locale", async () => {
    const { router } = await buildApp(null, "/fr/login");
    expect(router.currentRoute.value.path).toBe("/ar/login");
  });

  it("sets html lang and dir from the locale param", async () => {
    await buildApp(null, "/en");
    expect(document.documentElement.lang).toBe("en");
    expect(document.documentElement.dir).toBe("ltr");

    await buildApp(null, "/ar");
    expect(document.documentElement.lang).toBe("ar");
    expect(document.documentElement.dir).toBe("rtl");
  });

  it("persists the locale to localStorage", async () => {
    await buildApp(null, "/en");
    expect(localStorage.getItem("locale")).toBe("en");
  });

  it("redirects logged-out visitors of protected pages to login with a redirect query", async () => {
    const { router } = await buildApp(null, "/ar/admin/activity");
    expect(router.currentRoute.value.name).toBe("login");
    expect(router.currentRoute.value.query.redirect).toBe("/ar/admin/activity");
  });

  it("sends a logged-in user away from the login page to the dashboard", async () => {
    const { router } = await buildApp(reader, "/ar/login");
    expect(router.currentRoute.value.name).toBe("dashboard");
  });

  it("renders a 403 ErrorState for a reader without activity.view", async () => {
    const { wrapper } = await buildApp(reader, "/ar/admin/activity");
    expect(wrapper.text()).toContain("لا تملك صلاحية الوصول");
    expect(fetchActivity).not.toHaveBeenCalled();
  });

  it("renders the feed for an editor, passing URL query filters to the API", async () => {
    const { wrapper } = await buildApp(
      editor,
      "/ar/admin/activity?event=updated&subject_type=Artist&page=2",
    );

    expect(fetchActivity).toHaveBeenCalledWith(
      expect.objectContaining({
        event: "updated",
        subject_type: "Artist",
        page: 2,
      }),
      expect.any(AbortSignal),
    );
    expect(wrapper.text()).toContain("Inji Efflatoun");
  });
});

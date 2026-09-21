import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import UsersPage from "@/pages/admin/UsersPage.vue";
import { useAuthStore } from "@/stores/auth";
import { mountWithPlugins } from "@/test/utils";
import type { AdminUserRow } from "@/types/user";

const api = vi.hoisted(() => ({ fetchAdminUsers: vi.fn(), deleteUser: vi.fn() }));
vi.mock("@/api/users", () => api);

const row = (id: number, roles: string[] = ["reader"], isActive = true): AdminUserRow => ({
  id, name: `User ${id}`, email: `user${id}@example.com`, roles, is_active: isActive,
});

const meta = { current_page: 1, last_page: 1, per_page: 24, total: 1, from: null, to: null };

let router: Router;
let pinia: ReturnType<typeof createPinia>;

function signIn(permissions: string[]) {
  pinia = createPinia();
  setActivePinia(pinia);
  useAuthStore().$patch({ user: { id: 1, name: "E", email: "e@x", roles: ["admin"], permissions } as never, initialized: true });
}

async function mountPage() {
  await router.push("/en/admin/users");
  const wrapper = mountWithPlugins(UsersPage, { locale: "en", router, pinia });
  await flushPromises();
  return wrapper;
}

beforeEach(() => {
  signIn(["users.manage"]);
  api.fetchAdminUsers.mockReset().mockResolvedValue({ data: [row(1)], links: [], meta });
  api.deleteUser.mockReset().mockResolvedValue(undefined);
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale/admin/users", name: "admin.users", component: UsersPage },
      { path: "/:locale/admin/users/new", name: "admin.users.new", component: UsersPage },
      { path: "/:locale/admin/users/:id(\\d+)", name: "admin.users.edit", component: UsersPage },
    ],
  });
});

describe("UsersPage", () => {
  it("is refused to users without users.manage", async () => {
    signIn([]);
    const wrapper = await mountPage();

    expect(wrapper.find("[data-testid=user-row]").exists()).toBe(false);
    expect(wrapper.text()).toContain("You don't have access to this page");
  });

  it("renders a row per user with name, email and role badges", async () => {
    api.fetchAdminUsers.mockResolvedValue({ data: [row(1, ["admin"]), row(2, ["editor", "reviewer"])], links: [], meta });
    const wrapper = await mountPage();

    const rows = wrapper.findAll("[data-testid=user-row]");
    expect(rows).toHaveLength(2);
    expect(rows[0]!.text()).toContain("User 1");
    expect(rows[0]!.text()).toContain("user1@example.com");
    expect(rows[0]!.text()).toContain("Admin");
    expect(rows[1]!.text()).toContain("Editor");
    expect(rows[1]!.text()).toContain("Reviewer");
  });

  it("filters by role through the select", async () => {
    const wrapper = await mountPage();
    expect(api.fetchAdminUsers.mock.calls[0]![0]).toEqual({ page: 1 });

    await wrapper.get("[data-testid=role-filter]").setValue("editor");
    await flushPromises();

    expect(api.fetchAdminUsers.mock.lastCall![0]).toEqual({ role: "editor", page: 1 });
    expect(router.currentRoute.value.query.role).toBe("editor");
  });

  it("links to the edit page except for the signed-in user's own row", async () => {
    api.fetchAdminUsers.mockResolvedValue({ data: [row(1), row(2)], links: [], meta });
    const wrapper = await mountPage();

    const rows = wrapper.findAll("[data-testid=user-row]");
    expect(rows[0]!.find("[data-testid=edit-user]").exists()).toBe(false);
    const edit = rows[1]!.find("[data-testid=edit-user]");
    expect(edit.exists()).toBe(true);
    expect(edit.attributes("href")).toContain("/en/admin/users/2");
  });

  it("marks inactive users with a badge", async () => {
    api.fetchAdminUsers.mockResolvedValue({ data: [row(1, ["reader"], false)], links: [], meta });
    const wrapper = await mountPage();

    expect(wrapper.find("[data-testid=inactive-badge]").exists()).toBe(true);
  });

  it("offers delete for others but not for the signed-in user or superadmins", async () => {
    api.fetchAdminUsers.mockResolvedValue({
      data: [row(1), row(2), row(3, ["superadmin"])],
      links: [],
      meta,
    });
    const wrapper = await mountPage();

    const rows = wrapper.findAll("[data-testid=user-row]");
    expect(rows[0]!.find("[data-testid=delete-user]").exists()).toBe(false);
    expect(rows[1]!.find("[data-testid=delete-user]").exists()).toBe(true);
    expect(rows[2]!.find("[data-testid=delete-user]").exists()).toBe(false);
  });

  it("asks in a dialog before deleting, then deletes and reloads the list", async () => {
    api.fetchAdminUsers.mockResolvedValue({ data: [row(1), row(2)], links: [], meta });
    const wrapper = await mountPage();
    const callsBefore = api.fetchAdminUsers.mock.calls.length;

    await wrapper.findAll("[data-testid=user-row]")[1]!.get("[data-testid=delete-user]").trigger("click");
    await flushPromises();

    const dialog = document.querySelector("[data-testid=confirm-dialog-confirm]");
    expect(dialog).not.toBeNull();
    expect(document.body.textContent).toContain("User 2");

    (dialog as HTMLElement).click();
    await flushPromises();

    expect(api.deleteUser).toHaveBeenCalledWith(2);
    expect(api.fetchAdminUsers.mock.calls.length).toBeGreaterThan(callsBefore);
  });

  it("does not delete when the dialog is cancelled", async () => {
    api.fetchAdminUsers.mockResolvedValue({ data: [row(1), row(2)], links: [], meta });
    const wrapper = await mountPage();

    await wrapper.findAll("[data-testid=user-row]")[1]!.get("[data-testid=delete-user]").trigger("click");
    await flushPromises();

    const cancel = document.querySelector("[data-testid=confirm-dialog-cancel]");
    expect(cancel).not.toBeNull();
    (cancel as HTMLElement).click();
    await flushPromises();

    expect(api.deleteUser).not.toHaveBeenCalled();
    expect(document.querySelector("[data-testid=confirm-dialog-confirm]")).toBeNull();
  });

  it("puts the search in the URL after a pause, then fetches once with it", async () => {
    vi.useFakeTimers();
    try {
      const wrapper = await mountPage();
      const box = wrapper.get("[data-testid=users-search]");

      await box.setValue("sa");
      await box.setValue("samar");
      await vi.advanceTimersByTimeAsync(300);
      await flushPromises();

      expect(router.currentRoute.value.query.search).toBe("samar");
      const lastCall = api.fetchAdminUsers.mock.lastCall![0] as Record<string, unknown>;
      expect(lastCall.search).toBe("samar");
      expect(lastCall.page).toBe(1);
    } finally {
      vi.useRealTimers();
    }
  });
});

import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import RolesPage from "@/pages/admin/RolesPage.vue";
import { useAuthStore } from "@/stores/auth";
import { mountWithPlugins } from "@/test/utils";
import type { Role } from "@/types/role";

const api = vi.hoisted(() => ({ listRoles: vi.fn() }));
vi.mock("@/api/roles", () => api);

const role = (patch: Partial<Role> = {}): Role => ({
  id: 1, name: "editor", display_name: { ar: "محرر", en: "Editor" }, description: { ar: null, en: "Edits records" },
  is_built_in: true, permissions_editable: true, users_count: 3, permissions_count: 18, ...patch,
});

let router: Router;
let pinia: ReturnType<typeof createPinia>;

function signIn(permissions: string[]) {
  pinia = createPinia();
  setActivePinia(pinia);
  useAuthStore().$patch({ user: { id: 1, name: "E", email: "e@x", roles: ["admin"], permissions } as never, initialized: true });
}

async function mountPage() {
  await router.push("/en/admin/roles");
  const wrapper = mountWithPlugins(RolesPage, { locale: "en", router, pinia });
  await flushPromises();
  return wrapper;
}

beforeEach(() => {
  signIn(["roles.manage"]);
  api.listRoles.mockReset().mockResolvedValue([role()]);
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale/admin/roles", name: "admin.roles", component: RolesPage },
      { path: "/:locale/admin/roles/new", name: "admin.roles.new", component: RolesPage },
      { path: "/:locale/admin/roles/:id(\\d+)", name: "admin.roles.edit", component: RolesPage },
    ],
  });
});

describe("RolesPage", () => {
  it("is refused to a caller without roles.manage", async () => {
    signIn([]);
    const wrapper = await mountPage();

    expect(wrapper.find("[data-testid=role-row]").exists()).toBe(false);
    expect(wrapper.text()).toContain("You don't have access to this page");
  });

  it("marks a built-in role and shows its explanation, with zero rendered rather than blank", async () => {
    api.listRoles.mockResolvedValue([role({ users_count: 0 })]);
    const wrapper = await mountPage();

    const row = wrapper.get("[data-testid=role-row]");
    expect(row.find("[data-testid=built-in-badge]").exists()).toBe(true);
    expect(row.find("[data-testid=custom-badge]").exists()).toBe(false);
    expect(row.get("[data-testid=users-count]").text()).toContain("0");
  });

  it("marks a custom role distinctly from a built-in one", async () => {
    api.listRoles.mockResolvedValue([role({ id: 2, name: "archivist", is_built_in: false, users_count: 0, permissions_count: 2 })]);
    const wrapper = await mountPage();

    const row = wrapper.get("[data-testid=role-row]");
    expect(row.find("[data-testid=custom-badge]").exists()).toBe(true);
    expect(row.find("[data-testid=built-in-badge]").exists()).toBe(false);
  });

  it("shows both user and permission counts per role", async () => {
    const wrapper = await mountPage();

    const row = wrapper.get("[data-testid=role-row]");
    expect(row.get("[data-testid=users-count]").text()).toContain("3");
    expect(row.get("[data-testid=permissions-count]").text()).toContain("18");
  });

  it("offers a link to create a new role", async () => {
    const wrapper = await mountPage();
    expect(wrapper.find("[data-testid=add-role]").exists()).toBe(true);
  });
});

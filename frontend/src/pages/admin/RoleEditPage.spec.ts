import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import RoleEditPage from "@/pages/admin/RoleEditPage.vue";
import { useAuthStore } from "@/stores/auth";
import { mountWithPlugins } from "@/test/utils";
import { ApiError } from "@/types/api";
import type { RoleDetail } from "@/types/role";

const api = vi.hoisted(() => ({
  getRole: vi.fn(), getPermissionCatalogue: vi.fn(), createRole: vi.fn(), updateRole: vi.fn(), deleteRole: vi.fn(),
}));
vi.mock("@/api/roles", () => api);

const catalogue = {
  data: [
    { group: "artworks", permissions: [{ name: "artworks.manage", label: { ar: "إدارة الأعمال", en: "Manage artworks" } }] },
    { group: "administration", permissions: [{ name: "users.manage", label: { ar: "إدارة المستخدمين", en: "Manage users" } }] },
  ],
  meta: { grantable: ["artworks.manage"] },
};

const customRole: RoleDetail = {
  id: 5, name: "archivist", display_name: { ar: "أمين أرشيف", en: "Archivist" }, description: { ar: null, en: null },
  is_built_in: false, permissions_editable: true, users_count: 0, permissions_count: 1, updated_at: "2026-09-23T10:00:00Z", permissions: ["artworks.manage"],
};

const builtInRole: RoleDetail = {
  id: 6, name: "editor", display_name: { ar: "محرر", en: "Editor" }, description: { ar: null, en: "Edits records" },
  is_built_in: true, permissions_editable: true, users_count: 3, permissions_count: 1, updated_at: "2026-09-23T10:00:00Z", permissions: ["artworks.manage"],
};

const fullyLockedRole: RoleDetail = {
  id: 7, name: "superadmin", display_name: { ar: "مدير أعلى", en: "Superadmin" }, description: { ar: null, en: "Holds every permission" },
  is_built_in: true, permissions_editable: false, users_count: 1, permissions_count: 2, updated_at: "2026-09-23T10:00:00Z", permissions: ["artworks.manage", "users.manage"],
};

let router: Router;
let pinia: ReturnType<typeof createPinia>;

function signIn(permissions: string[]) {
  pinia = createPinia();
  setActivePinia(pinia);
  useAuthStore().$patch({ user: { id: 1, name: "E", email: "e@x", roles: ["admin"], permissions } as never, initialized: true });
}

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale/admin/roles", name: "admin.roles", component: RoleEditPage },
      { path: "/:locale/admin/roles/new", name: "admin.roles.new", component: RoleEditPage },
      { path: "/:locale/admin/roles/:id(\\d+)", name: "admin.roles.edit", component: RoleEditPage },
    ],
  });
}

async function mountNew() {
  await router.push("/en/admin/roles/new");
  const wrapper = mountWithPlugins(RoleEditPage, { locale: "en", router, pinia });
  await flushPromises();
  return wrapper;
}

async function mountEdit(id: number) {
  await router.push(`/en/admin/roles/${id}`);
  const wrapper = mountWithPlugins(RoleEditPage, { locale: "en", router, pinia });
  await flushPromises();
  return wrapper;
}

beforeEach(() => {
  signIn(["roles.manage", "artworks.manage"]);
  api.getPermissionCatalogue.mockReset().mockResolvedValue(catalogue);
  api.getRole.mockReset();
  api.createRole.mockReset().mockResolvedValue({ data: customRole });
  api.updateRole.mockReset().mockResolvedValue({ data: customRole });
  api.deleteRole.mockReset().mockResolvedValue({ data: { deleted: true } });
  router = makeRouter();
});

describe("RoleEditPage", () => {
  it("renders permissions grouped with bilingual labels, not raw identifiers", async () => {
    const wrapper = await mountNew();

    const text = wrapper.get("[data-testid=permission-groups]").text();
    expect(text).toContain("Manage artworks");
    expect(text).toContain("Manage users");
    expect(text).not.toContain("artworks.manage");
  });

  it("shows a non-grantable permission but disables it, rather than hiding it", async () => {
    const wrapper = await mountNew();

    const checkbox = wrapper.get<HTMLInputElement>("[data-testid=permission-users\\.manage]");
    expect(checkbox.element.disabled).toBe(true);
    expect(wrapper.get("[data-testid=permission-groups]").text()).toContain("Manage users");

    const grantableCheckbox = wrapper.get<HTMLInputElement>("[data-testid=permission-artworks\\.manage]");
    expect(grantableCheckbox.element.disabled).toBe(false);
  });

  it("renders a built-in role with its name/description locked but its permissions still editable", async () => {
    api.getRole.mockResolvedValue({ data: builtInRole });
    const wrapper = await mountEdit(6);

    expect(wrapper.find("[data-testid=built-in-badge]").exists()).toBe(true);
    expect(wrapper.find("[data-testid=built-in-explain]").exists()).toBe(true);
    expect(wrapper.get<HTMLInputElement>("[data-testid=role-name-en]").element.disabled).toBe(true);
    expect(wrapper.get<HTMLInputElement>("[data-testid=permission-artworks\\.manage]").element.disabled).toBe(false);
    expect(wrapper.find("[data-testid=role-submit]").exists()).toBe(true);
    expect(wrapper.find("[data-testid=delete-role]").exists()).toBe(false);
  });

  it("saves a permission change on a built-in role without touching its name or description", async () => {
    api.getRole.mockResolvedValue({ data: { ...builtInRole, users_count: 0 } });
    const wrapper = await mountEdit(6);

    await wrapper.get("[data-testid=permission-artworks\\.manage]").trigger("change");
    await wrapper.get("[data-testid=role-submit]").trigger("submit");
    await flushPromises();

    expect(api.updateRole).toHaveBeenCalledWith(6, expect.objectContaining({ permissions: [] }));
    expect(api.updateRole.mock.calls[0]![1]).not.toHaveProperty("name_ar");
    expect(api.updateRole.mock.calls[0]![1]).not.toHaveProperty("name_en");
  });

  it("renders a fully locked role (superadmin) fully read-only, with no save action", async () => {
    api.getRole.mockResolvedValue({ data: fullyLockedRole });
    const wrapper = await mountEdit(7);

    expect(wrapper.find("[data-testid=built-in-explain]").text()).toContain("fully protected");
    expect(wrapper.find("[data-testid=role-submit]").exists()).toBe(false);
    expect(wrapper.get<HTMLInputElement>("[data-testid=role-name-en]").element.disabled).toBe(true);
    expect(wrapper.get<HTMLInputElement>("[data-testid=permission-artworks\\.manage]").element.disabled).toBe(true);
  });

  it("creates a custom role with the selected permissions", async () => {
    const wrapper = await mountNew();
    await wrapper.get("[data-testid=role-name-ar]").setValue("أمين أرشيف");
    await wrapper.get("[data-testid=role-name-en]").setValue("Archivist");
    await wrapper.get("[data-testid=permission-artworks\\.manage]").trigger("change");

    await wrapper.get("[data-testid=role-submit]").trigger("submit");
    await flushPromises();

    expect(api.createRole).toHaveBeenCalledWith({
      name_ar: "أمين أرشيف", name_en: "Archivist", description_ar: null, description_en: null, permissions: ["artworks.manage"],
    });
    expect(router.currentRoute.value.name).toBe("admin.roles.edit");
  });

  it("asks how many users are affected before saving a permission change on a held role", async () => {
    api.getRole.mockResolvedValue({ data: { ...customRole, users_count: 4 } });
    const wrapper = await mountEdit(5);

    await wrapper.get("[data-testid=permission-artworks\\.manage]").trigger("change");
    await wrapper.get("[data-testid=role-submit]").trigger("submit");
    await flushPromises();

    expect(api.updateRole).not.toHaveBeenCalled();
    const dialog = document.querySelector("[data-testid=confirm-dialog-confirm]");
    expect(dialog).not.toBeNull();
    expect(document.body.textContent).toContain("4");

    (dialog as HTMLElement).click();
    await flushPromises();

    expect(api.updateRole).toHaveBeenCalled();
  });

  it("saves immediately when nothing but the name changes, no confirmation needed", async () => {
    api.getRole.mockResolvedValue({ data: { ...customRole, users_count: 4 } });
    const wrapper = await mountEdit(5);

    await wrapper.get("[data-testid=role-name-en]").setValue("Senior Archivist");
    await wrapper.get("[data-testid=role-submit]").trigger("submit");
    await flushPromises();

    expect(api.updateRole).toHaveBeenCalled();
    expect(document.querySelector("[data-testid=confirm-dialog-confirm]")).toBeNull();
  });

  it("shows a distinct message for a stale concurrency conflict and offers to reload", async () => {
    api.getRole.mockResolvedValue({ data: customRole });
    api.updateRole.mockRejectedValue(new ApiError("validation", "This role was changed by someone else since you loaded it. Reload and try again.", {
      status: 409,
      fieldErrors: { updated_at: ["This role was changed by someone else since you loaded it. Reload and try again."] },
    }));
    const wrapper = await mountEdit(5);

    await wrapper.get("[data-testid=role-name-en]").setValue("Renamed");
    await wrapper.get("[data-testid=role-submit]").trigger("submit");
    await flushPromises();

    expect(wrapper.find("[data-testid=stale-conflict]").exists()).toBe(true);
  });

  it("shows a distinct message for the escalation refusal", async () => {
    api.getRole.mockResolvedValue({ data: customRole });
    api.updateRole.mockRejectedValue(new ApiError("validation", "You cannot grant a permission you do not hold yourself.", {
      status: 422,
      fieldErrors: { permissions: ["You cannot grant a permission you do not hold yourself."] },
    }));
    const wrapper = await mountEdit(5);

    await wrapper.get("[data-testid=role-name-en]").setValue("Renamed");
    await wrapper.get("[data-testid=role-submit]").trigger("submit");
    await flushPromises();

    expect(wrapper.get("[data-testid=permissions-error]").text()).toContain("do not hold yourself");
    expect(wrapper.find("[data-testid=stale-conflict]").exists()).toBe(false);
  });

  it("offers delete only for an unheld custom role, and deletes on confirmation", async () => {
    api.getRole.mockResolvedValue({ data: { ...customRole, users_count: 0 } });
    const wrapper = await mountEdit(5);

    await wrapper.get("[data-testid=delete-role]").trigger("click");
    await flushPromises();
    (document.querySelector("[data-testid=confirm-dialog-confirm]") as HTMLElement).click();
    await flushPromises();

    expect(api.deleteRole).toHaveBeenCalledWith(5);
    expect(router.currentRoute.value.name).toBe("admin.roles");
  });

  it("explains rather than offers deletion when a custom role is still held", async () => {
    api.getRole.mockResolvedValue({ data: { ...customRole, users_count: 2 } });
    const wrapper = await mountEdit(5);

    expect(wrapper.find("[data-testid=delete-role]").exists()).toBe(false);
    expect(wrapper.get("[data-testid=delete-blocked]").text()).toContain("2");
  });

  it("is refused to a caller without roles.manage", async () => {
    signIn([]);
    const wrapper = await mountNew();

    expect(wrapper.text()).toContain("You don't have access to this page");
  });
});

import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import UserEditPage from "@/pages/admin/UserEditPage.vue";
import { useAuthStore } from "@/stores/auth";
import { mountWithPlugins } from "@/test/utils";
import { ApiError } from "@/types/api";
import type { AdminUserDetail } from "@/types/user";

const api = vi.hoisted(() => ({ fetchUser: vi.fn(), createUser: vi.fn(), updateUser: vi.fn(), sendInvitation: vi.fn() }));
vi.mock("@/api/users", () => api);

const detail: AdminUserDetail = {
  id: 2, name: "Samar", email: "samar@example.com", roles: ["reviewer"], permissions: [], is_active: true,
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
      { path: "/:locale/admin/users", name: "admin.users", component: UserEditPage },
      { path: "/:locale/admin/users/new", name: "admin.users.new", component: UserEditPage },
      { path: "/:locale/admin/users/:id(\\d+)", name: "admin.users.edit", component: UserEditPage },
    ],
  });
}

async function mountNew() {
  await router.push("/en/admin/users/new");
  const wrapper = mountWithPlugins(UserEditPage, { locale: "en", router, pinia });
  await flushPromises();
  return wrapper;
}

async function mountEdit(id = 2) {
  api.fetchUser.mockResolvedValue({ data: detail });
  await router.push(`/en/admin/users/${id}`);
  const wrapper = mountWithPlugins(UserEditPage, { locale: "en", router, pinia });
  await flushPromises();
  return wrapper;
}

beforeEach(() => {
  signIn(["users.manage"]);
  api.fetchUser.mockReset();
  api.createUser.mockReset().mockResolvedValue({ data: detail });
  api.updateUser.mockReset().mockResolvedValue({ data: detail });
  api.sendInvitation.mockReset().mockResolvedValue(undefined);
  router = makeRouter();
});

describe("UserEditPage", () => {
  it("is refused to users without users.manage", async () => {
    signIn([]);
    const wrapper = await mountNew();

    expect(wrapper.find("[data-testid=user-submit]").exists()).toBe(false);
    expect(wrapper.text()).toContain("You don't have access to this page");
  });

  it("creates a user and navigates back to the list", async () => {
    const wrapper = await mountNew();
    await wrapper.get("[data-testid=user-name]").setValue("Samar");
    await wrapper.get("[data-testid=user-email]").setValue("samar@example.com");
    await wrapper.get("[data-testid=user-password]").setValue("secret-pass");
    await wrapper.get("[data-testid=user-password-confirmation]").setValue("secret-pass");
    await wrapper.get("[data-testid=user-role]").setValue("reviewer");

    await wrapper.get("[data-testid=user-submit]").trigger("submit");
    await flushPromises();

    expect(api.createUser).toHaveBeenCalledWith({
      name: "Samar",
      email: "samar@example.com",
      role: "reviewer",
      is_active: true,
      password: "secret-pass",
      password_confirmation: "secret-pass",
    });
    expect(router.currentRoute.value.name).toBe("admin.users");
  });

  it("sends is_active false when the new user is set inactive", async () => {
    const wrapper = await mountNew();
    await wrapper.get("[data-testid=user-name]").setValue("Dormant");
    await wrapper.get("[data-testid=user-email]").setValue("dormant@example.com");
    await wrapper.get("[data-testid=user-password]").setValue("secret-pass");
    await wrapper.get("[data-testid=user-role]").setValue("reader");
    await wrapper.get("[data-testid=user-status]").setValue("inactive");

    await wrapper.get("[data-testid=user-submit]").trigger("submit");
    await flushPromises();

    expect(api.createUser).toHaveBeenCalledWith(expect.objectContaining({ is_active: false }));
  });

  it("includes send_invitation when the invitation box is checked", async () => {
    const wrapper = await mountNew();
    await wrapper.get("[data-testid=user-name]").setValue("Invited");
    await wrapper.get("[data-testid=user-email]").setValue("invited@example.com");
    await wrapper.get("[data-testid=user-password]").setValue("temp-password");
    await wrapper.get("[data-testid=user-role]").setValue("reader");
    await wrapper.get("[data-testid=send-invitation]").setValue(true);

    await wrapper.get("[data-testid=user-submit]").trigger("submit");
    await flushPromises();

    expect(api.createUser).toHaveBeenCalledWith(expect.objectContaining({ send_invitation: true }));
  });

  it("hydrates the form in edit mode and omits a blank password", async () => {
    const wrapper = await mountEdit();

    expect(api.fetchUser).toHaveBeenCalledWith(2);
    expect(wrapper.get<HTMLInputElement>("[data-testid=user-name]").element.value).toBe("Samar");
    expect(wrapper.get<HTMLSelectElement>("[data-testid=user-role]").element.value).toBe("reviewer");

    await wrapper.get("[data-testid=user-name]").setValue("Samar K");
    await wrapper.get("[data-testid=user-submit]").trigger("submit");
    await flushPromises();

    expect(api.updateUser).toHaveBeenCalledWith(2, {
      name: "Samar K",
      email: "samar@example.com",
      role: "reviewer",
      is_active: true,
    });
    expect(wrapper.find("[data-testid=user-saved]").exists()).toBe(true);
  });

  it("sends the new password when one is entered on edit", async () => {
    const wrapper = await mountEdit();
    await wrapper.get("[data-testid=user-password]").setValue("new-secret");
    await wrapper.get("[data-testid=user-password-confirmation]").setValue("new-secret");

    await wrapper.get("[data-testid=user-submit]").trigger("submit");
    await flushPromises();

    expect(api.updateUser.mock.lastCall![1]).toMatchObject({ password: "new-secret", password_confirmation: "new-secret" });
  });

  it("sends a fresh invitation from edit mode and confirms it", async () => {
    const wrapper = await mountEdit();

    await wrapper.get("[data-testid=send-invitation-button]").trigger("click");
    await flushPromises();

    expect(api.sendInvitation).toHaveBeenCalledWith(2);
    expect(wrapper.find("[data-testid=invitation-sent]").exists()).toBe(true);
  });

  it("shows an error when the invitation fails", async () => {
    api.sendInvitation.mockRejectedValue(new ApiError("server", "Could not send", { status: 500 }));
    const wrapper = await mountEdit();

    await wrapper.get("[data-testid=send-invitation-button]").trigger("click");
    await flushPromises();

    expect(wrapper.find("[data-testid=invitation-error]").exists()).toBe(true);
    expect(wrapper.text()).toContain("Could not send");
  });

  it("offers no invitation button when adding a user", async () => {
    const wrapper = await mountNew();

    expect(wrapper.find("[data-testid=send-invitation-button]").exists()).toBe(false);
  });

  it("hydrates the status select and posts the chosen value", async () => {
    api.fetchUser.mockResolvedValue({ data: { ...detail, is_active: false } });
    await router.push("/en/admin/users/2");
    const wrapper = mountWithPlugins(UserEditPage, { locale: "en", router, pinia });
    await flushPromises();

    expect(wrapper.get<HTMLSelectElement>("[data-testid=user-status]").element.value).toBe("inactive");

    await wrapper.get("[data-testid=user-status]").setValue("active");
    await wrapper.get("[data-testid=user-submit]").trigger("submit");
    await flushPromises();

    expect(api.updateUser.mock.lastCall![1]).toMatchObject({ is_active: true });
  });

  it("sends is_active false when the status is set to inactive", async () => {
    const wrapper = await mountEdit();
    await wrapper.get("[data-testid=user-status]").setValue("inactive");

    await wrapper.get("[data-testid=user-submit]").trigger("submit");
    await flushPromises();

    expect(api.updateUser.mock.lastCall![1]).toMatchObject({ is_active: false });
  });

  it("generates a password that fills both fields and reveals it", async () => {
    const wrapper = await mountNew();
    await wrapper.get("[data-testid=generate-password]").trigger("click");

    const password = wrapper.get<HTMLInputElement>("[data-testid=user-password]");
    const confirmation = wrapper.get<HTMLInputElement>("[data-testid=user-password-confirmation]");
    expect(password.element.value).not.toBe("");
    expect(password.element.value).toBe(confirmation.element.value);
    expect(password.element.type).toBe("text");
  });

  it("toggles password visibility", async () => {
    const wrapper = await mountNew();
    const toggle = wrapper.get("[data-testid=toggle-password]");

    expect(wrapper.get<HTMLInputElement>("[data-testid=user-password]").element.type).toBe("password");

    await toggle.trigger("click");
    expect(wrapper.get<HTMLInputElement>("[data-testid=user-password]").element.type).toBe("text");
    expect(wrapper.get<HTMLInputElement>("[data-testid=user-password-confirmation]").element.type).toBe("text");

    await toggle.trigger("click");
    expect(wrapper.get<HTMLInputElement>("[data-testid=user-password]").element.type).toBe("password");
  });

  it("renders server-side field errors", async () => {
    api.createUser.mockRejectedValue(new ApiError("validation", "The email has already been taken.", {
      status: 422,
      fieldErrors: { email: ["The email has already been taken."] },
    }));
    const wrapper = await mountNew();
    await wrapper.get("[data-testid=user-name]").setValue("Samar");
    await wrapper.get("[data-testid=user-email]").setValue("dupe@example.com");
    await wrapper.get("[data-testid=user-password]").setValue("secret-pass");
    await wrapper.get("[data-testid=user-role]").setValue("reader");

    await wrapper.get("[data-testid=user-submit]").trigger("submit");
    await flushPromises();

    expect(wrapper.text()).toContain("The email has already been taken.");
  });
});

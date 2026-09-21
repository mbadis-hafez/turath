import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import SetPasswordPage from "@/pages/SetPasswordPage.vue";
import { useAuthStore } from "@/stores/auth";
import { mountWithPlugins } from "@/test/utils";
import { ApiError } from "@/types/api";

const api = vi.hoisted(() => ({ changePassword: vi.fn(), fetchUser: vi.fn(), logout: vi.fn() }));
vi.mock("@/api/auth", () => api);

let router: Router;
let pinia: ReturnType<typeof createPinia>;

const flaggedUser = {
  id: 2, name: "Invited", email: "invited@example.com", roles: ["reader"], permissions: [], must_change_password: true,
};

function signIn() {
  pinia = createPinia();
  setActivePinia(pinia);
  useAuthStore().$patch({ user: { ...flaggedUser } as never, initialized: true });
}

async function mountPage(query: Record<string, string> = {}) {
  await router.push({ path: "/en/set-password", query });
  const wrapper = mountWithPlugins(SetPasswordPage, { locale: "en", router, pinia });
  await flushPromises();
  return wrapper;
}

beforeEach(() => {
  signIn();
  api.changePassword.mockReset().mockResolvedValue({ ...flaggedUser, must_change_password: false });
  api.fetchUser.mockReset().mockResolvedValue({ ...flaggedUser, must_change_password: false });
  api.logout.mockReset().mockResolvedValue(undefined);
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale/set-password", name: "set-password", component: SetPasswordPage },
      { path: "/:locale/login", name: "login", component: SetPasswordPage },
      { path: "/:locale/dashboard", name: "dashboard", component: SetPasswordPage },
    ],
  });
});

describe("SetPasswordPage", () => {
  it("renders the forced password change form", async () => {
    const wrapper = await mountPage();

    expect(wrapper.text()).toContain("Choose a new password");
    expect(wrapper.get("[data-testid=new-password]").element).toBeTruthy();
    expect(wrapper.get("[data-testid=confirm-password]").element).toBeTruthy();
  });

  it("changes the password, refreshes the user, and navigates to the redirect target", async () => {
    const wrapper = await mountPage({ redirect: "/en/dashboard" });
    await wrapper.get("[data-testid=new-password]").setValue("brand-new-password");
    await wrapper.get("[data-testid=confirm-password]").setValue("brand-new-password");

    await wrapper.get("[data-testid=set-password-submit]").trigger("submit");
    await flushPromises();

    expect(api.changePassword).toHaveBeenCalledWith("brand-new-password", "brand-new-password");
    expect(api.fetchUser).toHaveBeenCalled();
    expect(router.currentRoute.value.path).toBe("/en/dashboard");
  });

  it("renders server-side validation errors", async () => {
    api.changePassword.mockRejectedValue(new ApiError("validation", "The password field confirmation does not match.", {
      status: 422,
      fieldErrors: { password: ["The password must be at least 8 characters."] },
    }));
    const wrapper = await mountPage();
    await wrapper.get("[data-testid=new-password]").setValue("short");
    await wrapper.get("[data-testid=confirm-password]").setValue("short");

    await wrapper.get("[data-testid=set-password-submit]").trigger("submit");
    await flushPromises();

    expect(wrapper.text()).toContain("The password must be at least 8 characters.");
    expect(api.fetchUser).not.toHaveBeenCalled();
  });

  it("can sign out instead", async () => {
    const wrapper = await mountPage();

    await wrapper.get("[data-testid=set-password-logout]").trigger("click");
    await flushPromises();

    expect(api.logout).toHaveBeenCalled();
    expect(router.currentRoute.value.name).toBe("login");
  });
});

import { flushPromises } from "@vue/test-utils";
import { createPinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import LoginPage from "@/pages/LoginPage.vue";
import { mountWithPlugins } from "@/test/utils";

vi.mock("@/api/auth", () => ({ login: vi.fn(), fetchUser: vi.fn(), logout: vi.fn() }));

let router: Router;

async function mountPage(query: Record<string, string> = {}) {
  await router.push({ path: "/en/login", query });
  const wrapper = mountWithPlugins(LoginPage, { locale: "en", router, pinia: createPinia() });
  await flushPromises();
  return wrapper;
}

beforeEach(() => {
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale/login", name: "login", component: LoginPage },
      { path: "/:locale/forgot-password", name: "forgot-password", component: LoginPage },
    ],
  });
});

describe("LoginPage password reset links", () => {
  it("carries the address typed so far to the forgot-password page", async () => {
    const wrapper = await mountPage();
    expect(wrapper.get("[data-testid=forgot-password-link]").attributes("href")).toBe("/en/forgot-password");

    await wrapper.get("#email").setValue("reader@example.test");

    expect(wrapper.get("[data-testid=forgot-password-link]").attributes("href")).toBe("/en/forgot-password?email=reader@example.test");
  });

  it("confirms a completed reset and fills in the address", async () => {
    const wrapper = await mountPage({ reset: "1", email: "reader@example.test" });

    expect(wrapper.get("[data-testid=password-reset-notice]").text()).toBe("Your password has been reset. Sign in with your new password.");
    expect((wrapper.get("#email").element as HTMLInputElement).value).toBe("reader@example.test");
  });

  it("shows no notice on an ordinary visit", async () => {
    const wrapper = await mountPage();

    expect(wrapper.find("[data-testid=password-reset-notice]").exists()).toBe(false);
  });
});

import { flushPromises } from "@vue/test-utils";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import ResetPasswordPage from "@/pages/ResetPasswordPage.vue";
import { mountWithPlugins } from "@/test/utils";
import { ApiError } from "@/types/api";

const api = vi.hoisted(() => ({ resetPassword: vi.fn() }));
vi.mock("@/api/auth", () => api);

let router: Router;

const link = { token: "synthetic-token", email: "reader@example.test" };

async function mountPage(query: Record<string, string> = link) {
  await router.push({ path: "/en/reset-password", query });
  const wrapper = mountWithPlugins(ResetPasswordPage, { locale: "en", router });
  await flushPromises();
  return wrapper;
}

async function fillAndSubmit(wrapper: Awaited<ReturnType<typeof mountPage>>, password = "a-brand-new-password") {
  await wrapper.get("[data-testid=new-password]").setValue(password);
  await wrapper.get("[data-testid=confirm-password]").setValue(password);
  await wrapper.get("form").trigger("submit");
  await flushPromises();
}

beforeEach(() => {
  api.resetPassword.mockReset().mockResolvedValue(undefined);
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale/reset-password", name: "reset-password", component: ResetPasswordPage },
      { path: "/:locale/forgot-password", name: "forgot-password", component: ResetPasswordPage },
      { path: "/:locale/login", name: "login", component: ResetPasswordPage },
    ],
  });
});

describe("ResetPasswordPage", () => {
  it("saves the new password with the link's token and address, then sends the person to sign in", async () => {
    const wrapper = await mountPage();
    expect(wrapper.text()).toContain("reader@example.test");

    await fillAndSubmit(wrapper);

    expect(api.resetPassword).toHaveBeenCalledWith({
      token: "synthetic-token",
      email: "reader@example.test",
      password: "a-brand-new-password",
      passwordConfirmation: "a-brand-new-password",
    });
    expect(router.currentRoute.value.name).toBe("login");
    expect(router.currentRoute.value.params.locale).toBe("en");
    expect(router.currentRoute.value.query).toEqual({ reset: "1", email: "reader@example.test" });
  });

  it("explains an incomplete link straight away instead of showing the form", async () => {
    const wrapper = await mountPage({ email: "reader@example.test" });

    expect(wrapper.find("form").exists()).toBe(false);
    expect(wrapper.get("[data-testid=reset-link-invalid]").text()).toContain("This link can't be used");
    expect(wrapper.get("[data-testid=reset-request-new]").attributes("href")).toBe("/en/forgot-password?email=reader@example.test");
  });

  it("explains a used or expired link when the server rejects the token", async () => {
    api.resetPassword.mockRejectedValue(new ApiError("validation", "This password reset link is invalid or has expired.", {
      status: 422,
      fieldErrors: { token: ["This password reset link is invalid or has expired."] },
    }));
    const wrapper = await mountPage();

    await fillAndSubmit(wrapper);

    expect(wrapper.find("form").exists()).toBe(false);
    expect(wrapper.get("[data-testid=reset-link-invalid]").text()).toContain("invalid or has expired");
    expect(router.currentRoute.value.name).toBe("reset-password");
  });

  it("keeps the form and shows password errors in place", async () => {
    api.resetPassword.mockRejectedValue(new ApiError("validation", "The password field must be at least 8 characters.", {
      status: 422,
      fieldErrors: { password: ["The password field must be at least 8 characters."] },
    }));
    const wrapper = await mountPage();

    await fillAndSubmit(wrapper, "short");

    expect(wrapper.find("[data-testid=reset-link-invalid]").exists()).toBe(false);
    expect(wrapper.text()).toContain("The password field must be at least 8 characters.");
    expect(router.currentRoute.value.name).toBe("reset-password");
  });

  it("reports a network failure without leaving the form", async () => {
    api.resetPassword.mockRejectedValue(new ApiError("network", "Network failure"));
    const wrapper = await mountPage();

    await fillAndSubmit(wrapper);

    expect(wrapper.get("[role=alert]").text()).toBe("Could not reach the server");
    expect(wrapper.find("form").exists()).toBe(true);
  });
});

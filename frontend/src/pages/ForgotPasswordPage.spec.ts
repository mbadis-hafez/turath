import { flushPromises } from "@vue/test-utils";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import ForgotPasswordPage from "@/pages/ForgotPasswordPage.vue";
import { mountWithPlugins } from "@/test/utils";
import { ApiError } from "@/types/api";

const api = vi.hoisted(() => ({ requestPasswordReset: vi.fn() }));
vi.mock("@/api/auth", () => api);

let router: Router;

async function mountPage(query: Record<string, string> = {}) {
  await router.push({ path: "/en/forgot-password", query });
  const wrapper = mountWithPlugins(ForgotPasswordPage, { locale: "en", router });
  await flushPromises();
  return wrapper;
}

beforeEach(() => {
  api.requestPasswordReset.mockReset().mockResolvedValue(undefined);
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale/forgot-password", name: "forgot-password", component: ForgotPasswordPage },
      { path: "/:locale/login", name: "login", component: ForgotPasswordPage },
    ],
  });
});

describe("ForgotPasswordPage", () => {
  it("prefills the address passed from the sign-in page", async () => {
    const wrapper = await mountPage({ email: "reader@example.test" });

    expect((wrapper.get("[data-testid=reset-email]").element as HTMLInputElement).value).toBe("reader@example.test");
  });

  it("requests a link and confirms where it went without saying whether the account exists", async () => {
    const wrapper = await mountPage();
    await wrapper.get("[data-testid=reset-email]").setValue("reader@example.test");

    await wrapper.get("form").trigger("submit");
    await flushPromises();

    expect(api.requestPasswordReset).toHaveBeenCalledWith("reader@example.test");
    const sent = wrapper.get("[data-testid=reset-link-sent]");
    expect(sent.text()).toContain("Check your email");
    expect(sent.text()).toContain("If an account uses this address");
    expect(sent.text()).toContain("reader@example.test");
  });

  it("lets the person go back and use a different address", async () => {
    const wrapper = await mountPage();
    await wrapper.get("[data-testid=reset-email]").setValue("reader@example.test");
    await wrapper.get("form").trigger("submit");
    await flushPromises();

    await wrapper.get("[data-testid=reset-try-again]").trigger("click");

    expect(wrapper.find("[data-testid=reset-link-sent]").exists()).toBe(false);
    expect((wrapper.get("[data-testid=reset-email]").element as HTMLInputElement).value).toBe("reader@example.test");
  });

  it("shows the server's error for a malformed address", async () => {
    api.requestPasswordReset.mockRejectedValue(new ApiError("validation", "The email field must be a valid email address.", {
      status: 422,
      fieldErrors: { email: ["The email field must be a valid email address."] },
    }));
    const wrapper = await mountPage();
    await wrapper.get("[data-testid=reset-email]").setValue("not-an-email");

    await wrapper.get("form").trigger("submit");
    await flushPromises();

    expect(wrapper.text()).toContain("The email field must be a valid email address.");
    expect(wrapper.find("[role=alert]").exists()).toBe(false);
    expect(wrapper.find("[data-testid=reset-link-sent]").exists()).toBe(false);
  });

  it("says when there have been too many attempts", async () => {
    api.requestPasswordReset.mockRejectedValue(new ApiError("throttled", "Too Many Attempts.", { status: 429 }));
    const wrapper = await mountPage();
    await wrapper.get("[data-testid=reset-email]").setValue("reader@example.test");

    await wrapper.get("form").trigger("submit");
    await flushPromises();

    expect(wrapper.get("[role=alert]").text()).toBe("Too many attempts, try again later");
  });
});

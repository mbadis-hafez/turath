import { beforeEach, describe, expect, it, vi } from "vitest";
import { createPinia, setActivePinia } from "pinia";

import { ApiError, type User } from "@/types/api";

vi.mock("@/api/auth", () => ({
  fetchUser: vi.fn(),
  login: vi.fn(),
  logout: vi.fn(),
}));

import * as authApi from "@/api/auth";
import { useAuthStore } from "@/stores/auth";

const user: User = {
  id: 1,
  name: "Editor",
  email: "editor@example.com",
  roles: ["editor"],
  permissions: ["activity.view"],
  must_change_password: false,
};

describe("stores/auth", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    setActivePinia(createPinia());
  });

  it("login success stores the user and marks the session initialized", async () => {
    vi.mocked(authApi.login).mockResolvedValue(user);
    const auth = useAuthStore();

    await auth.login("editor@example.com", "secret");

    expect(authApi.login).toHaveBeenCalledWith("editor@example.com", "secret");
    expect(auth.user).toEqual(user);
    expect(auth.initialized).toBe(true);
    expect(auth.isAuthenticated).toBe(true);
  });

  it("login failure leaves the user logged out and rethrows", async () => {
    const failure = new ApiError("validation", "Invalid credentials", {
      status: 422,
    });
    vi.mocked(authApi.login).mockRejectedValue(failure);
    const auth = useAuthStore();

    await expect(auth.login("a@b.c", "wrong")).rejects.toBe(failure);
    expect(auth.user).toBeNull();
    expect(auth.isAuthenticated).toBe(false);
  });

  it("fetchUser treats 401 as logged out, not an error", async () => {
    vi.mocked(authApi.fetchUser).mockRejectedValue(
      new ApiError("authentication", "Unauthenticated", { status: 401 }),
    );
    const auth = useAuthStore();
    auth.user = user;

    await auth.fetchUser();

    expect(auth.user).toBeNull();
    expect(auth.initialized).toBe(true);
    expect(auth.isAuthenticated).toBe(false);
  });

  it("exposes can() and hasRole() helpers", async () => {
    vi.mocked(authApi.fetchUser).mockResolvedValue(user);
    const auth = useAuthStore();
    await auth.fetchUser();

    expect(auth.can("activity.view")).toBe(true);
    expect(auth.can("users.manage")).toBe(false);
    expect(auth.hasRole("editor")).toBe(true);
    expect(auth.hasRole("admin")).toBe(false);
  });

  it("logout clears the user even if the request fails", async () => {
    vi.mocked(authApi.logout).mockImplementation(() =>
      Promise.reject(new ApiError("network", "offline")),
    );
    const auth = useAuthStore();
    auth.user = user;

    await auth.logout();

    expect(auth.user).toBeNull();
  });
});

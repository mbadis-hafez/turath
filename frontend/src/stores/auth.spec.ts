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

  it("can() reflects a permission granted after the initial load, once refetched", async () => {
    // Simulates FR-008: an administrator grants a permission mid-session.
    // The interface only learns about it by calling fetchUser() again — this
    // is what the router guard does on navigation into a permission-gated
    // route (research R4), not tested here since that's routing, not store
    // behaviour; this asserts the store side actually updates when it does.
    vi.mocked(authApi.fetchUser).mockResolvedValueOnce(user);
    const auth = useAuthStore();
    await auth.fetchUser();
    expect(auth.can("roles.manage")).toBe(false);

    vi.mocked(authApi.fetchUser).mockResolvedValueOnce({ ...user, permissions: [...user.permissions, "roles.manage"] });
    await auth.fetchUser();

    expect(auth.can("roles.manage")).toBe(true);
  });

  it("can() reflects a permission revoked after the initial load, once refetched", async () => {
    vi.mocked(authApi.fetchUser).mockResolvedValueOnce({ ...user, permissions: [...user.permissions, "roles.manage"] });
    const auth = useAuthStore();
    await auth.fetchUser();
    expect(auth.can("roles.manage")).toBe(true);

    vi.mocked(authApi.fetchUser).mockResolvedValueOnce(user);
    await auth.fetchUser();

    expect(auth.can("roles.manage")).toBe(false);
  });

  it("a refetch failure after the session is established keeps the last-known user, not logs out", async () => {
    // Guards against a real bug found while wiring T060: navigating into a
    // permission-gated route refetches the user, and a transient network
    // error there must not sign someone out mid-session the way an initial
    // bootstrap failure does.
    vi.mocked(authApi.fetchUser).mockResolvedValueOnce(user);
    const auth = useAuthStore();
    await auth.fetchUser();
    expect(auth.user).toEqual(user);

    vi.mocked(authApi.fetchUser).mockRejectedValueOnce(new Error("network blip"));
    await auth.fetchUser();

    expect(auth.user).toEqual(user);
    expect(auth.isAuthenticated).toBe(true);
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

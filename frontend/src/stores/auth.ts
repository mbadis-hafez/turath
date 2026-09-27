import { defineStore } from "pinia";

import * as authApi from "@/api/auth";
import { ApiError, type User } from "@/types/api";

export const useAuthStore = defineStore("auth", {
  state: () => ({
    user: null as User | null,
    initialized: false,
  }),
  getters: {
    isAuthenticated: (state) => state.user !== null,
    can: (state) => (permission: string) =>
      state.user?.permissions.includes(permission) ?? false,
    hasRole: (state) => (role: string) =>
      state.user?.roles.includes(role) ?? false,
  },
  actions: {
    /**
     * Loads the current user at startup, and is refetched by the router on
     * navigation into permission-gated routes (research.md R4). A 401 always
     * means "logged out". Any other error only logs the user out during the
     * initial bootstrap — once a session is established, a transient refetch
     * failure (e.g. a network blip while navigating) must not sign someone
     * out from under them; it just leaves the last-known permissions in place.
     */
    async fetchUser(): Promise<void> {
      try {
        this.user = await authApi.fetchUser();
      } catch (error) {
        if (error instanceof ApiError && error.kind === "authentication") {
          this.user = null;
        } else if (!this.initialized) {
          this.user = null;
          console.error("Failed to bootstrap user session", error);
        } else {
          console.error("Failed to refresh user session", error);
        }
      } finally {
        this.initialized = true;
      }
    },
    async login(email: string, password: string): Promise<void> {
      this.user = await authApi.login(email, password);
      this.initialized = true;
    },
    async logout(): Promise<void> {
      try {
        await authApi.logout();
      } catch {
        // The session may already be invalid server-side; always clear locally.
      } finally {
        this.user = null;
      }
    },
  },
});

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
    /** Loads the current user once at startup. A 401 simply means "logged out". */
    async fetchUser(): Promise<void> {
      try {
        this.user = await authApi.fetchUser();
      } catch (error) {
        if (error instanceof ApiError && error.kind === "authentication") {
          this.user = null;
        } else {
          this.user = null;
          console.error("Failed to bootstrap user session", error);
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

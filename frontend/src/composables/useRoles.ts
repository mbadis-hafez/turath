import { ref } from "vue";

import { listRoles } from "@/api/roles";
import type { Role } from "@/types/role";

/**
 * The full roles list, fetched once per composable instance. Backs the roles
 * screen and the role pickers on the user screens — a single source so a
 * newly created custom role is immediately assignable everywhere (FR-018).
 */
export function useRoles() {
  const roles = ref<Role[]>([]);
  const loading = ref(false);
  const error = ref<unknown>(null);

  async function load(): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
      roles.value = await listRoles();
    } catch (err) {
      error.value = err;
    } finally {
      loading.value = false;
    }
  }

  void load();

  return { roles, loading, error, retry: load };
}

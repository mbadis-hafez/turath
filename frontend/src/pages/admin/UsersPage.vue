<script setup lang="ts">
import { computed, ref } from "vue";
import { useI18n } from "vue-i18n";

import EmptyState from "@/components/common/EmptyState.vue";
import ErrorState from "@/components/common/ErrorState.vue";
import ConfirmDialog from "@/components/common/ConfirmDialog.vue";
import Pagination from "@/components/common/Pagination.vue";
import Spinner from "@/components/common/Spinner.vue";
import { useAdminUsers } from "@/composables/useAdminUsers";
import { useLocalePath } from "@/composables/useLocalePath";
import { deleteUser } from "@/api/users";
import { useAuthStore } from "@/stores/auth";
import { ApiError } from "@/types/api";
import { ADMIN_ROLES } from "@/types/user";

const { t } = useI18n();
const { localePath } = useLocalePath();
const auth = useAuthStore();

const forbidden = new ApiError("forbidden", "Forbidden", { status: 403 });
const canManage = computed(() => auth.can("users.manage"));

const {
  items, meta, loading, error, query, searchInput, retry,
  setSearch, setRole, setPage, clear,
} = useAdminUsers();

const deletingId = ref<number | null>(null);
const deleteError = ref<string | null>(null);
const pendingDelete = ref<{ id: number; name: string } | null>(null);
// Captured separately from the ref: closing the dialog (cancel or the confirm action itself)
// fires the cancel path, so the ref may already be null when the confirm handler runs.
let pendingDeleteRow: { id: number; name: string } | null = null;

function isSelf(rowId: number): boolean {
  return rowId === auth.user?.id;
}

function isSuperadmin(row: { roles: string[] }): boolean {
  return row.roles.includes("superadmin");
}

async function remove(row: { id: number; name: string }): Promise<void> {
  pendingDeleteRow = row;
  pendingDelete.value = row;
  deleteError.value = null;
}

async function confirmRemove(): Promise<void> {
  const row = pendingDeleteRow;
  if (row === null) return;
  deletingId.value = row.id;
  deleteError.value = null;
  try {
    await deleteUser(row.id);
    pendingDeleteRow = null;
    pendingDelete.value = null;
    await retry();
  } catch (err) {
    deleteError.value = err instanceof Error ? err.message : t("errors.generic");
  } finally {
    deletingId.value = null;
  }
}

function onDialogCancel(): void {
  // Only the dialog's open state is reset here; the captured row survives, because this
  // also fires as a side effect of the confirm action's close.
  pendingDelete.value = null;
}

const ROLE_BADGE_CLASS: Record<string, string> = {
  superadmin: "bg-danger-soft text-danger",
  admin: "bg-accent-soft text-accent-strong",
  editor: "bg-success-soft text-success",
  reviewer: "bg-warn-soft text-warn",
};

function roleClass(role: string): string {
  return ROLE_BADGE_CLASS[role] ?? "bg-neutral-soft text-ink-muted";
}

const hasFilters = computed(() => query.value.search !== "" || query.value.role !== "");
</script>

<template>
  <section>
    <ErrorState v-if="!canManage" :error="forbidden" />
    <template v-else>
      <div class="flex flex-wrap items-start justify-between gap-4 border-b-2 border-ink pb-6">
        <div>
          <h1 class="text-balance text-3xl font-semibold tracking-tight text-ink">{{ t("users.title") }}</h1>
          <p v-if="meta" class="mt-1 text-sm text-ink-muted">{{ t("users.subtitle", { count: meta.total }) }}</p>
        </div>
        <RouterLink :to="localePath('admin.users.new')" class="rounded-md bg-ink px-4 py-2 text-sm font-semibold text-paper hover:bg-ink/85" data-testid="add-user">
          {{ t("users.add") }}
        </RouterLink>
      </div>

      <div class="mt-6 flex flex-wrap items-center gap-2">
        <input
          :value="searchInput"
          type="search"
          :placeholder="t('users.searchPlaceholder')"
          class="min-w-64 flex-1 rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none"
          data-testid="users-search"
          @input="setSearch(($event.target as HTMLInputElement).value)"
        />
        <select
          :value="query.role"
          class="rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none"
          :aria-label="t('users.allRoles')"
          data-testid="role-filter"
          @change="setRole(($event.target as HTMLSelectElement).value)"
        >
          <option value="">{{ t("users.allRoles") }}</option>
          <option v-for="role in ADMIN_ROLES" :key="role" :value="role">{{ t(`users.roles.${role}`) }}</option>
        </select>
      </div>

      <div class="mt-6">
        <ErrorState v-if="error" :error="error" @retry="retry" />
        <Spinner v-else-if="loading && items.length === 0" class="mx-auto my-12 block" />
        <EmptyState v-else-if="items.length === 0" :title="t('users.empty')" :description="t('users.emptyHelp')">
          <button v-if="hasFilters" type="button" class="mt-4 rounded-md bg-accent px-4 py-2 text-sm font-medium text-surface hover:bg-accent-strong" @click="clear">
            {{ t("users.clear") }}
          </button>
        </EmptyState>
        <template v-else>
          <div class="overflow-x-auto">
            <table class="w-full text-start text-sm">
              <thead class="border-b border-line text-xs text-ink-muted">
                <tr>
                  <th class="px-3 py-2 text-start font-medium">{{ t("users.columns.name") }}</th>
                  <th class="px-3 py-2 text-start font-medium">{{ t("users.columns.email") }}</th>
                  <th class="px-3 py-2 text-start font-medium">{{ t("users.columns.roles") }}</th>
                  <th class="px-3 py-2 text-start font-medium"><span class="sr-only">{{ t("users.editLink") }}</span></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="u in items" :key="u.id" class="border-b border-line align-top hover:bg-neutral-soft/50" data-testid="user-row">
                  <td class="px-3 py-3 text-base font-semibold text-ink">
                    {{ u.name }}
                    <span v-if="!u.is_active" class="ms-2 rounded-sm bg-neutral-soft px-1.5 py-0.5 text-xs font-medium text-ink-muted" data-testid="inactive-badge">{{ t("users.inactive") }}</span>
                  </td>
                  <td class="px-3 py-3 text-ink-muted">{{ u.email }}</td>
                  <td class="px-3 py-3">
                    <ul class="flex flex-wrap gap-1">
                      <li v-for="role in u.roles" :key="role" class="rounded-sm px-1.5 py-0.5 text-xs font-medium" :class="roleClass(role)">
                        {{ t(`users.roles.${role}`) }}
                      </li>
                    </ul>
                  </td>
                  <td class="px-3 py-3">
                    <span class="flex items-center gap-3">
                      <RouterLink v-if="!isSelf(u.id)" :to="localePath('admin.users.edit', { id: u.id })" class="text-sm font-medium text-accent hover:underline" data-testid="edit-user">
                        {{ t("users.editLink") }}
                      </RouterLink>
                      <button v-if="!isSelf(u.id) && !isSuperadmin(u)" type="button" class="text-sm font-medium text-danger hover:underline disabled:opacity-50" :disabled="deletingId === u.id" data-testid="delete-user" @click="remove(u)">
                        {{ deletingId === u.id ? t("users.deleting") : t("users.delete") }}
                      </button>
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <Pagination class="mt-6" :meta="meta" @change="setPage" />
        </template>
      </div>

      <ConfirmDialog
        :open="pendingDelete !== null"
        :title="pendingDelete ? t('users.deleteTitle', { name: pendingDelete.name }) : ''"
        :description="t('users.deleteDescription')"
        :confirm-label="deletingId !== null ? t('users.deleting') : t('users.delete')"
        :cancel-label="t('users.edit.cancel')"
        :busy="deletingId !== null"
        :error="deleteError"
        @confirm="confirmRemove"
        @cancel="onDialogCancel"
      />
    </template>
  </section>
</template>

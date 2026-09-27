<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import EmptyState from "@/components/common/EmptyState.vue";
import ErrorState from "@/components/common/ErrorState.vue";
import Spinner from "@/components/common/Spinner.vue";
import { useLocalePath } from "@/composables/useLocalePath";
import { useLocalized } from "@/composables/useLocalized";
import { useRoles } from "@/composables/useRoles";
import { useAuthStore } from "@/stores/auth";
import { ApiError } from "@/types/api";

const { t } = useI18n();
const { pick } = useLocalized();
const { localePath } = useLocalePath();
const auth = useAuthStore();

const forbidden = new ApiError("forbidden", "Forbidden", { status: 403 });
const canManage = computed(() => auth.can("roles.manage"));

const { roles, loading, error, retry } = useRoles();
</script>

<template>
  <section>
    <ErrorState v-if="!canManage" :error="forbidden" />
    <template v-else>
      <div class="flex flex-wrap items-start justify-between gap-4 border-b-2 border-ink pb-6">
        <div>
          <h1 class="text-balance text-3xl font-semibold tracking-tight text-ink">{{ t("roles.title") }}</h1>
          <p class="mt-1 text-sm text-ink-muted">{{ t("roles.subtitle") }}</p>
        </div>
        <RouterLink :to="localePath('admin.roles.new')" class="rounded-md bg-ink px-4 py-2 text-sm font-semibold text-paper hover:bg-ink/85" data-testid="add-role">
          {{ t("roles.add") }}
        </RouterLink>
      </div>

      <div class="mt-6">
        <ErrorState v-if="error" :error="error" @retry="retry" />
        <Spinner v-else-if="loading && roles.length === 0" class="mx-auto my-12 block" />
        <EmptyState v-else-if="roles.length === 0" :title="t('roles.empty')" :description="t('roles.emptyHelp')" />
        <ul v-else class="mt-2 divide-y divide-line border-y border-line">
          <li v-for="role in roles" :key="role.id" class="flex flex-wrap items-start justify-between gap-4 py-4" data-testid="role-row">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <RouterLink :to="localePath('admin.roles.edit', { id: role.id })" class="text-base font-semibold text-ink hover:underline">
                  {{ pick(role.display_name)?.text }}
                </RouterLink>
                <span v-if="role.is_built_in" class="rounded-sm bg-neutral-soft px-1.5 py-0.5 text-xs font-medium text-ink-muted" data-testid="built-in-badge">
                  {{ t("roles.builtIn") }}
                </span>
                <span v-else class="rounded-sm bg-accent-soft px-1.5 py-0.5 text-xs font-medium text-accent-strong" data-testid="custom-badge">
                  {{ t("roles.custom") }}
                </span>
              </div>
              <p v-if="pick(role.description)?.text" class="mt-1 text-sm text-ink-muted">{{ pick(role.description)?.text }}</p>
              <p v-if="role.is_built_in && !role.permissions_editable" class="mt-1 text-xs text-ink-muted">{{ t("roles.fullyLockedExplain") }}</p>
              <p v-else-if="role.is_built_in" class="mt-1 text-xs text-ink-muted">{{ t("roles.builtInExplain") }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-4 text-sm tabular-nums text-ink-muted">
              <span data-testid="users-count">{{ t("roles.usersCount", { count: role.users_count }) }}</span>
              <span data-testid="permissions-count">{{ t("roles.permissionsCount", { count: role.permissions_count }) }}</span>
            </div>
          </li>
        </ul>
      </div>
    </template>
  </section>
</template>

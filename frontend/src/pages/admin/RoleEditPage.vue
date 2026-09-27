<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";

import ConfirmDialog from "@/components/common/ConfirmDialog.vue";
import ErrorState from "@/components/common/ErrorState.vue";
import Spinner from "@/components/common/Spinner.vue";
import { createRole, deleteRole, getPermissionCatalogue, getRole, updateRole } from "@/api/roles";
import { useLocalePath } from "@/composables/useLocalePath";
import { useLocalized } from "@/composables/useLocalized";
import { useAuthStore } from "@/stores/auth";
import { ApiError } from "@/types/api";
import type { PermissionGroup, RoleDetail } from "@/types/role";

const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const { pick } = useLocalized();
const { localePath } = useLocalePath();
const auth = useAuthStore();

const forbidden = new ApiError("forbidden", "Forbidden", { status: 403 });
const canManage = computed(() => auth.can("roles.manage"));

const id = computed(() => (route.params.id ? Number(route.params.id) : null));
const isNew = computed(() => id.value === null);

const role = ref<RoleDetail | null>(null);
const groups = ref<PermissionGroup[]>([]);
const grantable = ref<string[]>([]);
const loading = ref(false);
const loadError = ref<unknown>(null);

const form = reactive({ nameAr: "", nameEn: "", descriptionAr: "", descriptionEn: "" });
const selected = ref<Set<string>>(new Set());

function loadForm(r: RoleDetail): void {
  form.nameAr = r.display_name.ar ?? "";
  form.nameEn = r.display_name.en ?? "";
  form.descriptionAr = r.description.ar ?? "";
  form.descriptionEn = r.description.en ?? "";
  selected.value = new Set(r.permissions);
}

async function load(): Promise<void> {
  loading.value = true;
  loadError.value = null;
  try {
    const catalogue = await getPermissionCatalogue();
    groups.value = catalogue.data;
    grantable.value = catalogue.meta.grantable;
    if (id.value !== null) {
      role.value = (await getRole(id.value)).data;
      loadForm(role.value);
    }
  } catch (err) {
    loadError.value = err;
  } finally {
    loading.value = false;
  }
}
watch(id, () => void load(), { immediate: true });

// A built-in role's name/description stay code-defined; its permissions are
// still administrator-adjustable, unlike a fully locked role (superadmin
// alone), where nothing — not even permissions — can change here.
const isBuiltIn = computed(() => role.value?.is_built_in ?? false);
const permissionsLocked = computed(() => role.value !== null && !role.value.permissions_editable);
const fullyLocked = computed(() => isBuiltIn.value && permissionsLocked.value);
const isGrantable = (name: string): boolean => grantable.value.includes(name);

function togglePermission(name: string): void {
  if (permissionsLocked.value || !isGrantable(name)) return;
  const next = new Set(selected.value);
  if (next.has(name)) next.delete(name);
  else next.add(name);
  selected.value = next;
}

const canSubmit = computed(() => !fullyLocked.value && (isBuiltIn.value || (form.nameAr.trim() !== "" && form.nameEn.trim() !== "")));

const submitting = ref(false);
const saved = ref(false);
const error = ref<unknown>(null);
const staleConflict = ref(false);

const fieldErrors = computed<Record<string, string[]>>(() => (error.value instanceof ApiError ? error.value.fieldErrors : {}));
const firstError = (...keys: string[]): string | null => {
  for (const key of keys) if (fieldErrors.value[key]?.[0]) return fieldErrors.value[key]![0]!;
  return null;
};
const generalError = computed(() => {
  if (!(error.value instanceof ApiError)) return error.value instanceof Error ? error.value.message : null;
  if (Object.keys(fieldErrors.value).length > 0) return null;
  return error.value.message;
});

// FR-006: the registrar must be told how many people a permission change affects *before* it saves.
const confirmingPermissionChange = ref(false);
const permissionsChanged = computed(() => {
  if (role.value === null) return false;
  const before = new Set(role.value.permissions);
  return before.size !== selected.value.size || [...before].some((p) => !selected.value.has(p));
});

async function submit(): Promise<void> {
  if (!isNew.value && permissionsChanged.value && (role.value?.users_count ?? 0) > 0 && !confirmingPermissionChange.value) {
    confirmingPermissionChange.value = true;
    return;
  }
  confirmingPermissionChange.value = false;
  await save();
}

async function save(): Promise<void> {
  submitting.value = true;
  saved.value = false;
  error.value = null;
  staleConflict.value = false;
  // A built-in role's name/description are locked server-side (RoleGuard);
  // resending them unchanged would still trip that refusal, so they're only
  // included when actually editable — permissions are the only field a
  // built-in role's edit can touch.
  const payload = {
    ...(isBuiltIn.value ? {} : {
      name_ar: form.nameAr.trim(),
      name_en: form.nameEn.trim(),
      description_ar: form.descriptionAr.trim() || null,
      description_en: form.descriptionEn.trim() || null,
    }),
    permissions: [...selected.value],
  };
  try {
    if (isNew.value) {
      const created = await createRole(payload);
      await router.push(localePath("admin.roles.edit", { id: created.data.id }));
    } else {
      role.value = (await updateRole(id.value!, { ...payload, updated_at: role.value!.updated_at })).data;
      loadForm(role.value);
      saved.value = true;
    }
  } catch (err) {
    if (err instanceof ApiError && err.status === 409 && !isNew.value) {
      // Concurrency conflict (FR-011) and the built-in refusal (FR-005/015)
      // both land here; the message alone tells them apart for the reader,
      // but only a real conflict makes a reload-and-retry meaningful.
      staleConflict.value = fieldErrors.value.updated_at !== undefined || err.message.toLowerCase().includes("changed by someone else");
    }
    error.value = err;
  } finally {
    submitting.value = false;
  }
}

async function reloadAfterConflict(): Promise<void> {
  staleConflict.value = false;
  error.value = null;
  await load();
}

// --- delete (custom roles only, only when unheld) ---
const deleting = ref(false);
const deleteError = ref<unknown>(null);
const confirmingDelete = ref(false);

async function confirmDelete(): Promise<void> {
  if (id.value === null) return;
  deleting.value = true;
  deleteError.value = null;
  try {
    await deleteRole(id.value);
    await router.push(localePath("admin.roles"));
  } catch (err) {
    deleteError.value = err;
    confirmingDelete.value = false;
  } finally {
    deleting.value = false;
  }
}

const input = "mt-1 w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none disabled:cursor-not-allowed disabled:bg-neutral-soft disabled:text-ink-muted";
</script>

<template>
  <section>
    <ErrorState v-if="!canManage" :error="forbidden" />
    <ErrorState v-else-if="loadError" :error="loadError" @retry="load" />
    <Spinner v-else-if="loading" class="mx-auto my-12 block" />

    <template v-else>
      <nav class="text-xs text-ink-muted" aria-label="Breadcrumb">
        <RouterLink :to="localePath('admin.roles')" class="hover:text-ink">{{ t("roles.back") }}</RouterLink>
      </nav>

      <form @submit.prevent="submit">
        <div class="mt-3 flex flex-wrap items-start justify-between gap-4 border-b-2 border-ink pb-6">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <h1 class="text-balance text-3xl font-semibold tracking-tight text-ink">{{ isNew ? t("roles.addTitle") : t("roles.editTitle") }}</h1>
              <span v-if="isBuiltIn" class="rounded-sm bg-neutral-soft px-1.5 py-0.5 text-xs font-medium text-ink-muted" data-testid="built-in-badge">{{ t("roles.builtIn") }}</span>
            </div>
            <p v-if="fullyLocked" class="mt-1 text-sm text-ink-muted" data-testid="built-in-explain">{{ t("roles.fullyLockedExplain") }}</p>
            <p v-else-if="isBuiltIn" class="mt-1 text-sm text-ink-muted" data-testid="built-in-explain">{{ t("roles.builtInExplain") }}</p>
          </div>
          <div class="flex items-center gap-2">
            <button
              v-if="!isNew && !isBuiltIn && (role?.users_count ?? 0) === 0"
              type="button"
              class="rounded-md border border-danger px-4 py-2 text-sm font-medium text-danger hover:bg-danger-soft disabled:opacity-50"
              :disabled="deleting"
              data-testid="delete-role"
              @click="confirmingDelete = true"
            >
              {{ t("roles.delete") }}
            </button>
            <p v-else-if="!isNew && !isBuiltIn" class="text-xs text-ink-muted" data-testid="delete-blocked">{{ t("roles.deleteBlocked", { count: role?.users_count ?? 0 }) }}</p>
            <RouterLink :to="localePath('admin.roles')" class="rounded-md border border-ink px-4 py-2 text-sm font-medium text-ink hover:bg-neutral-soft">{{ t("roles.cancel") }}</RouterLink>
            <button v-if="!fullyLocked" type="submit" class="rounded-md bg-ink px-4 py-2 text-sm font-semibold text-paper disabled:cursor-not-allowed disabled:bg-neutral-soft disabled:text-ink-muted" :disabled="!canSubmit || submitting" data-testid="role-submit">
              {{ submitting ? t("roles.saving") : t("roles.save") }}
            </button>
          </div>
        </div>

        <p v-if="generalError" class="mt-2 text-sm text-danger" role="alert" data-testid="general-error">{{ generalError }}</p>
        <p v-if="saved" class="mt-2 text-sm text-success" data-testid="role-saved">{{ t("roles.savedMessage") }}</p>
        <div v-if="staleConflict" class="mt-2 flex items-center gap-3 rounded-md border border-danger bg-danger-soft p-3 text-sm text-danger" data-testid="stale-conflict">
          <span>{{ t("roles.staleConflict") }}</span>
          <button type="button" class="font-medium underline" @click="reloadAfterConflict">{{ t("roles.reload") }}</button>
        </div>

        <div class="mt-8 grid gap-10 lg:grid-cols-[24rem_1fr]">
          <section class="space-y-4">
            <h2 class="border-b-2 border-ink pb-2 text-sm font-semibold uppercase text-ink">{{ t("roles.details") }}</h2>
            <label class="block text-xs text-ink-muted">{{ t("roles.nameAr") }}
              <input v-model="form.nameAr" type="text" dir="rtl" :disabled="isBuiltIn" :class="input" data-testid="role-name-ar" />
              <span v-if="firstError('name_ar')" class="text-danger">{{ firstError("name_ar") }}</span>
            </label>
            <label class="block text-xs text-ink-muted">{{ t("roles.nameEn") }}
              <input v-model="form.nameEn" type="text" dir="ltr" :disabled="isBuiltIn" :class="input" data-testid="role-name-en" />
              <span v-if="firstError('name_en')" class="text-danger">{{ firstError("name_en") }}</span>
            </label>
            <label class="block text-xs text-ink-muted">{{ t("roles.descriptionAr") }}
              <textarea v-model="form.descriptionAr" dir="rtl" rows="2" :disabled="isBuiltIn" :class="input" data-testid="role-description-ar" />
            </label>
            <label class="block text-xs text-ink-muted">{{ t("roles.descriptionEn") }}
              <textarea v-model="form.descriptionEn" dir="ltr" rows="2" :disabled="isBuiltIn" :class="input" data-testid="role-description-en" />
            </label>
          </section>

          <section>
            <h2 class="border-b-2 border-ink pb-2 text-sm font-semibold uppercase text-ink">{{ t("roles.permissions") }}</h2>
            <p v-if="firstError('permissions')" class="mt-2 text-sm text-danger" data-testid="permissions-error">{{ firstError("permissions") }}</p>
            <div class="mt-4 space-y-6" data-testid="permission-groups">
              <div v-for="group in groups" :key="group.group">
                <h3 class="text-xs font-semibold uppercase text-ink-muted">{{ t(`roles.groups.${group.group}`) }}</h3>
                <ul class="mt-2 space-y-1">
                  <li v-for="p in group.permissions" :key="p.name" class="flex items-start gap-2 text-sm" data-testid="permission-row">
                    <input
                      type="checkbox"
                      class="mt-0.5 size-4"
                      :checked="selected.has(p.name)"
                      :disabled="permissionsLocked || !isGrantable(p.name)"
                      :data-testid="`permission-${p.name}`"
                      @change="togglePermission(p.name)"
                    />
                    <span :class="isGrantable(p.name) ? 'text-ink' : 'text-ink-muted'">
                      {{ pick(p.label)?.text }}
                      <span v-if="!isGrantable(p.name)" class="block text-xs text-ink-muted">{{ t("roles.notGrantable") }}</span>
                    </span>
                  </li>
                </ul>
              </div>
            </div>
          </section>
        </div>
      </form>

      <ConfirmDialog
        :open="confirmingPermissionChange"
        :title="t('roles.confirmChangeTitle')"
        :description="t('roles.confirmChangeDescription', { count: role?.users_count ?? 0 })"
        :confirm-label="t('roles.confirmChangeAction')"
        :cancel-label="t('roles.cancel')"
        :busy="submitting"
        @confirm="save"
        @cancel="confirmingPermissionChange = false"
      />
      <ConfirmDialog
        :open="confirmingDelete"
        :title="t('roles.deleteTitle')"
        :description="t('roles.deleteDescription')"
        :confirm-label="deleting ? t('roles.deleting') : t('roles.delete')"
        :cancel-label="t('roles.cancel')"
        :busy="deleting"
        :error="deleteError instanceof Error ? deleteError.message : null"
        @confirm="confirmDelete"
        @cancel="confirmingDelete = false"
      />
    </template>
  </section>
</template>

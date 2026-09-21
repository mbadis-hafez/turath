<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";

import { createUser, fetchUser, updateUser } from "@/api/users";
import ErrorState from "@/components/common/ErrorState.vue";
import Spinner from "@/components/common/Spinner.vue";
import { useLocalePath } from "@/composables/useLocalePath";
import { useAuthStore } from "@/stores/auth";
import { ApiError } from "@/types/api";
import { ADMIN_ROLES, type AdminUserDetail } from "@/types/user";

const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const { localePath } = useLocalePath();
const auth = useAuthStore();

const forbidden = new ApiError("forbidden", "Forbidden", { status: 403 });
const canManage = computed(() => auth.can("users.manage"));

const id = computed(() => (route.params.id ? Number(route.params.id) : null));
const isNew = computed(() => id.value === null);

const form = reactive({
  name: "",
  email: "",
  password: "",
  passwordConfirmation: "",
  role: "" as string,
  isActive: true,
  sendInvitation: false,
});

function loadForm(user: AdminUserDetail): void {
  form.name = user.name;
  form.email = user.email;
  form.password = "";
  form.passwordConfirmation = "";
  form.role = user.roles[0] ?? "";
  form.isActive = user.is_active;
}

function payload(): Record<string, string | boolean> {
  const body: Record<string, string | boolean> = {
    name: form.name.trim(),
    email: form.email.trim(),
    role: form.role,
  };
  if (isNew.value) {
    body.password = form.password;
    body.password_confirmation = form.passwordConfirmation;
    if (form.sendInvitation) {
      body.send_invitation = true;
    }
  } else {
    body.is_active = form.isActive;
    if (form.password !== "") {
      body.password = form.password;
      body.password_confirmation = form.passwordConfirmation;
    }
  }
  return body;
}

const user = ref<AdminUserDetail | null>(null);
const loading = ref(false);
const loadError = ref<unknown>(null);

async function loadUser(): Promise<void> {
  if (id.value === null) return;
  loading.value = user.value === null;
  loadError.value = null;
  try {
    user.value = (await fetchUser(id.value)).data;
    loadForm(user.value);
  } catch (err) {
    loadError.value = err;
  } finally {
    loading.value = false;
  }
}
watch(id, () => void loadUser(), { immediate: true });

const submitting = ref(false);
const saved = ref(false);
const error = ref<unknown>(null);

const filled = (v: string): boolean => v.trim() !== "";
const canSubmit = computed(
  () =>
    filled(form.name) &&
    filled(form.email) &&
    form.role !== "" &&
    (isNew.value ? form.password !== "" : true) &&
    !submitting.value,
);

const fieldErrors = computed<Record<string, string[]>>(() => (error.value instanceof ApiError ? error.value.fieldErrors : {}));
const firstError = (...keys: string[]): string | null => {
  for (const key of keys) if (fieldErrors.value[key]?.[0]) return fieldErrors.value[key]![0]!;
  return null;
};
const generalError = computed(() => (error.value instanceof Error && Object.keys(fieldErrors.value).length === 0 ? error.value.message : null));

async function submit(): Promise<void> {
  submitting.value = true;
  saved.value = false;
  error.value = null;
  try {
    if (isNew.value) {
      await createUser(payload());
      await router.push(localePath("admin.users"));
    } else {
      user.value = (await updateUser(id.value!, payload())).data;
      loadForm(user.value);
      saved.value = true;
    }
  } catch (err) {
    error.value = err;
  } finally {
    submitting.value = false;
  }
}

const input = "mt-1 w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none";
</script>

<template>
  <section>
    <ErrorState v-if="!canManage" :error="forbidden" />
    <ErrorState v-else-if="loadError" :error="loadError" @retry="loadUser" />
    <Spinner v-else-if="loading" class="mx-auto my-12 block" />

    <template v-else>
      <nav class="text-xs text-ink-muted" aria-label="Breadcrumb">
        <RouterLink :to="localePath('admin.users')" class="hover:text-ink">{{ t("users.edit.back") }}</RouterLink>
        <span aria-hidden="true"> &rsaquo; </span>{{ isNew ? t("users.edit.addTitle") : t("users.edit.editTitle") }}
      </nav>

      <form @submit.prevent="submit">
        <div class="mt-3 flex flex-wrap items-start justify-between gap-4 border-b-2 border-ink pb-6">
          <div>
            <h1 class="text-balance text-3xl font-semibold tracking-tight text-ink">{{ isNew ? t("users.edit.addTitle") : t("users.edit.editTitle") }}</h1>
            <p v-if="user" class="mt-1 text-sm text-ink-muted">{{ user.email }}</p>
          </div>
          <div class="flex items-center gap-2">
            <RouterLink :to="localePath('admin.users')" class="rounded-md border border-ink px-4 py-2 text-sm font-medium text-ink hover:bg-neutral-soft">{{ t("users.edit.cancel") }}</RouterLink>
            <button type="submit" class="rounded-md bg-ink px-4 py-2 text-sm font-semibold text-paper disabled:cursor-not-allowed disabled:bg-neutral-soft disabled:text-ink-muted" data-testid="user-submit" :disabled="!canSubmit">
              {{ submitting ? t("users.edit.saving") : t("users.edit.save") }}
            </button>
          </div>
        </div>
        <p v-if="generalError" class="mt-2 text-sm text-danger" role="alert">{{ generalError }}</p>
        <p v-if="saved" class="mt-2 text-sm text-success" data-testid="user-saved">{{ t("users.edit.saved") }}</p>

        <div class="mt-8 max-w-3xl">
          <section>
            <h2 class="border-b-2 border-ink pb-2 text-sm font-semibold uppercase text-ink">{{ t("users.edit.account") }}</h2>
            <div class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
              <label class="text-xs text-ink-muted">{{ t("users.edit.name") }}
                <input v-model="form.name" type="text" :class="input" data-testid="user-name" />
                <span v-if="firstError('name')" class="text-danger">{{ firstError("name") }}</span>
              </label>
              <label class="text-xs text-ink-muted">{{ t("users.edit.email") }}
                <input v-model="form.email" type="email" dir="ltr" :class="input" data-testid="user-email" />
                <span v-if="firstError('email')" class="text-danger">{{ firstError("email") }}</span>
              </label>
              <label class="text-xs text-ink-muted">{{ t("users.edit.password") }}
                <input v-model="form.password" type="password" dir="ltr" autocomplete="new-password" :class="input" data-testid="user-password" />
                <span v-if="firstError('password')" class="text-danger">{{ firstError("password") }}</span>
              </label>
              <label class="text-xs text-ink-muted">{{ t("users.edit.passwordConfirm") }}
                <input v-model="form.passwordConfirmation" type="password" dir="ltr" autocomplete="new-password" :class="input" data-testid="user-password-confirmation" />
              </label>
              <p v-if="!isNew" class="text-xs text-ink-muted sm:col-span-2">{{ t("users.edit.passwordHelp") }}</p>
              <label class="text-xs text-ink-muted">{{ t("users.edit.role") }}
                <select v-model="form.role" :class="input" data-testid="user-role">
                  <option value="" disabled>—</option>
                  <option v-for="role in ADMIN_ROLES" :key="role" :value="role">{{ t(`users.roles.${role}`) }}</option>
                </select>
                <span v-if="firstError('role')" class="text-danger">{{ firstError("role") }}</span>
              </label>
              <label v-if="!isNew" class="flex items-end gap-2 text-sm text-ink">
                <input v-model="form.isActive" type="checkbox" class="size-4 accent-ink" data-testid="user-active" />
                <span>{{ t("users.edit.isActive") }}</span>
              </label>
              <p v-if="!isNew" class="text-xs text-ink-muted sm:col-span-2">{{ t("users.edit.isActiveHelp") }}</p>
              <template v-if="isNew">
                <label class="flex items-end gap-2 text-sm text-ink">
                  <input v-model="form.sendInvitation" type="checkbox" class="size-4 accent-ink" data-testid="send-invitation" />
                  <span>{{ t("users.edit.sendInvitation") }}</span>
                </label>
                <p class="text-xs text-ink-muted sm:col-span-2">{{ t("users.edit.sendInvitationHelp") }}</p>
              </template>
            </div>
          </section>
        </div>
      </form>
    </template>
  </section>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";

import { createUser, fetchUser, sendInvitation, updateUser } from "@/api/users";
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
  status: "active" as "active" | "inactive",
  sendInvitation: false,
});

function loadForm(user: AdminUserDetail): void {
  form.name = user.name;
  form.email = user.email;
  form.password = "";
  form.passwordConfirmation = "";
  form.role = user.roles[0] ?? "";
  form.status = user.is_active ? "active" : "inactive";
}

function payload(): Record<string, string | boolean> {
  const body: Record<string, string | boolean> = {
    name: form.name.trim(),
    email: form.email.trim(),
    role: form.role,
    is_active: form.status === "active",
  };
  if (isNew.value) {
    body.password = form.password;
    body.password_confirmation = form.passwordConfirmation;
    if (form.sendInvitation) {
      body.send_invitation = true;
    }
  } else if (form.password !== "") {
    body.password = form.password;
    body.password_confirmation = form.passwordConfirmation;
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

const inviting = ref(false);
const invitationSent = ref(false);
const invitationError = ref<unknown>(null);

const showPassword = ref(false);

function generatePassword(): void {
  const alphabet = "ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789#%&";
  const bytes = new Uint8Array(16);
  crypto.getRandomValues(bytes);
  let generated = "";
  for (const byte of bytes) generated += alphabet.charAt(byte % alphabet.length);
  form.password = generated;
  form.passwordConfirmation = generated;
  showPassword.value = true;
}

async function invite(): Promise<void> {
  if (id.value === null) return;
  inviting.value = true;
  invitationSent.value = false;
  invitationError.value = null;
  try {
    await sendInvitation(id.value);
    invitationSent.value = true;
  } catch (err) {
    invitationError.value = err;
  } finally {
    inviting.value = false;
  }
}
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
            <button
              v-if="!isNew"
              type="button"
              data-testid="send-invitation-button"
              :disabled="inviting"
              class="inline-flex items-center gap-1.5 rounded-md border border-ink px-4 py-2 text-sm font-medium text-ink hover:bg-neutral-soft disabled:cursor-not-allowed disabled:opacity-60"
              @click="invite"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4" aria-hidden="true">
                <rect width="20" height="16" x="2" y="4" rx="2" />
                <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
              </svg>
              {{ inviting ? t("users.edit.sendingInvitation") : t("users.edit.sendInvitation") }}
            </button>
            <RouterLink :to="localePath('admin.users')" class="rounded-md border border-ink px-4 py-2 text-sm font-medium text-ink hover:bg-neutral-soft">{{ t("users.edit.cancel") }}</RouterLink>
            <button type="submit" class="rounded-md bg-ink px-4 py-2 text-sm font-semibold text-paper disabled:cursor-not-allowed disabled:bg-neutral-soft disabled:text-ink-muted" data-testid="user-submit" :disabled="!canSubmit">
              {{ submitting ? t("users.edit.saving") : t("users.edit.save") }}
            </button>
          </div>
        </div>
        <p v-if="generalError" class="mt-2 text-sm text-danger" role="alert">{{ generalError }}</p>
        <p v-if="saved" class="mt-2 text-sm text-success" data-testid="user-saved">{{ t("users.edit.saved") }}</p>
        <p v-if="invitationError" class="mt-2 text-sm text-danger" role="alert" data-testid="invitation-error">
          {{ invitationError instanceof Error ? invitationError.message : t("users.edit.invitationFailed") }}
        </p>
        <p v-if="invitationSent" class="mt-2 text-sm text-success" data-testid="invitation-sent">{{ t("users.edit.invitationSent") }}</p>

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
              <label class="text-xs text-ink-muted">
                <span class="flex items-center justify-between gap-2">
                  <span>{{ t("users.edit.password") }}</span>
                  <button type="button" class="text-xs font-medium text-ink underline decoration-accent underline-offset-2 hover:text-accent" data-testid="generate-password" @click="generatePassword">
                    {{ t("users.edit.generatePassword") }}
                  </button>
                </span>
                <span class="relative mt-1 block">
                  <input v-model="form.password" :type="showPassword ? 'text' : 'password'" dir="ltr" autocomplete="new-password" class="w-full rounded-md border border-line bg-surface py-2 ps-3 pe-9 text-sm text-ink focus:border-accent focus:outline-none" data-testid="user-password" />
                  <button
                    type="button"
                    :aria-label="showPassword ? t('users.edit.hidePassword') : t('users.edit.showPassword')"
                    :aria-pressed="showPassword"
                    class="absolute inset-y-0 end-0 flex items-center px-2 text-ink-muted transition-colors hover:text-ink"
                    data-testid="toggle-password"
                    @click="showPassword = !showPassword"
                  >
                    <svg v-if="showPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4" aria-hidden="true">
                      <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
                      <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" />
                      <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" />
                      <path d="m2 2 20 20" />
                    </svg>
                    <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4" aria-hidden="true">
                      <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
                      <circle cx="12" cy="12" r="3" />
                    </svg>
                  </button>
                </span>
                <span v-if="firstError('password')" class="text-danger">{{ firstError("password") }}</span>
              </label>
              <label class="text-xs text-ink-muted">{{ t("users.edit.passwordConfirm") }}
                <input v-model="form.passwordConfirmation" :type="showPassword ? 'text' : 'password'" dir="ltr" autocomplete="new-password" :class="input" data-testid="user-password-confirmation" />
              </label>
              <p v-if="!isNew" class="text-xs text-ink-muted sm:col-span-2">{{ t("users.edit.passwordHelp") }}</p>
              <label class="text-xs text-ink-muted">{{ t("users.edit.role") }}
                <select v-model="form.role" :class="input" data-testid="user-role">
                  <option value="" disabled>—</option>
                  <option v-for="role in ADMIN_ROLES" :key="role" :value="role">{{ t(`users.roles.${role}`) }}</option>
                </select>
                <span v-if="firstError('role')" class="text-danger">{{ firstError("role") }}</span>
              </label>
              <label class="text-xs text-ink-muted">{{ t("users.edit.status") }}
                <select v-model="form.status" :class="input" data-testid="user-status">
                  <option value="active">{{ t("users.edit.statusActive") }}</option>
                  <option value="inactive">{{ t("users.edit.statusInactive") }}</option>
                </select>
              </label>
              <p class="text-xs text-ink-muted sm:col-span-2">{{ t("users.edit.isActiveHelp") }}</p>
              <template v-if="isNew">
                <label class="flex cursor-pointer items-start gap-3 rounded-md border border-line bg-surface p-3 transition-colors has-checked:border-ink has-checked:bg-neutral-soft sm:col-span-2">
                  <input v-model="form.sendInvitation" type="checkbox" class="mt-0.5 size-4 shrink-0 accent-ink" data-testid="send-invitation" />
                  <span>
                    <span class="block text-sm font-medium text-ink">{{ t("users.edit.sendInvitation") }}</span>
                    <span class="mt-0.5 block text-xs leading-relaxed text-ink-muted">{{ t("users.edit.sendInvitationHelp") }}</span>
                  </span>
                </label>
              </template>
            </div>
          </section>
        </div>
      </form>
    </template>
  </section>
</template>

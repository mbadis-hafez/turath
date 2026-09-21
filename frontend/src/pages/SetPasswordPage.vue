<script setup lang="ts">
import { reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";

import { changePassword } from "@/api/auth";
import { useLocalePath } from "@/composables/useLocalePath";
import { useAuthStore } from "@/stores/auth";
import { ApiError } from "@/types/api";

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const { localePath } = useLocalePath();

const form = reactive({ password: "", passwordConfirmation: "" });
const submitting = ref(false);
const error = ref<unknown>(null);

const fieldErrors = reactive<Record<string, string[]>>({});
const generalError = ref<string | null>(null);

function fail(err: unknown): void {
  error.value = err;
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key]);
  if (err instanceof ApiError && err.kind === "validation") {
    Object.assign(fieldErrors, err.fieldErrors);
    generalError.value = null;
  } else {
    generalError.value = err instanceof Error ? err.message : t("errors.generic");
  }
}

async function submit(): Promise<void> {
  if (submitting.value) return;
  submitting.value = true;
  error.value = null;
  generalError.value = null;
  try {
    await changePassword(form.password, form.passwordConfirmation);
    await auth.fetchUser();
    const redirect = typeof route.query.redirect === "string" ? route.query.redirect : null;
    await router.push(redirect ?? localePath("dashboard"));
  } catch (err) {
    fail(err);
  } finally {
    submitting.value = false;
  }
}

async function logout(): Promise<void> {
  await auth.logout();
  await router.push(localePath("login"));
}
</script>

<template>
  <div class="flex min-h-dvh items-center justify-center bg-paper px-6 py-12">
    <section class="w-full max-w-md">
      <img src="/logo.png" :alt="$t('home.heading')" class="mx-auto mb-10 h-20 w-auto" width="188" height="80" />

      <p class="text-sm text-ink-muted">{{ t("auth.setPassword.title") }}</p>
      <h1 class="mt-2 text-3xl font-semibold text-balance font-display text-ink">{{ t("auth.setPassword.heading") }}</h1>
      <p v-if="auth.user" class="mt-1 text-sm text-ink-muted">{{ auth.user.email }}</p>
      <p class="mt-4 text-sm text-pretty text-ink-muted">{{ t("auth.setPassword.hint") }}</p>

      <p v-if="generalError" class="mt-6 border-s-2 border-danger bg-danger-soft px-4 py-3 text-sm text-danger" role="alert">{{ generalError }}</p>

      <form class="mt-8 space-y-8" novalidate @submit.prevent="submit">
        <div>
          <label for="new-password" class="mb-1 block text-sm text-ink-muted">{{ t("auth.setPassword.newPassword") }}</label>
          <input
            id="new-password"
            v-model="form.password"
            type="password"
            name="password"
            required
            minlength="8"
            autocomplete="new-password"
            class="w-full border-0 border-b-2 border-line bg-transparent px-0 pt-1 pb-2.5 text-lg text-ink focus:border-ink"
            :aria-invalid="Boolean(fieldErrors.password)"
            data-testid="new-password"
          />
          <p v-if="fieldErrors.password" class="mt-1 text-sm text-danger">{{ fieldErrors.password[0] }}</p>
        </div>

        <div>
          <label for="confirm-password" class="mb-1 block text-sm text-ink-muted">{{ t("auth.setPassword.confirmPassword") }}</label>
          <input
            id="confirm-password"
            v-model="form.passwordConfirmation"
            type="password"
            name="password_confirmation"
            required
            minlength="8"
            autocomplete="new-password"
            class="w-full border-0 border-b-2 border-line bg-transparent px-0 pt-1 pb-2.5 text-lg text-ink focus:border-ink"
            :aria-invalid="Boolean(fieldErrors.password_confirmation)"
            data-testid="confirm-password"
          />
          <p v-if="fieldErrors.password_confirmation" class="mt-1 text-sm text-danger">{{ fieldErrors.password_confirmation[0] }}</p>
        </div>

        <div class="space-y-4 pt-2">
          <button type="submit" class="w-full bg-ink py-4 text-lg font-semibold text-paper transition-colors hover:bg-ink/85 disabled:cursor-not-allowed disabled:opacity-60" :disabled="submitting" data-testid="set-password-submit">
            {{ submitting ? t("auth.setPassword.submitting") : t("auth.setPassword.submit") }}
          </button>
          <button type="button" class="w-full border border-ink py-4 text-lg font-semibold text-ink transition-colors hover:bg-ink hover:text-paper" data-testid="set-password-logout" @click="logout">
            {{ t("auth.setPassword.logout") }}
          </button>
        </div>
      </form>
    </section>
  </div>
</template>

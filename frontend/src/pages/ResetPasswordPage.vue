<script setup lang="ts">
import { reactive, ref } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";

import { resetPassword } from "@/api/auth";
import { useLocalePath } from "@/composables/useLocalePath";
import { ApiError } from "@/types/api";

const { t } = useI18n();
const route = useRoute();
const { localePath, pushLocalePath } = useLocalePath();

// Both come from the link in the reset email (PasswordResetMail).
const token = typeof route.query.token === "string" ? route.query.token : "";
const email = typeof route.query.email === "string" ? route.query.email : "";

const form = reactive({ password: "", passwordConfirmation: "" });
const submitting = ref(false);
// The server answers a wrong, used or expired token, or an unknown address, with a `token` error.
const linkInvalid = ref(token === "" || email === "");
const bannerError = ref<string | null>(null);
const fieldErrors = reactive<Record<string, string[]>>({});

function errorMessage(error: unknown): string {
  if (error instanceof ApiError) {
    if (error.kind === "throttled") return t("errors.throttled");
    if (error.kind === "network") return t("errors.network");
  }
  return t("errors.generic");
}

async function submit(): Promise<void> {
  if (submitting.value) return;
  submitting.value = true;
  bannerError.value = null;
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key]);

  try {
    await resetPassword({
      token,
      email,
      password: form.password,
      passwordConfirmation: form.passwordConfirmation,
    });
    await pushLocalePath("login", {}, { reset: "1", email });
  } catch (error) {
    if (error instanceof ApiError && error.kind === "validation") {
      if (error.fieldErrors.token || error.fieldErrors.email) {
        linkInvalid.value = true;
      } else {
        Object.assign(fieldErrors, error.fieldErrors);
      }
    } else {
      bannerError.value = errorMessage(error);
    }
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <div class="flex min-h-dvh items-center justify-center bg-paper px-6 py-12">
    <section class="w-full max-w-md">
      <img src="/logo.png" :alt="$t('home.heading')" class="mx-auto mb-10 h-20 w-auto" width="188" height="80" />

      <p class="text-sm text-ink-muted">{{ t("auth.resetPassword.title") }}</p>

      <div v-if="linkInvalid" data-testid="reset-link-invalid">
        <h1 class="mt-2 text-3xl font-semibold text-balance font-display text-ink">{{ t("auth.resetPassword.invalidHeading") }}</h1>
        <p class="mt-4 text-pretty text-ink" role="alert">{{ t("auth.resetPassword.invalidBody") }}</p>
        <RouterLink
          :to="localePath('forgot-password', {}, email ? { email } : {})"
          class="mt-8 block w-full border border-ink py-4 text-center text-lg font-semibold text-ink transition-colors hover:bg-ink hover:text-paper"
          data-testid="reset-request-new"
        >
          {{ t("auth.resetPassword.requestNew") }}
        </RouterLink>
      </div>

      <template v-else>
        <h1 class="mt-2 text-3xl font-semibold text-balance font-display text-ink">{{ t("auth.resetPassword.heading") }}</h1>
        <p class="mt-1 text-sm text-ink-muted"><bdi>{{ email }}</bdi></p>
        <p class="mt-4 text-sm text-pretty text-ink-muted">{{ t("auth.resetPassword.hint") }}</p>

        <p v-if="bannerError" class="mt-6 border-s-2 border-danger bg-danger-soft px-4 py-3 text-sm text-danger" role="alert">{{ bannerError }}</p>

        <form class="mt-8 space-y-8" novalidate @submit.prevent="submit">
          <!-- Lets password managers file the new password under the right account. -->
          <input type="email" name="email" autocomplete="username" :value="email" class="hidden" readonly tabindex="-1" aria-hidden="true" />

          <div>
            <label for="new-password" class="mb-1 block text-sm text-ink-muted">{{ t("auth.resetPassword.newPassword") }}</label>
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
              aria-describedby="new-password-error"
              data-testid="new-password"
            />
            <p v-if="fieldErrors.password" id="new-password-error" class="mt-1 text-sm text-danger">{{ fieldErrors.password[0] }}</p>
          </div>

          <div>
            <label for="confirm-password" class="mb-1 block text-sm text-ink-muted">{{ t("auth.resetPassword.confirmPassword") }}</label>
            <input
              id="confirm-password"
              v-model="form.passwordConfirmation"
              type="password"
              name="password_confirmation"
              required
              minlength="8"
              autocomplete="new-password"
              class="w-full border-0 border-b-2 border-line bg-transparent px-0 pt-1 pb-2.5 text-lg text-ink focus:border-ink"
              data-testid="confirm-password"
            />
          </div>

          <button type="submit" class="w-full bg-ink py-4 text-lg font-semibold text-paper transition-colors hover:bg-ink/85 disabled:cursor-not-allowed disabled:opacity-60" :disabled="submitting" data-testid="reset-password-submit">
            {{ submitting ? t("auth.resetPassword.submitting") : t("auth.resetPassword.submit") }}
          </button>
        </form>
      </template>

      <p class="mt-10 border-t border-line pt-8 text-center text-sm">
        <RouterLink :to="localePath('login')" class="text-accent underline underline-offset-4 hover:text-accent-strong">{{ t("auth.forgotPassword.backToLogin") }}</RouterLink>
      </p>
    </section>
  </div>
</template>

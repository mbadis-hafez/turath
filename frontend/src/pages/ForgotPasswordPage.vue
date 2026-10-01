<script setup lang="ts">
import { reactive, ref } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";

import { requestPasswordReset } from "@/api/auth";
import { useLocalePath } from "@/composables/useLocalePath";
import { ApiError } from "@/types/api";

const { t } = useI18n();
const route = useRoute();
const { localePath } = useLocalePath();

// The login page passes along whatever was typed in its email field.
const email = ref(typeof route.query.email === "string" ? route.query.email : "");
const sentTo = ref<string | null>(null);
const submitting = ref(false);
const bannerError = ref<string | null>(null);
const fieldErrors = reactive<Record<string, string[]>>({});

function errorMessage(error: unknown): string | null {
  if (error instanceof ApiError) {
    if (error.kind === "validation") return null;
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
    await requestPasswordReset(email.value);
    sentTo.value = email.value;
  } catch (error) {
    if (error instanceof ApiError && error.kind === "validation") {
      Object.assign(fieldErrors, error.fieldErrors);
    }
    bannerError.value = errorMessage(error);
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <div class="flex min-h-dvh items-center justify-center bg-paper px-6 py-12">
    <section class="w-full max-w-md">
      <img src="/logo.png" :alt="$t('home.heading')" class="mx-auto mb-10 h-20 w-auto" width="188" height="80" />

      <p class="text-sm text-ink-muted">{{ t("auth.forgotPassword.title") }}</p>

      <div v-if="sentTo !== null" role="status" data-testid="reset-link-sent">
        <h1 class="mt-2 text-3xl font-semibold text-balance font-display text-ink">{{ t("auth.forgotPassword.sentHeading") }}</h1>
        <p class="mt-4 text-pretty text-ink">{{ t("auth.forgotPassword.sentBody") }}</p>
        <p class="mt-2 font-semibold break-all text-ink"><bdi>{{ sentTo }}</bdi></p>
        <p class="mt-4 text-sm text-pretty text-ink-muted">{{ t("auth.forgotPassword.sentHint") }}</p>
        <button type="button" class="mt-8 text-sm text-accent underline underline-offset-4 hover:text-accent-strong" data-testid="reset-try-again" @click="sentTo = null">
          {{ t("auth.forgotPassword.tryAgain") }}
        </button>
      </div>

      <template v-else>
        <h1 class="mt-2 text-3xl font-semibold text-balance font-display text-ink">{{ t("auth.forgotPassword.heading") }}</h1>
        <p class="mt-4 text-sm text-pretty text-ink-muted">{{ t("auth.forgotPassword.hint") }}</p>

        <p v-if="bannerError" class="mt-6 border-s-2 border-danger bg-danger-soft px-4 py-3 text-sm text-danger" role="alert">{{ bannerError }}</p>

        <form class="mt-8 space-y-8" novalidate @submit.prevent="submit">
          <div>
            <label for="email" class="mb-1 block text-sm text-ink-muted">{{ t("auth.email") }}</label>
            <input
              id="email"
              v-model="email"
              type="email"
              name="email"
              required
              autocomplete="email"
              class="w-full border-0 border-b-2 border-line bg-transparent px-0 pt-1 pb-2.5 text-lg text-ink focus:border-ink"
              :aria-invalid="Boolean(fieldErrors.email)"
              aria-describedby="email-error"
              data-testid="reset-email"
            />
            <p v-if="fieldErrors.email" id="email-error" class="mt-1 text-sm text-danger">{{ fieldErrors.email[0] }}</p>
          </div>

          <button type="submit" class="w-full bg-ink py-4 text-lg font-semibold text-paper transition-colors hover:bg-ink/85 disabled:cursor-not-allowed disabled:opacity-60" :disabled="submitting" data-testid="reset-request-submit">
            {{ submitting ? t("auth.forgotPassword.submitting") : t("auth.forgotPassword.submit") }}
          </button>
        </form>
      </template>

      <p class="mt-10 border-t border-line pt-8 text-center text-sm">
        <RouterLink :to="localePath('login')" class="text-accent underline underline-offset-4 hover:text-accent-strong">{{ t("auth.forgotPassword.backToLogin") }}</RouterLink>
      </p>
    </section>
  </div>
</template>

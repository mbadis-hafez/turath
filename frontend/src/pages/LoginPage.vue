<script setup lang="ts">
import { reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";

import { useAuthStore } from "@/stores/auth";
import { useLocalePath } from "@/composables/useLocalePath";
import { ApiError } from "@/types/api";

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const { localePath } = useLocalePath();

const form = reactive({ email: "", password: "" });
const submitting = ref(false);
const bannerError = ref<string | null>(null);
const fieldErrors = reactive<Record<string, string[]>>({});

function errorMessage(error: unknown): string {
  if (error instanceof ApiError) {
    if (error.kind === "validation") return t("auth.invalid");
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
    await auth.login(form.email, form.password);
    const redirect =
      typeof route.query.redirect === "string" ? route.query.redirect : null;
    await router.push(redirect ?? localePath("home"));
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
  <section class="mx-auto max-w-sm py-12">
    <h1 class="text-3xl font-semibold tracking-tight text-ink">
      {{ t("auth.title") }}
    </h1>

    <p
      v-if="bannerError"
      class="mt-6 rounded-md border border-danger/30 bg-danger-soft px-4 py-3 text-sm text-danger"
      role="alert"
    >
      {{ bannerError }}
    </p>

    <form class="mt-8 space-y-5" novalidate @submit.prevent="submit">
      <div>
        <label for="email" class="mb-1 block text-sm font-medium text-ink">
          {{ t("auth.email") }}
        </label>
        <input
          id="email"
          v-model="form.email"
          type="email"
          name="email"
          required
          autocomplete="email"
          class="w-full rounded-md border border-line bg-surface px-3 py-2 text-ink"
          :aria-invalid="Boolean(fieldErrors.email)"
          aria-describedby="email-error"
        />
        <p
          v-if="fieldErrors.email"
          id="email-error"
          class="mt-1 text-sm text-danger"
        >
          {{ fieldErrors.email[0] }}
        </p>
      </div>

      <div>
        <label for="password" class="mb-1 block text-sm font-medium text-ink">
          {{ t("auth.password") }}
        </label>
        <input
          id="password"
          v-model="form.password"
          type="password"
          name="password"
          required
          autocomplete="current-password"
          class="w-full rounded-md border border-line bg-surface px-3 py-2 text-ink"
          :aria-invalid="Boolean(fieldErrors.password)"
          aria-describedby="password-error"
        />
        <p
          v-if="fieldErrors.password"
          id="password-error"
          class="mt-1 text-sm text-danger"
        >
          {{ fieldErrors.password[0] }}
        </p>
      </div>

      <button
        type="submit"
        class="w-full rounded-md bg-accent px-4 py-2.5 font-medium text-surface hover:bg-accent-strong disabled:cursor-not-allowed disabled:opacity-50"
        :disabled="submitting"
      >
        {{ submitting ? t("auth.loggingIn") : t("auth.submit") }}
      </button>
    </form>
  </section>
</template>

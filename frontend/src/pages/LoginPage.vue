<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";

import { useAuthStore } from "@/stores/auth";
import { useLocalePath } from "@/composables/useLocalePath";
import { formatNumber } from "@/utils/format";
import type { AppLocale } from "@/i18n";
import { ApiError } from "@/types/api";

const { t, locale } = useI18n();
const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const { localePath } = useLocalePath();

const form = reactive({ email: "", password: "" });
const submitting = ref(false);
const bannerError = ref<string | null>(null);
const fieldErrors = reactive<Record<string, string[]>>({});
const passwordVisible = ref(false);

const stats = computed(() => {
  const format = (value: number) => formatNumber(value, locale.value as AppLocale);
  return [
    { value: format(8765), label: t("auth.brand.works") },
    { value: format(1402), label: t("auth.brand.artists") },
    { value: format(21240), label: t("auth.brand.archiveItems") },
  ];
});

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
  <div class="grid min-h-dvh bg-paper lg:grid-cols-2">
    <!-- Brand panel (right side in RTL) -->
    <aside
      class="hidden flex-col justify-between bg-ink p-12 text-paper lg:flex xl:p-16"
    >
      <p class="flex items-baseline gap-3 text-lg font-semibold">
        {{ $t("home.heading") }}
        <span class="text-sm font-normal tracking-wide text-paper/60 uppercase"
          >Bidayaat</span
        >
      </p>

      <div class="max-w-lg">
        <h2
          class="text-4xl leading-snug font-semibold text-balance font-display xl:text-5xl"
        >
          {{ t("auth.brand.heading") }}
        </h2>
        <p class="mt-6 text-lg leading-relaxed text-pretty text-paper/70">
          {{ t("auth.brand.body") }}
        </p>
      </div>

      <div>
        <dl class="flex gap-12 border-t border-paper/20 pt-8">
          <div v-for="stat in stats" :key="stat.label">
            <dd class="text-3xl font-semibold tabular-nums">{{ stat.value }}</dd>
            <dt class="mt-1 text-sm text-paper/60">{{ stat.label }}</dt>
          </div>
        </dl>
        <p class="mt-12 text-xs text-paper/50">
          {{ t("auth.brand.footer") }}
        </p>
      </div>
    </aside>

    <!-- Form panel -->
    <section class="flex items-center justify-center px-6 py-12 sm:px-12">
      <div class="w-full max-w-md">
        <p class="mb-10 text-center text-lg font-semibold text-ink lg:hidden">
          {{ $t("home.heading") }}
        </p>

        <p class="text-sm text-ink-muted">{{ t("auth.title") }}</p>
        <h1
          class="mt-2 text-3xl font-semibold text-balance font-display text-ink"
        >
          {{ t("auth.heading") }}
        </h1>

        <p
          v-if="bannerError"
          class="mt-6 border-s-2 border-danger bg-danger-soft px-4 py-3 text-sm text-danger"
          role="alert"
        >
          {{ bannerError }}
        </p>

        <form class="mt-10 space-y-8" novalidate @submit.prevent="submit">
          <div>
            <label for="email" class="mb-1 block text-sm text-ink-muted">
              {{ t("auth.email") }}
            </label>
            <input
              id="email"
              v-model="form.email"
              type="email"
              name="email"
              required
              autocomplete="email"
              class="w-full border-0 border-b-2 border-line bg-transparent px-0 pt-1 pb-2.5 text-lg text-ink focus:border-ink"
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
            <div class="mb-1 flex items-baseline justify-between gap-4">
              <label for="password" class="text-sm text-ink-muted">
                {{ t("auth.password") }}
              </label>
              <a
                href="#"
                class="text-sm text-accent hover:text-accent-strong"
                @click.prevent
              >
                {{ t("auth.forgot") }}
              </a>
            </div>
            <div class="relative">
              <input
                id="password"
                v-model="form.password"
                :type="passwordVisible ? 'text' : 'password'"
                name="password"
                required
                autocomplete="current-password"
                class="w-full border-0 border-b-2 border-line bg-transparent px-0 pt-1 pb-2.5 text-lg text-ink focus:border-ink"
                :aria-invalid="Boolean(fieldErrors.password)"
                aria-describedby="password-error"
              />
              <button
                type="button"
                class="absolute end-0 bottom-2.5 text-ink-muted hover:text-ink"
                :aria-label="
                  passwordVisible ? t('auth.hidePassword') : t('auth.showPassword')
                "
                :aria-pressed="passwordVisible"
                @click="passwordVisible = !passwordVisible"
              >
                <svg
                  v-if="passwordVisible"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  aria-hidden="true"
                  class="size-5"
                >
                  <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
                  <circle cx="12" cy="12" r="3" />
                </svg>
                <svg
                  v-else
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  aria-hidden="true"
                  class="size-5"
                >
                  <path
                    d="M9.9 4.24A9.12 9.12 0 0 1 12 4c6.5 0 10 8 10 8a13.16 13.16 0 0 1-1.67 2.68"
                  />
                  <path
                    d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3.5 8 10 8a9.74 9.74 0 0 0 5.39-1.61"
                  />
                  <line x1="2" x2="22" y1="2" y2="22" />
                </svg>
              </button>
            </div>
            <p
              v-if="fieldErrors.password"
              id="password-error"
              class="mt-1 text-sm text-danger"
            >
              {{ fieldErrors.password[0] }}
            </p>
          </div>

          <label class="flex items-center gap-2.5 text-sm text-ink">
            <input
              type="checkbox"
              name="remember"
              class="size-4 accent-ink"
            />
            {{ t("auth.keepSession") }}
          </label>

          <div class="space-y-4 pt-2">
            <button
              type="submit"
              class="w-full bg-ink py-4 text-lg font-semibold text-paper transition-colors hover:bg-ink/85 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="submitting"
            >
              {{ submitting ? t("auth.loggingIn") : t("auth.submit") }}
            </button>
            <button
              type="button"
              class="w-full border border-ink py-4 text-lg font-semibold text-ink transition-colors hover:bg-ink hover:text-paper"
            >
              {{ t("auth.nationalAccess") }}
            </button>
          </div>
        </form>

        <div
          class="mt-10 space-y-3 border-t border-line pt-8 text-center text-sm"
        >
          <p class="text-ink">
            {{ t("auth.noAccount") }}
            <a
              href="#"
              class="text-accent underline underline-offset-4 hover:text-accent-strong"
              @click.prevent
            >
              {{ t("auth.requestAccount") }}
            </a>
          </p>
          <p>
            <a
              href="#"
              class="text-accent underline underline-offset-4 hover:text-accent-strong"
              @click.prevent
            >
              {{ t("auth.browseOnly") }}
              <span aria-hidden="true" class="inline-block rtl:-scale-x-100"
                >→</span
              >
            </a>
          </p>
        </div>
      </div>
    </section>
  </div>
</template>

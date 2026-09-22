<script setup lang="ts">
import { computed } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";

import { useLocalePath } from "@/composables/useLocalePath";
import { useAuthStore } from "@/stores/auth";

/** Suggestion entry point for a single record; `id` is the artist slug or the numeric artwork id. */
const props = defineProps<{ type: "artists" | "artworks"; id: string | number }>();

const { t } = useI18n();
const route = useRoute();
const auth = useAuthStore();
const { localePath } = useLocalePath();

/** Signed-in contributors go straight to the suggestion form; visitors sign in first and come back to it. */
const suggestTarget = computed(() => localePath("suggest", { type: props.type, id: props.id }));
const correctionLink = computed(() =>
  auth.isAuthenticated ? suggestTarget.value : { ...localePath("login"), query: { redirect: `/${route.params.locale}/suggest/${props.type}/${props.id}` } },
);
const canCorrect = computed(() => !auth.isAuthenticated || auth.can("proposals.submit"));
</script>

<template>
  <section class="border border-ink p-6" data-testid="correction-box">
    <h2 class="text-xl font-semibold text-ink">{{ t(`${type}.correction.title`) }}</h2>
    <p class="mt-3 text-pretty text-sm leading-relaxed text-ink-muted">{{ t(`${type}.correction.body`) }}</p>
    <RouterLink v-if="canCorrect" :to="correctionLink" class="mt-5 block border border-ink py-3 text-center font-semibold text-ink hover:bg-ink hover:text-paper" data-testid="send-correction">{{ t(`${type}.correction.send`) }}</RouterLink>
  </section>
</template>

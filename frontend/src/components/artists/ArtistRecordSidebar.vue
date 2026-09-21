<script setup lang="ts">
import { computed } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";

import AlsoKnownAs from "@/components/artists/AlsoKnownAs.vue";
import LifeDates from "@/components/artists/LifeDates.vue";
import LocalizedText from "@/components/common/LocalizedText.vue";
import { useLocalePath } from "@/composables/useLocalePath";
import { useLocalized } from "@/composables/useLocalized";
import { useAuthStore } from "@/stores/auth";
import type { AppLocale } from "@/i18n";
import type { Artist, Bilingual } from "@/types/artist";
import { formatDateTime } from "@/utils/format";
import { formatLifeDates } from "@/utils/lifeDates";

const props = defineProps<{ artist: Artist }>();
const { t, locale } = useI18n();
const route = useRoute();
const auth = useAuthStore();
const { localePath } = useLocalePath();
const { pick } = useLocalized();

const hasText = (v: Bilingual | null | undefined) => Boolean(v?.ar?.trim() || v?.en?.trim());
const life = (d: Artist["birth"] | null) => (d && formatLifeDates(d, locale.value as "ar" | "en") !== null ? d : null);
const birth = computed(() => life(props.artist.birth));
const death = computed(() => life(props.artist.death));
const hasLifeDates = computed(() => birth.value !== null || death.value !== null);

const verified = computed(() => props.artist.verified_status === "verified");
const owner = computed(() => (props.artist.owner_type ? t(`curation.ownerTypes.${props.artist.owner_type}`) : null));
const ownerCity = computed(() => pick(props.artist.birth?.place ?? { ar: null, en: null }));
const recordDate = computed(() => (props.artist.record_date ? new Intl.DateTimeFormat(locale.value, { dateStyle: "medium" }).format(new Date(props.artist.record_date)) : null));

/** Signed-in contributors go straight to the suggestion form; visitors sign in first and come back to it. */
const suggestTarget = computed(() => localePath("suggest", { type: "artists", id: props.artist.id }));
const correctionLink = computed(() =>
  auth.isAuthenticated ? suggestTarget.value : { ...localePath("login"), query: { redirect: `/${route.params.locale}/suggest/artists/${props.artist.id}` } },
);
const canCorrect = computed(() => !auth.isAuthenticated || auth.can("proposals.submit"));
</script>

<template>
  <aside data-testid="record-sidebar">
    <h2 class="border-b-2 border-ink pb-2 text-xs font-semibold text-ink-muted">{{ t("artists.record.title") }}</h2>
    <dl class="divide-y divide-line text-sm">
      <div v-if="recordDate" class="py-4"><dt class="text-xs text-ink-muted">{{ t("artists.record.source") }}</dt><dd class="mt-1 text-lg text-ink" data-testid="record-date">{{ recordDate }}</dd></div>
      <div v-if="owner" class="py-4"><dt class="text-xs text-ink-muted">{{ t("artists.record.owner") }}</dt><dd class="mt-1 text-lg text-ink" data-testid="record-owner">{{ owner }}<template v-if="ownerCity"> — {{ ownerCity.text }}</template></dd></div>
      <div class="py-4">
        <dt class="text-xs text-ink-muted">{{ t("artists.record.nameVerification") }}</dt>
        <dd class="mt-1 text-lg" :class="verified ? 'text-success' : 'text-danger'" data-testid="record-verification">{{ verified ? t("artists.record.verified") : t("artists.record.unverified") }}</dd>
      </div>
      <div class="py-4">
        <dt class="text-xs text-ink-muted">{{ t("artists.record.lifeDates") }}</dt>
        <dd v-if="hasLifeDates" class="mt-1 space-y-1 text-lg text-ink" data-testid="record-life">
          <p v-if="birth"><LifeDates :date="birth" :label="t('artists.born')" /><template v-if="hasText(birth.place)"> · <LocalizedText :text="birth.place" /></template></p>
          <p v-if="death"><LifeDates :date="death" :label="t('artists.died')" /><template v-if="hasText(death.place)"> · <LocalizedText :text="death.place" /></template></p>
        </dd>
        <dd v-else class="mt-1 text-lg text-danger" data-testid="record-life">{{ t("artists.record.notRecorded") }}</dd>
      </div>
      <div class="py-4"><dt class="text-xs text-ink-muted">{{ t("artists.record.updated") }}</dt><dd class="mt-1 text-lg text-ink" data-testid="record-updated">{{ formatDateTime(artist.updated_at, locale as AppLocale) }}</dd></div>
    </dl>

    <AlsoKnownAs v-if="artist.also_known_as.length > 0" class="mt-6" :variants="artist.also_known_as" />

    <section class="mt-8 border border-ink p-6" data-testid="correction-box">
      <h2 class="text-xl font-semibold text-ink">{{ t("artists.correction.title") }}</h2>
      <p class="mt-3 text-pretty text-sm leading-relaxed text-ink-muted">{{ t("artists.correction.body") }}</p>
      <RouterLink v-if="canCorrect" :to="correctionLink" class="mt-5 block border border-ink py-3 text-center font-semibold text-ink hover:bg-ink hover:text-paper" data-testid="send-correction">{{ t("artists.correction.send") }}</RouterLink>
    </section>
  </aside>
</template>

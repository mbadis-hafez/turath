<script setup lang="ts">
import { computed } from "vue";
import { useI18n } from "vue-i18n";

import AlsoKnownAs from "@/components/artists/AlsoKnownAs.vue";
import LifeDates from "@/components/artists/LifeDates.vue";
import LocalizedText from "@/components/common/LocalizedText.vue";
import CorrectionBox from "@/components/proposals/CorrectionBox.vue";
import type { AppLocale } from "@/i18n";
import type { Artist, Bilingual } from "@/types/artist";
import { formatDateTime } from "@/utils/format";
import { formatLifeDates } from "@/utils/lifeDates";

const props = defineProps<{ artist: Artist }>();
const { t, locale } = useI18n();

const hasText = (v: Bilingual | null | undefined) => Boolean(v?.ar?.trim() || v?.en?.trim());
const life = (d: Artist["birth"] | null) => (d && formatLifeDates(d, locale.value as "ar" | "en") !== null ? d : null);
const birth = computed(() => life(props.artist.birth));
const death = computed(() => life(props.artist.death));
const hasLifeDates = computed(() => birth.value !== null || death.value !== null);

const verified = computed(() => props.artist.verified_status === "verified");
const recordDate = computed(() => (props.artist.record_date ? new Intl.DateTimeFormat(locale.value, { dateStyle: "medium" }).format(new Date(props.artist.record_date)) : null));
</script>

<template>
  <aside data-testid="record-sidebar">
    <h2 class="border-b-2 border-ink pb-2 text-xs font-semibold text-ink-muted">{{ t("artists.record.title") }}</h2>
    <dl class="divide-y divide-line text-sm">
      <div v-if="recordDate" class="py-4"><dt class="text-xs text-ink-muted">{{ t("artists.record.source") }}</dt><dd class="mt-1 text-lg text-ink" data-testid="record-date">{{ recordDate }}</dd></div>
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

    <CorrectionBox :id="artist.slug" class="mt-8" type="artists" />
  </aside>
</template>

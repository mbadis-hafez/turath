<script setup lang="ts">
import { useI18n } from "vue-i18n";

import ListEditor from "@/components/curation/ListEditor.vue";
import { newContact } from "@/composables/useArtistProfileForm";
import type { ArtistContact } from "@/types/artistCuration";

const contacts = defineModel<ArtistContact[]>({ required: true });
const { t } = useI18n();

const input = "mt-1 w-full rounded-md border border-line bg-surface px-3 py-1.5 text-sm text-ink focus:border-accent focus:outline-none";
</script>

<template>
  <ListEditor v-model="contacts" :create="newContact" :add-label="t('curation.profileForm.addContact')">
    <template #default="{ item }">
      <div class="grid gap-3 sm:grid-cols-2">
        <label class="text-xs text-ink-muted">{{ t("curation.profileForm.contactName") }}<input v-model="item.name" type="text" :class="input" /></label>
        <label class="text-xs text-ink-muted">{{ t("curation.profileForm.contactRole") }}<input v-model="item.role_note" type="text" :class="input" /></label>
        <label class="text-xs text-ink-muted">{{ t("curation.detail.email") }}<input v-model="item.email" type="email" dir="ltr" :class="input" /></label>
        <label class="text-xs text-ink-muted">{{ t("curation.detail.phone") }}<input v-model="item.phone" type="tel" dir="ltr" :class="input" /></label>
      </div>
    </template>
  </ListEditor>
</template>

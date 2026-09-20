<script setup lang="ts">
import { useI18n } from "vue-i18n";

import ListEditor from "@/components/curation/ListEditor.vue";
import { newSocial } from "@/composables/useArtistProfileForm";
import type { SocialLink, SocialPlatform } from "@/types/artistCuration";

const links = defineModel<SocialLink[]>({ required: true });
const { t } = useI18n();

const PLATFORMS: SocialPlatform[] = ["website", "instagram", "x", "facebook", "youtube", "tiktok", "linkedin", "snapchat", "other"];
const input = "mt-1 w-full rounded-md border border-line bg-surface px-3 py-1.5 text-sm text-ink focus:border-accent focus:outline-none";
</script>

<template>
  <ListEditor v-model="links" :create="newSocial" :add-label="t('curation.profileForm.addSocial')">
    <template #default="{ item }">
      <div class="grid gap-3 sm:grid-cols-[10rem_1fr]">
        <label class="text-xs text-ink-muted">{{ t("curation.profileForm.platform") }}
          <select v-model="item.platform" :class="input">
            <option v-for="p in PLATFORMS" :key="p" :value="p">{{ t(`curation.profileForm.platforms.${p}`) }}</option>
          </select>
        </label>
        <label class="text-xs text-ink-muted">{{ t("curation.profileForm.url") }}<input v-model="item.url" type="url" dir="ltr" placeholder="https://" :class="input" /></label>
      </div>
      <label class="mt-2 flex items-center gap-2 text-xs text-ink">
        <input v-model="item.is_public" type="checkbox" class="size-4" />
        {{ t("curation.profileForm.publicLink") }}
      </label>
    </template>
  </ListEditor>
</template>

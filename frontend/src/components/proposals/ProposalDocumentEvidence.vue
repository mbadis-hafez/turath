<script setup lang="ts">
import { useI18n } from "vue-i18n";

import { ocrRegionCropUrl } from "@/api/archive";
import { CONTACT_PROPOSAL_STATUS_CLASS, useContactProposalText } from "@/composables/useContactProposalText";
import { useLocalePath } from "@/composables/useLocalePath";
import type { ProposalDocumentEvidence } from "@/types/proposalDiff";

/**
 * Where each value in a document-sourced draft came from, for the reviewer
 * deciding it: the section diff says what changes, this says why — which
 * document, page and region, how the value was read and how sure the machine
 * was. The scan itself is one click away for anyone who may open the material.
 */
defineProps<{ evidence: ProposalDocumentEvidence[] }>();
const { t, locale } = useI18n();
const { localePath } = useLocalePath();
const { provenanceLine, fieldLabel, statusLabel } = useContactProposalText();

const documentLabel = (doc: ProposalDocumentEvidence): string => {
  const title = doc.archive_item ? (locale.value === "ar" ? doc.archive_item.title.ar ?? doc.archive_item.title.en : doc.archive_item.title.en ?? doc.archive_item.title.ar) : null;
  return [doc.archive_item?.legacy_ref, title].filter(Boolean).join(" — ") || t("proposals.evidence.untitled");
};
</script>

<template>
  <section class="mt-4 rounded-md border border-line p-3 text-sm" data-testid="proposal-evidence">
    <h3 class="text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ t("proposals.evidence.title") }}</h3>
    <p class="mt-1 text-xs text-ink-muted">{{ t("proposals.evidence.explainer") }}</p>

    <div v-for="doc in evidence" :key="doc.file_id ?? 0" class="mt-3" data-testid="proposal-evidence-document">
      <p class="text-ink">
        {{ t("proposals.evidence.readFrom") }}
        <RouterLink
          v-if="doc.can_open_document && doc.archive_item"
          :to="localePath('admin.archive.show', { id: doc.archive_item.id })"
          target="_blank"
          class="font-medium underline"
          data-testid="proposal-evidence-link"
        >{{ documentLabel(doc) }}</RouterLink>
        <span v-else class="font-medium">{{ documentLabel(doc) }}</span>
      </p>

      <ul class="mt-2 space-y-2">
        <li v-for="v in doc.values" :key="v.id" class="flex flex-wrap items-start gap-3 text-xs" data-testid="proposal-evidence-value">
          <img
            v-if="v.source.has_crop && v.source.region_id !== null && doc.archive_item"
            :src="ocrRegionCropUrl(doc.archive_item.id, v.source.region_id)"
            :alt="t('archive.ocr.artistContact.values.cropAlt', { field: fieldLabel(v) })"
            class="max-h-14 rounded-sm border border-line"
            data-testid="proposal-evidence-crop"
          />
          <div class="flex-1 space-y-0.5">
            <p class="flex flex-wrap items-center gap-2">
              <span class="font-semibold text-ink">{{ fieldLabel(v) }}</span>
              <span class="font-medium text-ink" :dir="v.field === 'address' ? 'auto' : 'ltr'">{{ v.proposed_value }}</span>
              <span class="rounded-sm px-1.5 py-0.5 font-medium" :class="CONTACT_PROPOSAL_STATUS_CLASS[v.status]">{{ statusLabel(v) }}</span>
            </p>
            <p class="text-ink-muted" data-testid="proposal-evidence-provenance">{{ provenanceLine(v) }}</p>
            <p v-if="v.has_correction_mark" class="text-warn" data-testid="proposal-evidence-correction-mark">{{ t("archive.ocr.artistContact.values.correctionMark") }}</p>
            <p v-if="v.replaces_existing && v.status === 'pending'" class="text-warn">{{ t("archive.ocr.artistContact.values.replacesExisting") }}</p>
          </div>
        </li>
      </ul>
    </div>
  </section>
</template>

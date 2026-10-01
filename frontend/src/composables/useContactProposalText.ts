import { useI18n } from "vue-i18n";

import type { AppLocale } from "@/i18n";
import type { ContactProposalValue } from "@/types/ocr";
import { formatDateTime } from "@/utils/format";

/**
 * How a document-sourced contact value is described wherever it appears —
 * the archive page's contact panel and the reviewer's evidence box — so the
 * proposer and the approving reviewer read the same provenance.
 */
export function useContactProposalText() {
  const { t, locale } = useI18n();

  const isUndecided = (v: ContactProposalValue): boolean => v.status === "pending" || v.status === "changes_requested";

  /** Where the value came from, in one line: page, label, how it was read, how sure the machine was. */
  function provenanceLine(v: ContactProposalValue): string {
    const parts: string[] = [];
    if (v.source.page !== null) parts.push(t("archive.ocr.provenance.page", { page: v.source.page }));
    if (v.source.label) parts.push(`“${v.source.label}”`);
    parts.push(v.edited_by_proposer ? t("archive.ocr.artistContact.values.typedByProposer") : t(`archive.ocr.provenance.method.${v.extraction_method}`));
    if (v.confidence !== null) parts.push(t("archive.ocr.artistContact.values.confidence", { value: v.confidence }));
    if (v.machine_suggestion) {
      parts.push(t("archive.ocr.artistContact.values.machineRead", {
        model: v.machine_suggestion.model,
        confidence: v.machine_suggestion.confidence === null ? "—" : `${Math.round(v.machine_suggestion.confidence * 100)}%`,
      }));
    }
    return parts.join(" · ");
  }

  const fieldLabel = (v: ContactProposalValue): string => t(`archive.ocr.artistContact.fields.${v.field}`);
  const statusLabel = (v: ContactProposalValue): string => t(`archive.ocr.artistContact.values.status.${v.status}`);
  const when = (iso: string | null): string => (iso ? formatDateTime(iso, locale.value as AppLocale) : "—");

  return { isUndecided, provenanceLine, fieldLabel, statusLabel, when };
}

export const CONTACT_PROPOSAL_STATUS_CLASS: Record<ContactProposalValue["status"], string> = {
  pending: "bg-warn-soft text-warn",
  changes_requested: "bg-danger-soft text-danger",
  approved: "bg-success-soft text-success",
  rejected: "bg-danger-soft text-danger",
  superseded: "bg-neutral-soft text-ink-muted",
};

import type { ContactProposalValue } from "@/types/ocr";
import type { ProposalConflict } from "@/types/proposal";

/** One changed field inside a section diff. */
export interface DiffField {
  field: string;
  label: { ar: string | null; en: string | null };
  old: unknown;
  new: unknown;
}

/** Added/removed/changed rows of one named collection inside a section diff. */
export interface CollectionDiff {
  key: string;
  added: { label: string; fields: DiffField[] }[];
  removed: { label: string; fields: DiffField[] }[];
  changed: { label: string; fields: DiffField[] }[];
}

/** One named section of an editorial draft proposal diff. */
export interface SectionDiff {
  key: string;
  fields: DiffField[];
  collections: CollectionDiff[];
}

/** A document a proposed value was read from, for the reviewer deciding the draft (contact proposals only so far). */
export interface ProposalDocumentEvidence {
  file_id: number | null;
  archive_item: { id: number; legacy_ref: string | null; title: { ar: string | null; en: string | null } } | null;
  /** Whether the viewer may open the archive item and its crops (archive.manage). */
  can_open_document: boolean;
  values: ContactProposalValue[];
}

/** GET proposals/{id}/diff */
export interface ProposalDiffResponse {
  sections: SectionDiff[];
  /** Drift the reviewer must confirm before approving — empty unless the proposal is pending. */
  conflicts: ProposalConflict[];
  evidence: ProposalDocumentEvidence[] | null;
}

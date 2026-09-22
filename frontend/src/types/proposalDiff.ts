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

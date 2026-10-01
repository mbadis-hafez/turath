/**
 * A name or title handed to a create page in the `prefill` query (the OCR
 * review's "Create"), placed in the Arabic or English box by its script. Only
 * a starting point the curator checks — nothing is saved until they save.
 */
export function prefillFromQuery(raw: unknown): { ar: string; en: string } | null {
  const text = typeof raw === "string" ? raw.trim().slice(0, 255) : "";
  if (text === "") return null;
  return /\p{Script=Arabic}/u.test(text) ? { ar: text, en: "" } : { ar: "", en: text };
}

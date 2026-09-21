import { ACCEPTED_EXTENSIONS, ACCEPTED_TYPES, MAX_TOTAL_BYTES } from "@/types/submission";

export type FileProblem = "type" | "size";

/** Some systems report TIFF or MP3 with an empty type, so fall back to the extension. */
export function isAcceptedFile(file: File): boolean {
  if ((ACCEPTED_TYPES as readonly string[]).includes(file.type)) return true;
  const name = file.name.toLowerCase();
  return file.type === "" && ACCEPTED_EXTENSIONS.some((ext) => name.endsWith(ext));
}

export function totalBytes(files: File[]): number {
  return files.reduce((sum, f) => sum + f.size, 0);
}

/** A courtesy check before anything uploads; the server re-checks by content. */
export function problemWith(existing: File[], incoming: File): FileProblem | null {
  if (!isAcceptedFile(incoming)) return "type";
  if (totalBytes(existing) + incoming.size > MAX_TOTAL_BYTES) return "size";
  return null;
}

export function formatBytes(bytes: number): string {
  if (bytes >= 1073741824) return `${(bytes / 1073741824).toFixed(1)} GB`;
  if (bytes >= 1048576) return `${(bytes / 1048576).toFixed(1)} MB`;
  return `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

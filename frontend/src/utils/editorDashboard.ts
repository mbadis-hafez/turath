import type { AttentionKind, EditorEntityType } from "@/types/editorDashboard";

/** Curation/edit route for each entity type (all gated by *.manage permissions). */
export const RECORD_ROUTE_NAMES: Record<EditorEntityType, string> = {
  artist: "admin.artists.show",
  artwork: "admin.artworks.show",
  event: "admin.events.edit",
  archive_item: "admin.archive.edit",
};

export type GreetingPeriod = "morning" | "afternoon" | "evening";

export function greetingPeriod(date: Date = new Date()): GreetingPeriod {
  const hour = date.getHours();
  if (hour >= 5 && hour < 12) return "morning";
  if (hour >= 12 && hour < 17) return "afternoon";
  return "evening";
}

/** i18n action-key suffix for each attention kind. */
export type AttentionAction = "fix" | "complete" | "continue";

export function attentionActionFor(kind: AttentionKind): AttentionAction {
  switch (kind) {
    case "changes_requested":
      return "fix";
    case "incomplete":
      return "complete";
    default:
      return "continue";
  }
}

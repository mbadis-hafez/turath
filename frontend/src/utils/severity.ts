import type { CompletenessSeverity } from "@/types/completeness";

export type RowAction = "complete" | "resolve" | "follow" | "none";

/** D56: one color per record, chosen by fixed precedence upstream (the API's severity). */
export const SEVERITY_BAR_CLASS: Record<CompletenessSeverity, string> = {
  blocking: "bg-danger",
  conflict: "bg-warn",
  minor: "bg-info",
  pending_review: "bg-info",
  clear: "bg-success",
};

export const SEVERITY_BADGE_CLASS: Record<CompletenessSeverity, string> = {
  blocking: "bg-danger-soft text-danger",
  conflict: "bg-warn-soft text-warn",
  minor: "bg-info-soft text-info",
  pending_review: "bg-info-soft text-info",
  clear: "bg-success-soft text-success",
};

/** Primary action per state: fill gaps, resolve a conflict, or follow the review item. */
export function rowAction(severity: CompletenessSeverity): RowAction {
  switch (severity) {
    case "blocking":
    case "minor":
      return "complete";
    case "conflict":
      return "resolve";
    case "pending_review":
      return "follow";
    default:
      return "none";
  }
}

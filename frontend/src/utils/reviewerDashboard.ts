import type { RouteLocationRaw } from "vue-router";

import type { useLocalePath } from "@/composables/useLocalePath";
import type { AppLocale } from "@/i18n";

type LocalePathFn = ReturnType<typeof useLocalePath>["localePath"];

export type GreetingPeriod = "morning" | "afternoon" | "evening";

export function greetingPeriod(date: Date = new Date()): GreetingPeriod {
  const hour = date.getHours();
  if (hour >= 5 && hour < 12) return "morning";
  if (hour >= 12 && hour < 17) return "afternoon";
  return "evening";
}

/** review_type values that map onto EditProposal (excludes material_intake, which never proposes). */
const PROPOSAL_REVIEW_TYPES = new Set([
  "editorial_review",
  "archivist_review",
  "data_audit",
  "second_source_needed",
]);

/**
 * Where "Review" on a queue/verification row should send the reviewer.
 * Material submissions have their own review page; everything else funnels
 * through the existing /proposals list, filtered to the review_type. There
 * is no per-row deep link today (ProposalsPage.vue has no per-proposal
 * route) — this lands on the filtered list, not a fixed detail page.
 */
export function reviewLinkFor(
  localePath: LocalePathFn,
  citableType: string | null,
  reviewType?: string,
): RouteLocationRaw {
  if (citableType === "material_submission") {
    return localePath("admin.materials");
  }

  const query: Record<string, string> = { status: "pending" };
  if (reviewType && PROPOSAL_REVIEW_TYPES.has(reviewType)) {
    query.review_type = reviewType;
  }
  return localePath("proposals", {}, query);
}

export function localeDateFormatter(locale: AppLocale): Intl.DateTimeFormat {
  return new Intl.DateTimeFormat(locale, { dateStyle: "full", numberingSystem: "latn" });
}

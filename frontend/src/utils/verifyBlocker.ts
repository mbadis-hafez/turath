/** The first blocking reason returned by the API, for the disabled Verify tooltip (D91/D96). */
export function firstBlockReason(blockers: Record<string, string[]>): string | null {
  for (const messages of Object.values(blockers)) {
    if (messages.length > 0) return messages[0] ?? null;
  }
  return null;
}

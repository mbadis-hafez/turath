import { reactive } from "vue";

export type DateMode = "exact" | "year" | "approx";

export interface FuzzyDate {
  mode: DateMode;
  date: string;
  year: string;
  text: string;
  from: string;
  to: string;
  certainty: "circa" | "range";
  note: string;
}

export interface DateContent {
  display: string | null;
  year_from: number | null;
  year_to: number | null;
  certainty: string | null;
}

const blank = (v: string | null | undefined): string | null => (v && v.trim() !== "" ? v.trim() : null);
// Number inputs bind numbers through v-model, so coerce before validating.
const validYear = (v: string | number): number | null => (/^\d{4}$/.test(String(v).trim()) ? Number.parseInt(String(v), 10) : null);
const exact = (year: number, display: string): Record<string, unknown> =>
  ({ display, year_from: year, year_to: year, calendar: "gregorian", certainty: "exact" });

/** "YYYY-MM-DD" → the API's exact date, or null when it isn't a full calendar date. */
export function dateToContent(input: string): Record<string, unknown> | null {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(input)) return null;
  return exact(Number.parseInt(input.slice(0, 4), 10), input);
}

/**
 * A date that is exact, a bare year, or approximate/range with a stated reason,
 * the one rule artists, artworks, archive items and events all share.
 */
export function useFuzzyDate() {
  const state = reactive<FuzzyDate>({ mode: "exact", date: "", year: "", text: "", from: "", to: "", certainty: "circa", note: "" });
  let initial = "null";

  function build(): Record<string, unknown> | null {
    if (state.mode === "exact") return dateToContent(state.date);
    if (state.mode === "year") {
      const y = validYear(state.year);
      return y === null ? null : exact(y, String(y));
    }
    const from = validYear(state.from);
    if (from === null) return null;
    const to = validYear(state.to) ?? from;
    return {
      display: blank(state.text) ?? (from === to ? String(from) : `${from}–${to}`),
      year_from: from, year_to: Math.max(from, to), calendar: "gregorian", certainty: state.certainty,
    };
  }

  function load(content: DateContent | null, note: string | null): void {
    const display = content?.display ?? "";
    Object.assign(state, { date: "", year: "", text: "", from: "", to: "", note: note ?? "" });
    state.certainty = content?.certainty === "range" ? "range" : "circa";
    if (/^\d{4}-\d{2}-\d{2}$/.test(display)) {
      state.mode = "exact"; state.date = display;
    } else if (content?.certainty === "exact" && content.year_from !== null && content.year_from === content.year_to && /^\d{4}$/.test(display)) {
      state.mode = "year"; state.year = display;
    } else if (content?.year_from) {
      state.mode = "approx"; state.text = display; state.from = String(content.year_from); state.to = content.year_to ? String(content.year_to) : "";
    } else {
      state.mode = "exact";
    }
    initial = JSON.stringify(build());
  }

  /** The reason counts only for approximate dates; an exact one never needs it. */
  function met(): boolean {
    const c = build();
    return c !== null && (c.certainty === "exact" || blank(state.note) !== null);
  }

  return { state, build, load, met, changed: () => JSON.stringify(build()) !== initial, note: () => blank(state.note) };
}

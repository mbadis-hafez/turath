export const ACTIVITY_EVENTS = [
  "created",
  "updated",
  "deleted",
  "restored",
] as const;

export type ActivityEvent = (typeof ACTIVITY_EVENTS)[number];

export interface ActivityChange {
  field: string;
  label: { ar: string; en: string };
  old: unknown;
  new: unknown;
}

export interface ActivityCauser {
  id: number;
  name: string;
}

export interface ActivityEntry {
  id: number;
  event: ActivityEvent;
  subject_type: string;
  subject_id: number;
  subject_label: string;
  causer: ActivityCauser | null;
  edit_summary: string | null;
  changes: ActivityChange[];
  created_at: string;
}

export interface ActivityFilters {
  subject_type?: string;
  subject_id?: number;
  causer_id?: number;
  event?: ActivityEvent;
  date_from?: string;
  date_to?: string;
  page?: number;
}

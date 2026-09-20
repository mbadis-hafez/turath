import type { Bilingual, PartialDate } from "@/types/artist";
import type { ArchiveItem } from "@/types/archive";
import type { Theme } from "@/types/artistCuration";
import type { PaginationMeta } from "@/types/api";

export const EVENT_TYPES = ["exhibition", "symposium", "award", "talk", "festival", "biennial", "other"] as const;
export type EventType = (typeof EVENT_TYPES)[number];
export const PARTICIPANT_ROLES = ["participant", "awardee", "organizer", "juror", "exhibited_work"] as const;
export type ParticipantRole = (typeof PARTICIPANT_ROLES)[number];
export type EventStatus = "draft" | "published" | "hidden";

export interface EventSummary {
  id: number;
  event_type: EventType;
  title: Bilingual;
  start: PartialDate | null;
  end: PartialDate | null;
  venue_name: string | null;
  city: string | null;
  publication_status: EventStatus;
}

export interface EventListItem extends EventSummary {
  participant_count: number;
}

export interface AdminEventRow extends EventListItem {
  completeness_pct: number;
  gap_count: number;
}

export interface EventParticipantRow {
  id: number;
  role: ParticipantRole;
  note: string | null;
  kind: "artist" | "artwork";
  entity: { id: number; slug?: string; name?: Bilingual; title?: Bilingual };
}

export interface EventDetail extends EventSummary {
  description: Bilingual;
  date_note: string | null;
  access_level: string;
  holder: { id: number; name: Bilingual; city: Bilingual } | null;
  themes: Theme[];
  participants: EventParticipantRow[];
  archive_items: ArchiveItem[];
  completeness?: { pct: number; blocking: string[]; minor: string[] };
}

export interface ParticipantPayload {
  id?: number;
  type: "artist" | "artwork";
  participant_id: number;
  role: ParticipantRole;
  note: string | null;
}

export type TimelineKind = "event" | "artist_lifespan" | "artwork";

export interface TimelineEntry {
  kind: TimelineKind;
  id: number;
  year_from: number;
  year_to: number | null;
  display: string | null;
  title?: Bilingual;
  name?: Bilingual;
  slug?: string;
  event_type?: EventType;
  artist?: Bilingual | null;
}

export interface TimelineBucket {
  year: number;
  entries: TimelineEntry[];
}

export interface EventsQuery {
  q?: string;
  event_type?: EventType;
  status?: string;
  page?: number;
}

export type EventsPage<T> = { data: T[]; meta: PaginationMeta };

/** An event as listed on an artist or artwork page, with that record's role in it. */
export interface PublicEventRef extends EventSummary {
  role: ParticipantRole;
  note: string | null;
}

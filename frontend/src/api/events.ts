import { request } from "@/api/http";
import type { PaginationMeta } from "@/types/api";
import type {
  AdminEventRow, EventDetail, EventsQuery, EventListItem, ParticipantPayload, TimelineBucket, TimelineKind,
} from "@/types/event";

type Meta = Omit<PaginationMeta, "from" | "to">;
const withRange = (meta: Meta): PaginationMeta => ({ ...meta, from: null, to: null });

function clean(params: Record<string, unknown>): Record<string, string | number> {
  const out: Record<string, string | number> = {};
  for (const [k, v] of Object.entries(params)) if (v !== undefined && v !== "") out[k] = v as string | number;
  return out;
}

export async function listAdminEvents(params: EventsQuery, signal?: AbortSignal): Promise<{ data: AdminEventRow[]; meta: PaginationMeta }> {
  const r = await request<{ data: AdminEventRow[]; meta: Meta }>({ method: "GET", url: "/api/v1/admin/events", params: clean({ ...params }), signal });
  return { data: r.data, meta: withRange(r.meta) };
}

export async function listEvents(params: Record<string, unknown>, signal?: AbortSignal): Promise<{ data: EventListItem[]; meta: PaginationMeta }> {
  const r = await request<{ data: EventListItem[]; meta: Meta }>({ method: "GET", url: "/api/v1/events", params: clean(params), signal });
  return { data: r.data, meta: withRange(r.meta) };
}

export function getEvent(id: number, signal?: AbortSignal): Promise<{ data: EventDetail }> {
  return request({ method: "GET", url: `/api/v1/events/${id}`, signal });
}

export function createEvent(payload: Record<string, unknown>): Promise<{ data: EventDetail }> {
  return request({ method: "POST", url: "/api/v1/events", data: payload });
}

export function updateEvent(id: number, payload: Record<string, unknown>): Promise<{ data: EventDetail }> {
  return request({ method: "PATCH", url: `/api/v1/events/${id}`, data: payload });
}

export function publishEvent(id: number): Promise<{ data: EventDetail }> {
  return request({ method: "POST", url: `/api/v1/events/${id}/publish` });
}

export function syncEventParticipants(id: number, participants: ParticipantPayload[]): Promise<unknown> {
  return request({ method: "PATCH", url: `/api/v1/events/${id}/participants`, data: { participants } });
}

export function syncEventThemes(id: number, themeIds: number[]): Promise<unknown> {
  return request({ method: "PATCH", url: `/api/v1/events/${id}/themes`, data: { theme_ids: themeIds } });
}

export async function getTimeline(
  params: { from?: number; to?: number; type: TimelineKind[]; theme_id: number[] },
  signal?: AbortSignal,
): Promise<{ data: TimelineBucket[]; meta: { total: number } }> {
  return request({
    method: "GET", url: "/api/v1/timeline", signal,
    params: { ...clean({ from: params.from, to: params.to }), type: params.type, theme_id: params.theme_id },
    paramsSerializer: { indexes: false },
  });
}

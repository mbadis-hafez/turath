import { reactive, ref } from "vue";

import { labelOf } from "@/components/curation/ArtworkPickers";
import type { PickerOption } from "@/components/curation/EntityPicker.vue";
import { dateToContent, useFuzzyDate } from "@/composables/useFuzzyDate";
import type { EventDetail, EventType, ParticipantPayload, ParticipantRole } from "@/types/event";

const blank = (v: string | null | undefined): string | null => (v && v.trim() !== "" ? v.trim() : null);

export interface ParticipantRow {
  id?: number;
  kind: "artist" | "artwork";
  entity: PickerOption | null;
  role: ParticipantRole;
  note: string;
}

export const newParticipant = (): ParticipantRow => ({ kind: "artist", entity: null, role: "participant", note: "" });

/** Editable event fields shared by the add and edit modes. */
export function useEventForm() {
  const form = reactive({
    type: "exhibition" as EventType, titleAr: "", titleEn: "", descriptionAr: "", descriptionEn: "",
    venue: "", city: "", endDate: "",
  });
  const holder = ref<PickerOption | null>(null);
  const participants = ref<ParticipantRow[]>([]);
  const themeIds = ref<number[]>([]);
  const start = useFuzzyDate();

  function load(e: EventDetail): void {
    form.type = e.event_type;
    form.titleAr = e.title.ar ?? ""; form.titleEn = e.title.en ?? "";
    form.descriptionAr = e.description.ar ?? ""; form.descriptionEn = e.description.en ?? "";
    form.venue = e.venue_name ?? ""; form.city = e.city ?? "";
    form.endDate = /^\d{4}-\d{2}-\d{2}$/.test(e.end?.display ?? "") ? (e.end?.display ?? "") : "";
    start.load(e.start, e.date_note);
    holder.value = e.holder ? { id: e.holder.id, label: labelOf(e.holder.name) } : null;
    themeIds.value = e.themes.map((t) => t.id);
    participants.value = e.participants.map((p) => ({
      id: p.id, kind: p.kind, role: p.role, note: p.note ?? "",
      entity: { id: p.entity.id, label: labelOf((p.kind === "artist" ? p.entity.name : p.entity.title) ?? { ar: null, en: null }) },
    }));
  }

  /** On existing events the dates are sent only when edited, so an untouched date survives byte for byte. */
  function payload(mode: "create" | "update", originalEnd = ""): Record<string, unknown> {
    const body: Record<string, unknown> = {
      event_type: form.type,
      title: { ar: blank(form.titleAr), en: blank(form.titleEn) },
      description: { ar: blank(form.descriptionAr), en: blank(form.descriptionEn) },
      venue_name: blank(form.venue),
      city: blank(form.city),
      holder_id: holder.value?.id ?? null,
      date_note: start.note(),
    };
    if (mode === "create" || start.changed()) body.start = start.build();
    if (mode === "create" ? form.endDate !== "" : form.endDate !== originalEnd) body.end = dateToContent(form.endDate);
    if (mode === "create") body.publication_status = "draft";
    return body;
  }

  function participantsPayload(): ParticipantPayload[] {
    return participants.value
      .filter((p) => p.entity !== null)
      .map((p) => ({ ...(p.id ? { id: p.id } : {}), type: p.kind, participant_id: p.entity!.id, role: p.role, note: blank(p.note) }));
  }

  /** Live checklist mirroring the server's rules, used before the event exists. */
  function checklist(): { key: string; met: boolean }[] {
    return [
      { key: "title", met: blank(form.titleAr) !== null || blank(form.titleEn) !== null },
      { key: "event_type", met: true },
      { key: "date", met: start.met() },
      { key: "venue_name", met: blank(form.venue) !== null },
      { key: "city", met: blank(form.city) !== null },
      { key: "holder", met: holder.value !== null },
      { key: "description", met: blank(form.descriptionAr) !== null || blank(form.descriptionEn) !== null },
      { key: "participants", met: participantsPayload().length > 0 },
    ];
  }

  return { form, holder, participants, themeIds, start: start.state, load, payload, participantsPayload, checklist };
}

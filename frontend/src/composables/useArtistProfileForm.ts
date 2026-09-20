import { reactive } from "vue";

import type { ArtistProfilePayload } from "@/api/artistCuration";
import type {
  ArtistContact,
  ArtistCuration,
  DateValue,
  EntryGroups,
  Localized,
  ProfileEntry,
  SocialLink,
} from "@/types/artistCuration";

export const emptyLocalized = (): Localized => ({ ar: null, en: null });

export const newEntry = (): ProfileEntry => ({
  title: emptyLocalized(),
  place: emptyLocalized(),
  year_from: null,
  year_to: null,
  note: emptyLocalized(),
});
export const newActivity = (): ProfileEntry => ({
  type: "exhibition",
  title: emptyLocalized(),
  place: emptyLocalized(),
  year_from: null,
  year_to: null,
  note: emptyLocalized(),
});
export const newContact = (): ArtistContact => ({ name: null, role_note: null, email: null, phone: null });
export const newSocial = (): SocialLink => ({ platform: "instagram", url: "", is_public: false });

const blank = (v: string | null | undefined): string | null => (v && v.trim() !== "" ? v.trim() : null);
const blankLocalized = (l: Localized): Localized => ({ ar: blank(l.ar), en: blank(l.en) });

/** "YYYY-MM-DD" (or a bare year) → the API's exact PartialDate; empty → null. */
export function dateInputToValue(input: string | null): DateValue | null {
  const value = blank(input);
  if (!value) return null;
  const year = Number.parseInt(value.slice(0, 4), 10);
  if (!Number.isFinite(year)) return null;
  return { display: value, year_from: year, year_to: year, calendar: "gregorian", certainty: "exact" };
}

export function dateValueToInput(value: DateValue | null): string {
  if (!value) return "";
  if (value.display && /^\d{4}-\d{2}-\d{2}$/.test(value.display)) return value.display;
  return "";
}

/** Shared editable profile state for the add-artist and curation pages. */
export function useArtistProfileForm() {
  const form = reactive({
    nationality: emptyLocalized(),
    classification: emptyLocalized(),
    birthDate: "",
    deathDate: "",
    entries: { educations: [], activities: [] } as EntryGroups,
    contacts: [] as ArtistContact[],
    socialLinks: [] as SocialLink[],
  });
  const initial = { birthDate: "", deathDate: "" };

  function load(c: ArtistCuration): void {
    form.nationality = { ...c.nationality };
    form.classification = { ...c.classification };
    form.birthDate = initial.birthDate = dateValueToInput(c.birth);
    form.deathDate = initial.deathDate = dateValueToInput(c.death);
    form.entries = JSON.parse(JSON.stringify(c.entries)) as EntryGroups;
    form.contacts = c.contacts.map((x) => ({ ...x }));
    form.socialLinks = c.social_links.map((x) => ({ ...x }));
  }

  /** Fields for POST/PATCH /artists. Birth keeps the artist's existing place so a date edit never wipes it. */
  function profilePayload(birthPlace?: Localized, onlyChangedDates = false): ArtistProfilePayload {
    const birth = dateInputToValue(form.birthDate);
    const payload: ArtistProfilePayload = {
      nationality: blankLocalized(form.nationality),
      classification: blankLocalized(form.classification),
    };
    // On existing records a date is only sent when edited, so dates the
    // date picker can't represent (circa, ranges, hijri) are never overwritten.
    if (!onlyChangedDates || form.birthDate !== initial.birthDate) {
      payload.birth = birth || birthPlace ? { ...(birth ?? {}), place: birthPlace ? blankLocalized(birthPlace) : undefined } : null;
    }
    if (!onlyChangedDates || form.deathDate !== initial.deathDate) {
      payload.death = dateInputToValue(form.deathDate);
    }
    return payload;
  }

  function entriesPayload(): EntryGroups {
    const clean = (list: ProfileEntry[]): ProfileEntry[] =>
      list
        .filter((e) => blank(e.title.ar) || blank(e.title.en))
        .map((e) => ({
          ...(e.id ? { id: e.id } : {}),
          ...(e.type ? { type: e.type } : {}),
          title: blankLocalized(e.title),
          place: blankLocalized(e.place),
          year_from: e.year_from || null,
          year_to: e.year_to || null,
          note: blankLocalized(e.note),
        }));
    return {
      educations: clean(form.entries.educations),
      activities: clean(form.entries.activities),
    };
  }

  function socialPayload(): SocialLink[] {
    return form.socialLinks
      .filter((l) => blank(l.url))
      .map((l) => ({ ...(l.id ? { id: l.id } : {}), platform: l.platform, url: l.url.trim(), is_public: l.is_public }));
  }

  function contactsPayload(): ArtistContact[] {
    return form.contacts
      .filter((c) => blank(c.name) || blank(c.email) || blank(c.phone))
      .map((c) => ({
        ...(c.id ? { id: c.id } : {}),
        name: blank(c.name),
        role_note: blank(c.role_note),
        email: blank(c.email),
        phone: blank(c.phone),
      }));
  }

  return { form, load, profilePayload, entriesPayload, socialPayload, contactsPayload };
}

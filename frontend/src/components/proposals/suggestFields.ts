import type { Artist } from "@/types/artist";
import type { Artwork } from "@/types/artwork";
import type { RecordType } from "@/types/proposal";

export interface SuggestField {
  /** The backend column name; must be in the server's proposable allow-list. */
  key: string;
  input: "text" | "textarea" | "number" | "select";
  dir?: "rtl" | "ltr";
  options?: string[];
}

/**
 * The fields a contributor may correct, per record type. A deliberate subset of
 * the server's allow-list — the server rejects anything outside it either way,
 * so this only decides what the form offers.
 */
export const SUGGEST_FIELDS: Record<"artists" | "artworks", SuggestField[]> = {
  artists: [
    { key: "name_ar", input: "text", dir: "rtl" },
    { key: "name_en", input: "text", dir: "ltr" },
    { key: "bio_ar", input: "textarea", dir: "rtl" },
    { key: "bio_en", input: "textarea", dir: "ltr" },
    { key: "birth_year_from", input: "number" },
    { key: "death_year_from", input: "number" },
    { key: "living_status", input: "select", options: ["unknown", "living", "deceased"] },
    { key: "birth_place_ar", input: "text", dir: "rtl" },
    { key: "birth_place_en", input: "text", dir: "ltr" },
    { key: "nationality_ar", input: "text", dir: "rtl" },
    { key: "nationality_en", input: "text", dir: "ltr" },
  ],
  artworks: [
    { key: "title_ar", input: "text", dir: "rtl" },
    { key: "title_en", input: "text", dir: "ltr" },
    { key: "medium_ar", input: "text", dir: "rtl" },
    { key: "medium_en", input: "text", dir: "ltr" },
    { key: "creation_year_from", input: "number" },
    { key: "height_cm", input: "number" },
    { key: "width_cm", input: "number" },
    { key: "notes_ar", input: "textarea", dir: "rtl" },
    { key: "notes_en", input: "textarea", dir: "ltr" },
  ],
};

export const supportsSuggestions = (type: string): type is "artists" | "artworks" =>
  type === "artists" || type === "artworks";

/** The public API returns nested shapes; proposals are column-level, so flatten. */
export function flattenArtist(a: Artist): Record<string, unknown> {
  return {
    name_ar: a.name.ar, name_en: a.name.en, bio_ar: a.bio.ar, bio_en: a.bio.en,
    birth_year_from: a.birth?.year_from ?? null, death_year_from: a.death?.year_from ?? null,
    living_status: a.living_status,
    birth_place_ar: a.birth?.place?.ar ?? null, birth_place_en: a.birth?.place?.en ?? null,
    nationality_ar: null, nationality_en: null,
  };
}

export function flattenArtwork(a: Artwork): Record<string, unknown> {
  return {
    title_ar: a.title.ar, title_en: a.title.en, medium_ar: a.medium.ar, medium_en: a.medium.en,
    creation_year_from: a.creation?.year_from ?? null,
    height_cm: a.dimensions.height_cm, width_cm: a.dimensions.width_cm,
    notes_ar: a.notes.ar, notes_en: a.notes.en,
  };
}

export const labelKeyFor = (type: RecordType, field: string): string => `proposals.fields.${type}.${field}`;

import type { ActivityEntry, ActivityEvent } from "@/types/activity";
import type { PaginatedResponse } from "@/types/api";
import type { PublicEventRef } from "@/types/event";

export interface Bilingual {
  ar: string | null;
  en: string | null;
}

export interface PartialDate {
  display: string | null;
  year_from: number | null;
  year_to: number | null;
  calendar: "gregorian" | "hijri";
  certainty: "exact" | "circa" | "range" | "unknown";
}

export type LivingStatus = "living" | "deceased" | "unknown";
export type VerifiedStatus = "unverified" | "verified" | "disputed";

export interface ArtistListItem {
  id: number;
  slug: string;
  name: Bilingual;
  city: Bilingual;
  birth: PartialDate | null;
  death: PartialDate | null;
  living_status: LivingStatus;
  verified_status: VerifiedStatus;
  /** Null unless published with a clear-rights portrait (ArtistListResource). */
  portrait_url: string | null;
  /** Number of published archive items linked to this artist. */
  materials_count?: number;
}

export type NameVariantLanguage = "ar" | "en" | "und";
export type NameVariantType =
  "transliteration" | "alias" | "birth_name" | "pen_name" | "typo" | "other";

export interface NameVariant {
  id: number;
  name: string;
  language: NameVariantLanguage;
  type: NameVariantType;
  source_note: string | null;
}

export interface Artist extends ArtistListItem {
  legacy_code: string | null;
  events?: PublicEventRef[];
  themes?: { id: number; label: Bilingual }[];
  nationality?: Bilingual;
  record_date?: string | null;
  bio: Bilingual;
  birth: (PartialDate & { place: Bilingual }) | null;
  death: (PartialDate & { place: Bilingual }) | null;
  also_known_as: NameVariant[];
  verified_at: string | null;
  publication_status: "draft" | "published" | "hidden";
  created_at: string;
  updated_at: string;
}

export type ArtistSort =
  | "name_ar"
  | "-name_ar"
  | "name_en"
  | "-name_en"
  | "created_at"
  | "-created_at"
  | "-materials_count";

export interface ArtistFacetValue<T = string> {
  value: T;
  count: number;
  label?: Bilingual;
}

export interface ArtistFacets {
  city: ArtistFacetValue<string>[];
  theme_id: ArtistFacetValue<number>[];
  item_type: ArtistFacetValue<string>[];
}

export interface ArtistLetters {
  ar: string[];
  en: string[];
}

export interface ArtistsQueryParams {
  q?: string;
  verified_status?: "verified" | "unverified" | "disputed";
  living_status?: LivingStatus;
  city?: string;
  theme_id?: number;
  item_type?: string;
  include_facets?: 1;
  sort?: ArtistSort;
  page?: number;
  per_page?: number;
}

export interface ArtistActivityQueryParams {
  event?: ActivityEvent;
  date_from?: string;
  date_to?: string;
  page?: number;
}

export type ArtistActivityResponse = PaginatedResponse<ActivityEntry>;

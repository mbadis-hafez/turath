import type { Bilingual, PartialDate } from "@/types/artist";
import type { Holder } from "@/types/holder";

export type ArtworkCategory =
  | "painting"
  | "drawing"
  | "printmaking"
  | "sculpture"
  | "mixed_media"
  | "paper_work"
  | "photography"
  | "installation"
  | "other";

export type AttributionCertainty =
  | "confirmed"
  | "attributed"
  | "disputed"
  | "unattributed";

export interface ArtistStub {
  id: number;
  slug: string;
  name: Bilingual;
}

export interface Dimensions {
  height_cm: number | null;
  width_cm: number | null;
  depth_cm: number | null;
  raw: string | null;
}

export interface ArtworkListItem {
  id: number;
  legacy_ref: string | null;
  title: Bilingual;
  is_untitled: boolean;
  artist: ArtistStub | null;
  attribution_certainty: AttributionCertainty;
  category: ArtworkCategory;
  medium: Bilingual;
  creation: PartialDate | null;
  dimensions: Dimensions;
}

export interface Artwork extends ArtworkListItem {
  frame_dimensions: Dimensions | null;
  weight_kg: number | null;
  signed: "signed" | "unsigned" | "unknown";
  edition: { number: string | null; size: number | null } | null;
  holder: Holder | null;
  holder_inventory_no: string | null;
  notes: Bilingual;
  publication_status: "draft" | "published" | "hidden";
  created_at: string;
  updated_at: string;
}

export type ArtworkSort =
  | "-created_at"
  | "created_at"
  | "title_ar"
  | "title_en"
  | "creation_year_from"
  | "-creation_year_from";

export interface ArtworksQueryParams {
  q?: string;
  artist_id?: number;
  artist_slug?: string;
  category?: ArtworkCategory;
  holder_id?: number;
  year_from?: number;
  year_to?: number;
  attribution_certainty?: AttributionCertainty;
  sort?: ArtworkSort;
  page?: number;
  per_page?: number;
}

export type ArtistArtworksQueryParams = Omit<
  ArtworksQueryParams,
  "artist_id" | "artist_slug"
>;

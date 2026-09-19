import type { Bilingual } from "@/types/artist";

export type HolderType =
  | "institution"
  | "private_collector"
  | "family"
  | "artist_estate"
  | "other";

export interface Holder {
  id: number;
  type: HolderType;
  display_name: Bilingual;
  city: Bilingual;
  country: Bilingual;
}

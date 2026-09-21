import type { Bilingual } from "@/types/artist";

export interface HomeArchiveItem {
  id: number;
  item_type: string;
  title: Bilingual;
  content: { display: string | null; year_from: number | null; year_to: number | null } | null;
  creator_name: string | null;
  description: Bilingual | null;
  restricted: boolean;
}

export interface HomeTheme {
  id: number;
  label: Bilingual;
  count: number;
}

export interface HomeArtist {
  id: number;
  slug: string;
  name: Bilingual;
  materials_count: number;
}

export interface HomePlace {
  name: Bilingual;
  materials_count: number;
}

export interface HomeOverview {
  stats: { materials: number; artists: number; artworks: number; sources: number };
  updated_at: string | null;
  popular_searches: { term: Bilingual }[];
  themes: HomeTheme[];
  archive_feature: HomeArchiveItem | null;
  recent_archive_items: HomeArchiveItem[];
  artists: HomeArtist[];
  places: HomePlace[];
}

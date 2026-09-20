import type { Localized } from "@/types/artistCuration";
import type { CompletenessSeverity } from "@/types/completeness";

export type ArtworkStatus = "draft" | "published" | "hidden";
export type ArtworkFlag = "untitled" | "missing_dimensions" | "holder_missing" | "year_uncertain";

export interface AdminArtworkRow {
  id: number;
  title: Localized;
  is_untitled: boolean;
  artist: { id: number; name: Localized } | null;
  holder_id: number | null;
  holder: { id: number; name: Localized } | null;
  year: string | null;
  flags: ArtworkFlag[];
  publication_status: ArtworkStatus;
  missing_dimensions: boolean;
  completeness_pct: number;
  severity: CompletenessSeverity;
  pipeline: { cleared: number; total: number };
  merged_into_id: number | null;
}

export interface AdminArtworksQuery {
  q?: string;
  status?: ArtworkStatus | "";
  missing_dimensions?: 1;
  has_pipeline_gap?: 1;
  page?: number;
}

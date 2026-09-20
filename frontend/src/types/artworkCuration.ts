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
  thumbnail_url: string | null;
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
  artist_id?: number;
  holder_id?: number;
  page?: number;
}

import type { DateValue } from "@/types/artistCuration";

export type PipelineStatus = "not_started" | "in_progress" | "tbc" | "done" | "not_applicable";
export const PIPELINE_STATUSES: PipelineStatus[] = ["not_started", "in_progress", "tbc", "done", "not_applicable"];
export type ConditionStatus = "not_available" | "pending" | "available";
export type ImageQuality = "low_resolution" | "high_resolution" | "archive_source";
export type SignedState = "signed" | "unsigned" | "unknown";

export interface Dims {
  height_cm: number | string | null;
  width_cm: number | string | null;
  depth_cm: number | string | null;
  raw: string | null;
}

export interface ArtworkCuration {
  id: number;
  legacy_ref: string | null;
  title: Localized;
  is_untitled: boolean;
  artist: { id: number; name: Localized } | null;
  attribution_certainty: string;
  category: string;
  medium: Localized;
  creation: DateValue | null;
  signed: SignedState;
  material_classification: MaterialClassification;
  conservation_risk_note: string | null;
  notes: Localized;
  edition: { number: string | null; size: number | null };
  dimensions: Dims;
  frame_dimensions: Dims;
  weight_kg: number | string | null;
  holder: { id: number; name: Localized } | null;
  holder_inventory_no: string | null;
  inventory_by_owner: string | null;
  condition_report_link: string | null;
  condition_report_status: ConditionStatus | null;
  image_quality: ImageQuality | null;
  editing_status: string | null;
  has_final_hr_image: boolean;
  images: ArtworkImage[];
  publication_status: ArtworkStatus;
  completeness: { pct: number; severity: CompletenessSeverity; blocking: string[]; minor: string[] };
  checklist: { key: string; tier: "core" | "important"; met: boolean }[];
  approve_blockers: Record<string, string[]>;
  pipeline: { stage_key: string; status: PipelineStatus; note: string | null }[];
  linked_materials: { id: number; legacy_ref: string | null; item_type: string; title: Localized; completeness_pct: number }[];
}

export type ImageRights = "unknown" | "licensed" | "public_domain" | "all_rights_reserved";

export interface ArtworkImage {
  id: number;
  url: string;
  filename: string | null;
  width_px: number | null;
  height_px: number | null;
  size_bytes: number;
  rights_status: ImageRights;
  is_final: boolean;
}

export type MaterialClassification = "movable" | "immovable" | "digital_native" | "unspecified";

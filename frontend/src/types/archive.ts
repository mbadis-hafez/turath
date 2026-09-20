import type { Bilingual, PartialDate } from "@/types/artist";

export const ARCHIVE_ITEM_TYPES = [
  "article", "image", "video", "audio", "catalogue", "certificate", "invitation", "poster", "document", "portfolio", "documentation_card", "other",
] as const;
export type ArchiveItemType = (typeof ARCHIVE_ITEM_TYPES)[number];
export type AccessLevel = "public" | "registered" | "researcher" | "institution_only" | "embargoed";
export type RightsStatus = "public_domain" | "licensed" | "all_rights_reserved" | "unknown";

export interface ArchiveLink {
  role: string;
  artist?: { id: number; slug: string; name: Bilingual };
  artwork?: { id: number; title: Bilingual };
}

export interface ArchiveItem {
  id: number;
  legacy_ref: string | null;
  item_type: ArchiveItemType;
  title: Bilingual;
  content: PartialDate | null;
  access_level: AccessLevel;
  publication_status: "draft" | "published" | "hidden";
  restricted: boolean;
  rights_status?: RightsStatus;
  creator_name?: string | null;
  links?: ArchiveLink[];
}

export interface ArchiveQueryParams {
  q?: string;
  item_type?: ArchiveItemType;
  status?: "all";
  sort?: "-created_at" | "created_at" | "content_year_from" | "-content_year_from";
  page?: number;
}

export type ArchiveRowStatus = "draft" | "published" | "hidden" | "incomplete" | "under_review";

export interface AdminArchiveRow {
  id: number;
  legacy_ref: string | null;
  item_type: ArchiveItemType;
  title: Bilingual;
  date: string | null;
  source: { name: Bilingual; rights_status: RightsStatus };
  publication_status: "draft" | "published" | "hidden";
  incomplete: boolean;
  gap_count: number;
  under_review: boolean;
}

export interface AdminArchiveQuery {
  q?: string;
  item_type?: ArchiveItemType;
  status?: ArchiveRowStatus;
  rights_status?: RightsStatus;
  year_from?: number;
  year_to?: number;
  mine?: 1;
  page?: number;
}

export interface BulkResult {
  succeeded: number[];
  failed: { id: number; message: string }[];
}

export interface ArchiveEditFile {
  id: number;
  name: string | null;
  mime_type: string;
  size_bytes: number;
  width_px: number | null;
  height_px: number | null;
  is_image: boolean;
  url: string;
}

export interface ArchiveEditLink {
  id?: number;
  role: string;
  kind: "artist" | "artwork";
  entity_id: number;
  label: Bilingual;
}

export interface ArchiveEdit {
  id: number;
  legacy_ref: string | null;
  item_type: ArchiveItemType;
  title: Bilingual;
  description: Bilingual;
  place: Bilingual;
  date_note: string | null;
  content: { display: string | null; year_from: number | null; year_to: number | null; calendar: string | null; certainty: string | null } | null;
  people_names: string[];
  keywords: string[];
  source_name: string | null;
  rights_holder: Bilingual;
  rights_status: RightsStatus;
  license: string | null;
  verification_reference: string | null;
  access_level: AccessLevel;
  publication_status: "draft" | "published" | "hidden";
  under_review: boolean;
  updated_at: string | null;
  file: ArchiveEditFile | null;
  checklist: { key: string; met: boolean }[];
  completeness_pct: number;
  links: ArchiveEditLink[];
}

export const LINK_ROLES = ["about", "depicts", "mentions", "authored_by", "donor", "subject", "primary_documentation"] as const;

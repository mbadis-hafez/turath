import type { CompletenessSeverity } from "@/types/completeness";

export type VerifiedStatus = "unverified" | "verified" | "disputed";
export type PublicationStatus = "draft" | "published" | "hidden";
export type OwnerType = "artist" | "heir_or_estate" | "gallery" | "institution" | "other";
export type AuthLetterStatus = "not_started" | "pending" | "signed" | "not_applicable";
export type PreAgreementStatus = "not_started" | "pending" | "yes" | "no" | "not_applicable";
export type BioSourceType = "citation" | "derived_from_linked_materials" | "unspecified";

export interface Localized {
  ar: string | null;
  en: string | null;
}

export interface Theme {
  id: number;
  label: Localized;
}

export interface DateValue {
  display: string | null;
  year_from: number | null;
  year_to: number | null;
  calendar: string | null;
  certainty: string | null;
}

export interface ProfileEntry {
  id?: number;
  type?: ActivityType;
  title: Localized;
  place: Localized;
  year_from: number | null;
  year_to: number | null;
  note: Localized;
}

export type ActivityType = "award" | "exhibition" | "talk" | "symposium";
export type EntryGroup = "educations" | "activities";
export type EntryGroups = Record<EntryGroup, ProfileEntry[]>;

export interface StaffOption {
  id: number;
  name: string;
  email: string;
}

export interface ArtistContact {
  id?: number;
  name: string | null;
  role_note: string | null;
  email: string | null;
  phone: string | null;
  address?: string | null;
}

export type SocialPlatform = "website" | "instagram" | "x" | "facebook" | "youtube" | "tiktok" | "linkedin" | "snapchat" | "other";

export interface SocialLink {
  id?: number;
  platform: SocialPlatform;
  url: string;
  is_public: boolean;
}

export type PortraitRights = "unknown" | "licensed" | "public_domain" | "all_rights_reserved";

export interface PortraitInfo {
  has_portrait: boolean;
  rights_status: PortraitRights;
  url: string | null;
}

export interface AdminArtistRow {
  id: number;
  slug: string;
  legacy_code: string | null;
  name: Localized;
  publication_status: PublicationStatus;
  verified_status: VerifiedStatus;
  /** null until a reviewer approves this artist's creation-review item (005). */
  creation_approved_at: string | null;
  city: Localized;
  owner_type: OwnerType | null;
  linked_material_count: number;
  gap_count: number;
  severity: CompletenessSeverity;
  themes: Theme[];
}

export interface AdminArtistsQuery {
  q?: string;
  unverified?: 1;
  city?: string;
  owner_type?: OwnerType;
  theme_id?: number;
  has_priority_materials?: 1;
  /** Excludes artists whose creation hasn't been reviewed yet — for pickers that link an artist elsewhere, not the registry itself (005). */
  linkable_only?: 1;
  page?: number;
}

export type ChecklistTier = "core" | "important" | "administrative";
export type ChecklistSection = "identity" | "biography" | "media";

export interface ChecklistItem {
  key: string;
  tier: ChecklistTier;
  met: boolean;
  supported: boolean;
  /** Profile items are grouped by section; administrative items have none. */
  section?: ChecklistSection;
}

export interface LinkedMaterial {
  id: number;
  legacy_ref: string | null;
  item_type: string;
  year: string | null;
  title: Localized;
  completeness_pct: number;
  gap_count: number;
}

export interface ArtistCuration {
  id: number;
  slug: string;
  legacy_code: string | null;
  name: Localized;
  city: Localized;
  life_dates: { birth: string | null; death: string | null };
  name_as_in_sources: string[] | null;
  identified_through: { note: string | null; date: string | null };
  bio: { ar: string | null; en: string | null; source_type: BioSourceType };
  verified_status: VerifiedStatus;
  publication_status: "draft" | "published" | "hidden";
  published_at: string | null;
  /** null until a reviewer approves this artist's creation-review item (005); gates Verify/publish. */
  creation_approved_at: string | null;
  /** The open creation-review item, while creation_approved_at is null; null once approved. */
  creation_review: {
    proposal_id: string;
    status: "draft" | "pending" | "changes_requested";
    review_note: string | null;
    created_by_user_id: number;
  } | null;
  nationality: Localized;
  classification: Localized;
  birth: DateValue | null;
  death: DateValue | null;
  living_status: "unknown" | "living" | "deceased";
  entries: EntryGroups;
  social_links: SocialLink[];
  portrait: PortraitInfo;
  contact: { owner_type: OwnerType | null; ref_supervisor_note: string | null };
  assigned_to: StaffOption | null;
  contacts: ArtistContact[];
  pipeline: {
    authorization_letter: { status: AuthLetterStatus; file_id: number | null; file_name: string | null };
    owner_pre_agreement: { status: PreAgreementStatus };
  };
  checklist: ChecklistItem[];
  /** Administrative requirements (contact, authorization letter, owner pre-agreement) — excluded from the profile percentage. */
  admin_checklist: ChecklistItem[];
  public_visibility: "visible" | "hidden";
  verify_blockers: Record<string, string[]>;
  publish_blockers: Record<string, string[]>;
  themes: Theme[];
  linked_materials: LinkedMaterial[];
}

export interface CurationUpdate {
  identified_through_note?: string | null;
  owner_type?: OwnerType | null;
  contacts?: ArtistContact[];
  authorization_letter_status?: AuthLetterStatus;
  authorization_letter_file_id?: number | null;
  owner_pre_agreement_status?: PreAgreementStatus;
  bio_source_type?: BioSourceType;
  ref_supervisor_note?: string | null;
}

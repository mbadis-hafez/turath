import type { CompletenessSeverity } from "@/types/completeness";

export type VerifiedStatus = "unverified" | "verified" | "disputed";
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
  title: Localized;
  place: Localized;
  year_from: number | null;
  year_to: number | null;
  note: Localized;
}

export type EntryGroup = "educations" | "awards" | "exhibitions";
export type EntryGroups = Record<EntryGroup, ProfileEntry[]>;

export interface ArtistContact {
  id?: number;
  name: string | null;
  role_note: string | null;
  email: string | null;
  phone: string | null;
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
  verified_status: VerifiedStatus;
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
  page?: number;
}

export interface ChecklistItem {
  key: string;
  tier: "core" | "important";
  met: boolean;
  supported: boolean;
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
  nationality: Localized;
  classification: Localized;
  birth: DateValue | null;
  death: DateValue | null;
  living_status: "unknown" | "living" | "deceased";
  entries: EntryGroups;
  social_links: SocialLink[];
  portrait: PortraitInfo;
  contact: { owner_type: OwnerType | null; ref_supervisor_note: string | null };
  contacts: ArtistContact[];
  pipeline: {
    authorization_letter: { status: AuthLetterStatus; file_id: number | null; file_name: string | null };
    owner_pre_agreement: { status: PreAgreementStatus };
  };
  checklist: ChecklistItem[];
  public_visibility: "visible" | "hidden";
  verify_blockers: Record<string, string[]>;
  themes: Theme[];
  linked_materials: LinkedMaterial[];
}

export interface CurationUpdate {
  identified_through_note?: string | null;
  owner_type?: OwnerType | null;
  contacts?: ArtistContact[];
  authorization_letter_status?: AuthLetterStatus;
  owner_pre_agreement_status?: PreAgreementStatus;
  bio_source_type?: BioSourceType;
  ref_supervisor_note?: string | null;
}

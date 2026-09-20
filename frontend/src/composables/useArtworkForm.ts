import { reactive, ref } from "vue";

import { labelOf } from "@/components/curation/ArtworkPickers";
import type { PickerOption } from "@/components/curation/EntityPicker.vue";
import type { ArtworkCuration, ConditionStatus, ImageQuality, MaterialClassification, SignedState } from "@/types/artworkCuration";

export const ARTWORK_CATEGORIES = ["painting", "drawing", "printmaking", "sculpture", "mixed_media", "paper_work", "photography", "installation", "other"];
export const CONDITION_STATES: ConditionStatus[] = ["not_available", "pending", "available"];
export const IMAGE_QUALITIES: ImageQuality[] = ["low_resolution", "high_resolution", "archive_source"];
export const MATERIAL_CLASSIFICATIONS: MaterialClassification[] = ["movable", "immovable", "digital_native", "unspecified"];
export const SIGNED_STATES: SignedState[] = ["signed", "unsigned", "unknown"];

const blank = (v: string | null | undefined): string | null => (v && v.trim() !== "" ? v.trim() : null);
const num = (v: string | number | null): number | null => (v === "" || v === null ? null : Number(v));
const text = (v: unknown): string => (v === null || v === undefined ? "" : String(v));

export interface ArtworkFormState {
  code: string; isUntitled: boolean;
  title: { ar: string; en: string }; category: string; medium: { ar: string; en: string }; year: string; signed: SignedState;
  height: string; width: string; depth: string; frameHeight: string; frameWidth: string; frameDepth: string; weight: string;
  editionNumber: string; editionSize: string; holderInventory: string; inventoryByOwner: string;
  conditionLink: string; conditionStatus: ConditionStatus | ""; imageQuality: ImageQuality | ""; editingStatus: string;
  notes: { ar: string; en: string };
  materialClassification: MaterialClassification; riskNote: string;
}

/** Editable artwork fields shared by the add and curation pages. */
export function useArtworkForm() {
  const form = reactive<ArtworkFormState>({
    code: "", isUntitled: false,
    title: { ar: "", en: "" }, category: "painting", medium: { ar: "", en: "" }, year: "", signed: "unknown",
    height: "", width: "", depth: "", frameHeight: "", frameWidth: "", frameDepth: "", weight: "",
    editionNumber: "", editionSize: "", holderInventory: "", inventoryByOwner: "",
    conditionLink: "", conditionStatus: "", imageQuality: "", editingStatus: "",
    notes: { ar: "", en: "" },
    materialClassification: "movable", riskNote: "",
  });
  const artist = ref<PickerOption | null>(null);
  const holder = ref<PickerOption | null>(null);
  let initialYear = "";

  function load(c: ArtworkCuration): void {
    form.code = c.legacy_ref ?? "";
    form.isUntitled = c.is_untitled;
    form.title = { ar: c.title.ar ?? "", en: c.title.en ?? "" };
    form.category = c.category;
    artist.value = c.artist ? { id: c.artist.id, label: labelOf(c.artist.name) } : null;
    holder.value = c.holder ? { id: c.holder.id, label: labelOf(c.holder.name) } : null;
    form.medium = { ar: c.medium.ar ?? "", en: c.medium.en ?? "" };
    form.year = initialYear = c.creation?.year_from ? String(c.creation.year_from) : "";
    form.signed = c.signed;
    form.height = text(c.dimensions.height_cm); form.width = text(c.dimensions.width_cm); form.depth = text(c.dimensions.depth_cm);
    form.frameHeight = text(c.frame_dimensions.height_cm); form.frameWidth = text(c.frame_dimensions.width_cm); form.frameDepth = text(c.frame_dimensions.depth_cm);
    form.weight = text(c.weight_kg);
    form.editionNumber = c.edition.number ?? ""; form.editionSize = text(c.edition.size);
    form.holderInventory = c.holder_inventory_no ?? ""; form.inventoryByOwner = c.inventory_by_owner ?? "";
    form.conditionLink = c.condition_report_link ?? ""; form.conditionStatus = c.condition_report_status ?? "";
    form.imageQuality = c.image_quality ?? ""; form.editingStatus = c.editing_status ?? "";
    form.notes = { ar: c.notes.ar ?? "", en: c.notes.en ?? "" };
    form.materialClassification = c.material_classification; form.riskNote = c.conservation_risk_note ?? "";
  }

  /** Request body. On existing records the year is sent only when edited so circa/range dates survive. */
  function payload(mode: "create" | "update"): Record<string, unknown> {
    const body: Record<string, unknown> = {
      title: { ar: blank(form.title.ar), en: blank(form.title.en) },
      is_untitled: form.isUntitled,
      category: form.category,
      artist_id: artist.value?.id ?? null,
      holder_id: holder.value?.id ?? null,
      medium: { ar: blank(form.medium.ar), en: blank(form.medium.en) },
      signed: form.signed,
      dimensions: { height_cm: num(form.height), width_cm: num(form.width), depth_cm: num(form.depth) },
      frame_dimensions: { height_cm: num(form.frameHeight), width_cm: num(form.frameWidth), depth_cm: num(form.frameDepth) },
      weight_kg: num(form.weight),
      edition_number: blank(form.editionNumber),
      edition_size: num(form.editionSize),
      holder_inventory_no: blank(form.holderInventory),
      inventory_by_owner: blank(form.inventoryByOwner),
      condition_report_link: blank(form.conditionLink),
      condition_report_status: form.conditionStatus || null,
      image_quality: form.imageQuality || null,
      editing_status: blank(form.editingStatus),
      notes: { ar: blank(form.notes.ar), en: blank(form.notes.en) },
      material_classification: form.materialClassification,
      conservation_risk_note: blank(form.riskNote),
    };
    if (mode === "create") {
      body.legacy_ref = blank(form.code);
      body.attribution_certainty = artist.value ? "confirmed" : "unattributed";
      body.publication_status = "draft";
    }
    if (mode === "create" || String(form.year) !== initialYear) {
      const y = num(form.year);
      body.creation = y ? { display: String(y), year_from: y, year_to: y, calendar: "gregorian", certainty: "exact" } : null;
    }
    return body;
  }

  return { form, artist, holder, load, payload };
}

import { flushPromises } from "@vue/test-utils";
import { beforeEach, describe, expect, it, vi } from "vitest";

import OcrArtistContactPanel from "@/components/curation/OcrArtistContactPanel.vue";
import { mountWithPlugins } from "@/test/utils";
import { ApiError } from "@/types/api";
import type { ContactProposalValue, OcrArtistContactState } from "@/types/ocr";

const api = vi.hoisted(() => ({
  getOcrArtistContact: vi.fn(), confirmOcrArtist: vi.fn(), proposeOcrArtistContact: vi.fn(),
  ocrRegionCropUrl: (itemId: number, regionId: number) => `/api/v1/archive-items/${itemId}/file/ocr/regions/${regionId}/crop`,
}));
vi.mock("@/api/archive", () => api);

const artist = { id: 7, slug: "ahmad-almaghlout", name: { ar: "أحمد المغلوث", en: "Ahmad Almaghlout" }, legacy_code: "AR036" };

function state(patch: Partial<OcrArtistContactState> = {}): OcrArtistContactState {
  return {
    applicable: true,
    extracted: {
      artist_name: { form_field_id: 1, field_label: "3 الفنان/ة", value: "أحمد المغلوث", method: "manually_transcribed", needs_transcription: false },
      phone: { form_field_id: 2, field_label: "دك الجوال", value: "0500000001", method: "manually_transcribed", needs_transcription: false },
      email: { form_field_id: null, field_label: null, value: null, method: null, needs_transcription: false },
      address: { form_field_id: 3, field_label: "العنوان", value: null, method: null, needs_transcription: true },
    },
    search_name: "أحمد المغلوث",
    candidates: [{ artist, strength: "high", basis: ["exact_name"] }],
    confirmed_artist: null,
    existing_contacts: null,
    proposal: null,
    contact_proposals: [],
    can_propose: true,
    can_target_existing: true,
    ...patch,
  };
}

const confirmed = { ...artist, confirmed_by_user_id: 3, confirmed_at: "2026-09-30T10:00:00Z" };

function value(patch: Partial<ContactProposalValue> = {}): ContactProposalValue {
  return {
    id: 1, field: "phone", action: "update_contact", target_contact_id: 11, proposed_value: "0500000001",
    current_value: "0500000002", current_value_shown: true, replaces_existing: true, status: "pending", superseded_reason: null, edit_proposal_id: "p-1",
    source: { file_id: 9, page: 2, region_id: 44, bbox: { x: 1, y: 2, width: 3, height: 4 }, label: "رقم الجوال", has_crop: true },
    extraction_method: "manually_transcribed", confidence: null,
    machine_suggestion: { provider: "kraken", model: "arabic-hw", model_version: "1.0", confidence: 0.62, decision: "accepted" },
    has_correction_mark: false, edited_by_proposer: false,
    proposed_by: { id: 3, name: "Nora" }, proposed_at: "2026-09-30T10:05:00Z", reviewed_by: null, reviewed_at: null, review_note: null,
    ...patch,
  };
}
const pending = { edit_proposal_id: "p-1", status: "pending" as const, review_note: null, reviewed_at: null, target_contact_id: 11, proposed_by_user_id: 3, proposed_at: "2026-09-30T10:05:00Z" };

async function mount(initial: OcrArtistContactState) {
  api.getOcrArtistContact.mockResolvedValue({ data: initial });
  const wrapper = mountWithPlugins(OcrArtistContactPanel, { locale: "en", props: { archiveItemId: 5, refreshKey: "1:" } });
  await flushPromises();
  return wrapper;
}

beforeEach(() => {
  api.getOcrArtistContact.mockReset();
  api.confirmOcrArtist.mockReset();
  api.proposeOcrArtistContact.mockReset();
});

describe("OcrArtistContactPanel", () => {
  it("renders nothing for a document that isn't an authorization letter", async () => {
    const wrapper = await mount(state({ applicable: false }));
    expect(wrapper.find("[data-testid=ocr-artist-contact-panel]").exists()).toBe(false);
  });

  it("shows candidates with a qualitative strength, and hides the contact form until an artist is confirmed", async () => {
    const wrapper = await mount(state());
    expect(wrapper.findAll("[data-testid=artist-candidate]")).toHaveLength(1);
    expect(wrapper.get("[data-testid=artist-candidate-strength]").text()).toBe("Strong match");
    expect(wrapper.find("[data-testid=artist-contact-form]").exists()).toBe(false);
  });

  it("confirms the selected candidate", async () => {
    const wrapper = await mount(state());
    api.confirmOcrArtist.mockResolvedValue({ data: state({ confirmed_artist: confirmed, existing_contacts: [] }) });

    await wrapper.get("[data-testid=artist-contact-confirm]").trigger("click");
    await flushPromises();

    expect(api.confirmOcrArtist).toHaveBeenCalledWith(5, 7);
    expect(wrapper.find("[data-testid=artist-contact-form]").exists()).toBe(true);
  });

  it("searches candidates under another spelling", async () => {
    const wrapper = await mount(state());
    await wrapper.get("[data-testid=artist-contact-search]").setValue("Almaghlout");
    await wrapper.get("form").trigger("submit");
    expect(api.getOcrArtistContact).toHaveBeenLastCalledWith(5, "Almaghlout", expect.any(AbortSignal));
  });

  it("pre-fills from transcriptions and says where each value came from", async () => {
    const wrapper = await mount(state({ confirmed_artist: confirmed, existing_contacts: [] }));
    expect((wrapper.get("[data-testid=artist-contact-phone]").element as HTMLInputElement).value).toBe("0500000001");
    expect(wrapper.get("[data-testid=artist-contact-phone-source]").text()).toContain("transcription");
    expect(wrapper.get("[data-testid=artist-contact-email-source]").text()).toContain("Not detected");
    expect(wrapper.get("[data-testid=artist-contact-address-source]").text()).toContain("hasn't been transcribed");
  });

  it("proposes the confirmed values and can target an existing contact", async () => {
    const wrapper = await mount(state({
      confirmed_artist: confirmed,
      existing_contacts: [{ id: 11, name: "Gallery desk", role_note: null, email: "desk@gallery.test", phone: null, address: null }],
    }));
    api.proposeOcrArtistContact.mockResolvedValue({ data: state({ confirmed_artist: confirmed, proposal: {
      edit_proposal_id: "p-1", status: "pending", review_note: null, reviewed_at: null, target_contact_id: 11, proposed_by_user_id: 3, proposed_at: "2026-09-30T10:05:00Z",
    } }) });

    await wrapper.get("[data-testid=artist-contact-email]").setValue("ahmad@example.test");
    await wrapper.get("[data-testid=artist-contact-target]").setValue("11");
    await wrapper.get("[data-testid=artist-contact-propose]").trigger("click");
    await flushPromises();

    expect(api.proposeOcrArtistContact).toHaveBeenCalledWith(5, {
      name: "أحمد المغلوث", role_note: null, email: "ahmad@example.test", phone: "0500000001", address: null, target_contact_id: 11, note: null,
    });
    // Awaiting review: the form is gone and the artist is locked.
    expect(wrapper.find("[data-testid=artist-contact-form]").exists()).toBe(false);
    expect(wrapper.get("[data-testid=artist-contact-proposal-status]").text()).toBe("Pending");
    expect(wrapper.find("[data-testid=artist-contact-confirm]").exists()).toBe(false);
  });

  it("shows validation errors next to the field they belong to", async () => {
    const wrapper = await mount(state({ confirmed_artist: confirmed, existing_contacts: [] }));
    api.proposeOcrArtistContact.mockRejectedValue(ApiError.fromHttp(422, { message: "Invalid", errors: { email: ["The email must be a valid email address."] } }));

    await wrapper.get("[data-testid=artist-contact-email]").setValue("not an email");
    await wrapper.get("[data-testid=artist-contact-propose]").trigger("click");
    await flushPromises();

    expect(wrapper.text()).toContain("The email must be a valid email address.");
  });

  it("disables proposing until at least one contact value is present", async () => {
    const empty = state({ confirmed_artist: confirmed, existing_contacts: [] });
    empty.extracted.phone = { ...empty.extracted.phone, value: null };
    const wrapper = await mount(empty);
    expect(wrapper.get("[data-testid=artist-contact-propose]").attributes("disabled")).toBeDefined();
  });

  it("explains when the viewer can't submit proposals", async () => {
    const wrapper = await mount(state({ confirmed_artist: confirmed, can_propose: false }));
    expect(wrapper.find("[data-testid=artist-contact-propose]").exists()).toBe(false);
    expect(wrapper.find("[data-testid=artist-contact-cannot-propose]").exists()).toBe(true);
  });

  it("shows a reviewer's requested changes and lets the proposer send again", async () => {
    const wrapper = await mount(state({ confirmed_artist: confirmed, existing_contacts: [], proposal: {
      edit_proposal_id: "p-1", status: "changes_requested", review_note: "Add the email too.", reviewed_at: null, target_contact_id: null, proposed_by_user_id: 3, proposed_at: null,
    } }));
    expect(wrapper.get("[data-testid=artist-contact-review-note]").text()).toContain("Add the email too.");
    expect(wrapper.find("[data-testid=artist-contact-propose]").exists()).toBe(true);
    // The artist stays locked while the draft is open.
    expect(wrapper.find("[data-testid=artist-contact-confirm]").exists()).toBe(false);
  });

  it("shows each proposed value against the one it would replace, with where it came from", async () => {
    const wrapper = await mount(state({ confirmed_artist: confirmed, proposal: pending, contact_proposals: [value()] }));
    const row = wrapper.get("[data-testid=contact-proposal-value]");

    expect(row.get("[data-testid=contact-proposal-status]").text()).toBe("Awaiting approval");
    expect(row.get("[data-testid=contact-proposal-proposed]").text()).toBe("0500000001");
    expect(row.get("[data-testid=contact-proposal-current]").text()).toBe("Currently: 0500000002");
    expect(row.find("[data-testid=contact-proposal-replaces]").exists()).toBe(true);
    expect(row.get("[data-testid=contact-proposal-provenance]").text()).toBe("Page 2 · “رقم الجوال” · transcribed by a reviewer · suggested by arabic-hw (62%), accepted by a reviewer");
    expect(row.get("[data-testid=contact-proposal-crop]").attributes("src")).toContain("/archive-items/5/file/ocr/regions/44/crop");
  });

  it("says why the current value isn't shown, and flags a possible correction on the document", async () => {
    const wrapper = await mount(state({ confirmed_artist: confirmed, proposal: pending, contact_proposals: [
      value({ current_value: null, current_value_shown: false, has_correction_mark: true, source: { ...value().source, has_crop: false } }),
    ] }));

    expect(wrapper.get("[data-testid=contact-proposal-current]").text()).toContain("only to people who can see artist contacts");
    expect(wrapper.find("[data-testid=contact-proposal-correction-mark]").exists()).toBe(true);
    expect(wrapper.find("[data-testid=contact-proposal-crop]").exists()).toBe(false);
  });

  it("keeps decided and replaced values as history, with the reviewer and the reason", async () => {
    const wrapper = await mount(state({ confirmed_artist: confirmed, proposal: { ...pending, status: "rejected", review_note: "Digits unclear." }, contact_proposals: [
      value({ id: 2, status: "rejected", current_value: null, current_value_shown: false, reviewed_by: { id: 8, name: "Huda" }, reviewed_at: "2026-09-30T12:00:00Z", review_note: "Digits unclear." }),
      value({ id: 1, status: "superseded", superseded_reason: "newer_proposal", edited_by_proposer: true, machine_suggestion: null }),
    ] }));
    const [rejected, superseded] = wrapper.findAll("[data-testid=contact-proposal-value]");

    expect(rejected.get("[data-testid=contact-proposal-reviewed]").text()).toContain("Huda");
    expect(rejected.get("[data-testid=contact-proposal-current]").text()).toContain("isn't kept");
    expect(rejected.find("[data-testid=contact-proposal-replaces]").exists()).toBe(false);
    expect(superseded.get("[data-testid=contact-proposal-superseded]").text()).toContain("later proposal");
    expect(superseded.get("[data-testid=contact-proposal-provenance]").text()).toContain("typed in by the proposer");
  });
});

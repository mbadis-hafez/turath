import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import ArtistCurationPage from "@/pages/admin/ArtistCurationPage.vue";
import { useAuthStore } from "@/stores/auth";
import { mountWithPlugins } from "@/test/utils";
import { ApiError } from "@/types/api";
import type { ArtistCuration } from "@/types/artistCuration";

const api = vi.hoisted(() => ({
  getArtistCuration: vi.fn(), updateArtistCuration: vi.fn(), verifyArtist: vi.fn(), publishArtist: vi.fn(),
  searchStaffOptions: vi.fn(), updateArtistAssignment: vi.fn(),
}));
vi.mock("@/api/artistCuration", () => api);

const dashboard = vi.hoisted(() => ({ getRecordCompleteness: vi.fn() }));
vi.mock("@/api/dashboard", () => dashboard);

const editorial = vi.hoisted(() => ({ getDraft: vi.fn(), saveDraft: vi.fn(), submitDraft: vi.fn(), submitCreationReview: vi.fn() }));
vi.mock("@/api/editorial", () => editorial);

const SECTION_OF: Record<string, "identity" | "biography" | "media"> = {
  artist_code: "identity", name_ar: "identity", name_en: "identity", birth_city: "identity",
  living_status_known: "identity", birth_year: "identity", death_year_or_living: "identity",
  nationality: "identity", bio_ar: "biography", bio_en: "biography", portrait_with_clear_rights: "media",
};

/** The 11 profile items the backend now returns, with `met` overridable per key. */
function checklist(met: Record<string, boolean> = {}, supported: Record<string, boolean> = {}): ArtistCuration["checklist"] {
  return Object.entries(SECTION_OF).map(([key, section]) => ({
    key, tier: "core" as const, section,
    met: met[key] ?? false,
    supported: supported[key] ?? true,
  }));
}

function adminChecklist(met: Record<string, boolean> = {}): ArtistCuration["admin_checklist"] {
  return ["contact", "authorization_letter", "owner_pre_agreement"].map((key) => ({
    key, tier: "administrative" as const, met: met[key] ?? false, supported: true,
  }));
}

function bundle(patch: Partial<ArtistCuration> = {}): ArtistCuration {
  return {
    id: 36, slug: "ahmad", legacy_code: "AR036", name: { ar: "أحمد المغلوث", en: "Ahmad Almaghlout" },
    city: { ar: "الأحساء", en: "Alahsa" }, life_dates: { birth: null, death: null },
    name_as_in_sources: null, identified_through: { note: "Site visit", date: null },
    bio: { ar: null, en: "Saudi artist.", source_type: "derived_from_linked_materials" },
    verified_status: "unverified",
    publication_status: "draft",
    published_at: null,
    // Reviewed by default, matching every other pre-existing fixture in this
    // file — tests that specifically exercise the creation-review flow (005)
    // override both fields explicitly.
    creation_approved_at: "2026-09-20T10:00:00Z",
    creation_review: null,
    nationality: { ar: null, en: null }, classification: { ar: null, en: null }, birth: null, death: null, living_status: "unknown",
    entries: { educations: [], activities: [] }, social_links: [], portrait: { has_portrait: false, rights_status: "unknown", url: null },
    contact: { owner_type: "artist", ref_supervisor_note: null },
    assigned_to: null,
    contacts: [{ id: 1, name: "Ahmad", role_note: null, email: "a@b.co", phone: null }],
    pipeline: { authorization_letter: { status: "not_started", file_id: null, file_name: null }, owner_pre_agreement: { status: "not_started" } },
    checklist: checklist({ name_ar: true }, { portrait_with_clear_rights: false }),
    admin_checklist: adminChecklist(),
    public_visibility: "hidden",
    verify_blockers: { "data.primary_source": ["Missing required field: Primary source."], "pipeline.authorization_letter": ["Authorization letter is not_started."] },
    publish_blockers: { "data.bio_en": ["Missing required field: English biography."] },
    themes: [], linked_materials: [{ id: 1, legacy_ref: "ARC1", item_type: "image", year: "1978", title: { ar: null, en: "Photo" }, completeness_pct: 30, gap_count: 6 }],
    ...patch,
  };
}

let router: Router;

async function mountPage(permissions: string[] = ["artists.manage"]) {
  const pinia = createPinia();
  setActivePinia(pinia);
  useAuthStore().$patch({ user: { id: 1, name: "E", email: "e@x", roles: ["editor"], permissions } as never, initialized: true });
  await router.push("/en/admin/artists/36");
  const wrapper = mountWithPlugins(ArtistCurationPage, { locale: "en", router, pinia });
  await flushPromises();
  return wrapper;
}

beforeEach(() => {
  api.getArtistCuration.mockReset().mockResolvedValue({ data: bundle() });
  api.searchStaffOptions.mockReset().mockResolvedValue({ data: [{ id: 5, name: "Samar", email: "samar@hafezgallery.com" }] });
  api.updateArtistAssignment.mockReset().mockResolvedValue({ data: { id: 5, name: "Samar", email: "samar@hafezgallery.com" } });
  dashboard.getRecordCompleteness.mockReset().mockResolvedValue({ data: { citations: [{ id: "c1", field_key: "name" }] } });
  editorial.getDraft.mockReset().mockResolvedValue({ data: null });
  editorial.saveDraft.mockReset().mockResolvedValue({ data: { status: "draft" } });
  editorial.submitDraft.mockReset().mockResolvedValue({ data: { status: "pending" } });
  editorial.submitCreationReview.mockReset().mockResolvedValue({ data: { status: "pending" } });
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale/admin/artists", name: "admin.artists", component: { template: "<div />" } },
      { path: "/:locale/admin/artists/:id", name: "admin.artists.show", component: ArtistCurationPage },
    ],
  });
});

describe("ArtistCurationPage", () => {
  it("disables Verify and names the first blocking reason from the API", async () => {
    const wrapper = await mountPage();
    const button = wrapper.get("[data-testid=verify-button]");

    expect(button.attributes("disabled")).toBeDefined();
    expect(button.attributes("title")).toBe("Cannot verify yet: Missing required field: Primary source.");
    expect(wrapper.get("[data-testid=visibility]").text()).toBe("Hidden");
  });

  it("enables Verify when nothing blocks and calls the verify endpoint", async () => {
    api.getArtistCuration.mockResolvedValue({ data: bundle({ verify_blockers: {}, public_visibility: "visible" }) });
    api.verifyArtist.mockResolvedValue({});
    const wrapper = await mountPage();

    await wrapper.get("[data-testid=verify-button]").trigger("click");
    await flushPromises();

    expect(api.verifyArtist).toHaveBeenCalledWith(36);
  });

  it("disables Publish and names the first blocking reason from the API", async () => {
    const wrapper = await mountPage();
    const button = wrapper.get("[data-testid=publish-button]");

    expect(button.attributes("disabled")).toBeDefined();
    expect(button.attributes("title")).toBe("Cannot publish yet: Missing required field: English biography.");
    expect(button.text()).toBe("Publish artist");
  });

  it("enables Publish when nothing blocks and calls the publish endpoint", async () => {
    api.getArtistCuration.mockResolvedValue({ data: bundle({ publish_blockers: {} }) });
    api.publishArtist.mockResolvedValue({});
    const wrapper = await mountPage();

    const button = wrapper.get("[data-testid=publish-button]");
    expect(button.attributes("disabled")).toBeUndefined();
    await button.trigger("click");
    await flushPromises();

    expect(api.publishArtist).toHaveBeenCalledWith(36);
  });

  it("shows a disabled Published state once the artist is already published", async () => {
    api.getArtistCuration.mockResolvedValue({
      data: bundle({ publication_status: "published", published_at: "2026-09-24T10:00:00Z", publish_blockers: {} }),
    });
    const wrapper = await mountPage();
    const button = wrapper.get("[data-testid=publish-button]");

    expect(button.text()).toBe("Published");
    expect(button.attributes("disabled")).toBeDefined();
  });

  it("hides Publish from a reviewer without artists.manage", async () => {
    const wrapper = await mountPage(["review_queue.editorial_review"]);

    expect(wrapper.find("[data-testid=publish-button]").exists()).toBe(false);
  });

  it("shows the creation-review banner immediately for a just-created artist, with Verify disabled for that reason specifically", async () => {
    api.getArtistCuration.mockResolvedValue({
      data: bundle({
        creation_approved_at: null,
        creation_review: { proposal_id: "p1", status: "draft", review_note: null, created_by_user_id: 1 },
        verify_blockers: {},
      }),
    });
    const wrapper = await mountPage();

    const banner = wrapper.get("[data-testid=draft-banner]");
    expect(banner.text()).toContain("New record");
    const submit = banner.get("[data-testid=send-for-review]");
    expect(submit.attributes("disabled")).toBeUndefined();

    const verify = wrapper.get("[data-testid=verify-button]");
    expect(verify.attributes("disabled")).toBeDefined();
    expect(verify.attributes("title")).toBe("Awaiting creation review — this can't be verified yet.");
  });

  it("sends a brand-new artist's creation for review with zero prior edits — the reported bug", async () => {
    api.getArtistCuration.mockResolvedValue({
      data: bundle({
        creation_approved_at: null,
        creation_review: { proposal_id: "p1", status: "draft", review_note: null, created_by_user_id: 1 },
      }),
    });
    const wrapper = await mountPage();

    await wrapper.get("[data-testid=send-for-review]").trigger("click");
    await flushPromises();

    expect(editorial.submitCreationReview).toHaveBeenCalledWith("artists", 36);
    expect(wrapper.get("[data-testid=draft-notice]").text()).toBe("Your draft was sent for review.");
  });

  it("blocks a non-creator from editing an unapproved artist, showing a pending-review state instead", async () => {
    api.getArtistCuration.mockResolvedValue({
      data: bundle({
        creation_approved_at: null,
        creation_review: { proposal_id: "p1", status: "draft", review_note: null, created_by_user_id: 2 },
      }),
    });
    const wrapper = await mountPage();

    expect(wrapper.get("[data-testid=draft-banner]").attributes("data-state")).toBe("blocked");
    expect(wrapper.get("[data-testid=save-button]").attributes("disabled")).toBeDefined();
  });

  it("renders the checklist, the provisional-bio badge and the linked material's gap count", async () => {
    const wrapper = await mountPage();

    const boxes = wrapper.findAll("[data-testid=checklist] input");
    expect(boxes.map((b) => (b.element as HTMLInputElement).checked)).toEqual([false, true, ...Array<boolean>(9).fill(false)]);
    expect(wrapper.text()).toContain("not tracked yet");
    expect(wrapper.get("[data-testid=required-met]").text()).toBe("1 of 11 required fields met");
    expect(wrapper.find("[data-testid=provisional-bio]").exists()).toBe(true);
    expect(wrapper.get("[data-testid=materials]").text()).toContain("6 gaps");
  });

  it("renders administrative requirements from admin_checklist, outside the profile count", async () => {
    api.getArtistCuration.mockResolvedValue({
      data: bundle({ admin_checklist: adminChecklist({ contact: true, authorization_letter: true }) }),
    });
    const wrapper = await mountPage();

    const boxes = wrapper.findAll("[data-testid=admin-checklist] input");
    expect(boxes.map((b) => (b.element as HTMLInputElement).checked)).toEqual([true, true, false]);
    expect(wrapper.get("[data-testid=required-met]").text()).toBe("1 of 11 required fields met");
    expect(wrapper.get("[data-testid=contact-section]").text()).toContain("Complete");
  });

  it("highlights missing identity fields in red and counts them, like the mockup", async () => {
    dashboard.getRecordCompleteness.mockResolvedValue({ data: { citations: [] } });
    api.getArtistCuration.mockResolvedValue({ data: bundle({ checklist: checklist({ name_ar: true, name_en: true, birth_year: true }) }) });
    const wrapper = await mountPage();

    expect(wrapper.get("[data-testid=name-verified-field]").classes()).toContain("bg-danger-soft");
    expect(wrapper.get("[data-testid=life-dates-field]").text()).toBe("Not recorded");
    expect(wrapper.get("[data-testid=identity-missing]").text()).toBe("5 fields missing");
    expect(wrapper.get("[data-testid=work-path]").text()).toContain("Name verified");
    expect(wrapper.text()).toContain("3 of 11 required fields met");
    expect(wrapper.text()).toContain("Ahmad Almaghlout");
  });

  it("shows the internal contact section only to holders of artists.manage", async () => {
    const wrapper = await mountPage();
    expect(wrapper.find("[data-testid=contact-section]").exists()).toBe(true);

    useAuthStore().$patch({ user: { id: 2, name: "R", email: "r@x", roles: ["reader"], permissions: [] } as never });
    await flushPromises();
    expect(wrapper.find("[data-testid=contact-section]").exists()).toBe(false);
  });

  describe("assigned staff", () => {
    it("shows the record's current assignee and saves a change immediately, bypassing draft mode", async () => {
      vi.useFakeTimers({ shouldAdvanceTime: true });
      api.getArtistCuration.mockResolvedValue({
        data: bundle({ assigned_to: { id: 2, name: "Fidha", email: "fidha.fatma@hafezgallery.com" } }),
      });
      const wrapper = await mountPage(["artists.manage", "proposals.submit"]);
      const picker = wrapper.get("[data-testid=assigned-staff]");
      expect(picker.get("[data-testid=picked]").text()).toContain("Fidha");

      await picker.get("[data-testid=picked] button").trigger("click");
      await picker.get("[data-testid=picker-input]").setValue("Samar");
      await vi.advanceTimersByTimeAsync(300);
      await flushPromises();
      await picker.get("[data-testid=picker-options] button").trigger("click");
      await flushPromises();

      expect(api.updateArtistAssignment).toHaveBeenCalledWith(36, 5);
      expect(editorial.saveDraft).not.toHaveBeenCalled();
      vi.useRealTimers();
    });

    it("shows no assignee when the record has none", async () => {
      const wrapper = await mountPage();
      expect(wrapper.get("[data-testid=assigned-staff]").find("[data-testid=picked]").exists()).toBe(false);
    });
  });

  describe("draft mode (proposals.submit)", () => {
    it("saves every section through saveDraft instead of the direct endpoints", async () => {
      const wrapper = await mountPage(["artists.manage", "proposals.submit"]);

      await wrapper.get("[data-testid=save-button]").trigger("click");
      await flushPromises();

      expect(editorial.saveDraft).toHaveBeenCalledTimes(5);
      const payloads = editorial.saveDraft.mock.calls.map((call) => call[2] as Record<string, unknown>);
      for (const section of ["fields", "educations", "activities", "social_links", "curation"]) {
        expect(payloads.some((p) => section in p)).toBe(true);
      }
      const last = payloads[payloads.length - 1];
      expect(last.fields).toEqual({ nationality: { ar: null, en: null }, classification: { ar: null, en: null } });
      expect(last.educations).toEqual([]);
      expect(last.activities).toEqual([]);
      expect(last.social_links).toEqual([]);
      expect(last.curation).toMatchObject({
        owner_type: "artist",
        authorization_letter_status: "not_started",
        owner_pre_agreement_status: "not_started",
        bio_source_type: "derived_from_linked_materials",
        contacts: [{ id: 1, name: "Ahmad", role_note: null, email: "a@b.co", phone: null, address: null }],
      });
      expect(api.updateArtistCuration).not.toHaveBeenCalled();
      expect(wrapper.get("[data-testid=saved-feedback]").text()).toBe("Saved to draft");
    });

    it("initializes the form from draft sections instead of live data", async () => {
      editorial.getDraft.mockResolvedValue({
        data: {
          status: "draft",
          payload: {
            fields: { nationality: { ar: "سعودي", en: "Saudi" } },
            curation: { owner_type: "gallery", bio_source_type: "citation" },
            educations: [{ title: { ar: "جامعة الملك سعود", en: "KSU" }, place: { ar: null, en: "Riyadh" }, year_from: 1990, year_to: null, note: { ar: null, en: null } }],
          },
        },
      });
      const wrapper = await mountPage(["artists.manage", "proposals.submit"]);

      expect(wrapper.get("[data-testid=draft-banner]").attributes("data-state")).toBe("draft");
      const ownerSelect = wrapper.get("[data-testid=contact-section] select").element as HTMLSelectElement;
      expect(ownerSelect.value).toBe("gallery");
      const inputs = wrapper.findAll("input[type=text]");
      const nationalityEn = inputs.find((i) => (i.element as HTMLInputElement).value === "Saudi");
      expect(nationalityEn).toBeDefined();
    });

    it("shows the pending state and disables saving while a review is open", async () => {
      editorial.getDraft.mockResolvedValue({ data: { status: "pending", payload: {} } });
      const wrapper = await mountPage(["artists.manage", "proposals.submit"]);

      expect(wrapper.get("[data-testid=draft-banner]").attributes("data-state")).toBe("pending");
      expect(wrapper.get("[data-testid=save-button]").attributes("disabled")).toBeDefined();
    });

    it("shows the reviewer note for changes_requested drafts", async () => {
      editorial.getDraft.mockResolvedValue({ data: { status: "changes_requested", review_note: "Fix the birth year", payload: {} } });
      const wrapper = await mountPage(["artists.manage", "proposals.submit"]);

      expect(wrapper.get("[data-testid=draft-banner]").attributes("data-state")).toBe("changes_requested");
      expect(wrapper.get("[data-testid=reviewer-note]").text()).toContain('Reviewer note: "Fix the birth year"');
    });

    it("sends the draft for review and flips the banner to awaiting review", async () => {
      editorial.getDraft.mockResolvedValue({ data: { status: "draft", payload: { fields: {} } } });
      const wrapper = await mountPage(["artists.manage", "proposals.submit"]);
      expect(wrapper.get("[data-testid=send-for-review]").attributes("disabled")).toBeDefined();

      await wrapper.get("[data-testid=save-button]").trigger("click");
      await flushPromises();
      expect(wrapper.get("[data-testid=send-for-review]").attributes("disabled")).toBeUndefined();

      await wrapper.get("[data-testid=send-for-review]").trigger("click");
      await flushPromises();

      expect(editorial.submitDraft).toHaveBeenCalledWith("artists", 36);
      expect(wrapper.get("[data-testid=draft-banner]").attributes("data-state")).toBe("pending");
      expect(wrapper.get("[data-testid=draft-notice]").text()).toBe("Your draft was sent for review.");
    });

    it("shows the backend's actual refusal reason when a submit is rejected, not a generic message", async () => {
      // Regression: sendForReview() used to swallow the real error entirely
      // and always show a generic "please try again" message, which hid
      // actionable reasons like "nothing in this draft differs from the
      // current record" (the actual 422 the backend returns in that case).
      editorial.getDraft.mockResolvedValue({ data: { status: "draft", payload: { fields: {} } } });
      const wrapper = await mountPage(["artists.manage", "proposals.submit"]);
      await wrapper.get("[data-testid=save-button]").trigger("click");
      await flushPromises();

      editorial.submitDraft.mockRejectedValueOnce(
        new ApiError("validation", "Nothing in this draft differs from the current record.", { status: 422 }),
      );
      await wrapper.get("[data-testid=send-for-review]").trigger("click");
      await flushPromises();

      expect(wrapper.text()).toContain("Nothing in this draft differs from the current record.");
      expect(wrapper.text()).not.toContain("Could not send the draft for review");
    });

    it("blocks editing with a non-actionable banner when another user's review is open", async () => {
      editorial.getDraft.mockRejectedValue(new ApiError("server", "conflict", { status: 409, body: { message: "conflict", proposal_id: "p9" } }));
      const wrapper = await mountPage(["artists.manage", "proposals.submit"]);

      expect(wrapper.get("[data-testid=draft-banner]").attributes("data-state")).toBe("blocked");
      expect(wrapper.get("[data-testid=save-button]").attributes("disabled")).toBeDefined();
      expect(editorial.saveDraft).not.toHaveBeenCalled();
    });

    it("discarding the draft resets the form to live values", async () => {
      editorial.getDraft.mockResolvedValue({
        data: { status: "draft", payload: { curation: { owner_type: "gallery" } } },
      });
      const wrapper = await mountPage(["artists.manage", "proposals.submit"]);
      expect((wrapper.get("[data-testid=contact-section] select").element as HTMLSelectElement).value).toBe("gallery");

      await wrapper.get("[data-testid=discard-draft]").trigger("click");
      document.querySelector("[data-testid=confirm-dialog-confirm]")!.dispatchEvent(new Event("click", { bubbles: true }));
      await flushPromises();

      expect(editorial.saveDraft).not.toHaveBeenCalled();
      expect(wrapper.find("[data-testid=draft-banner]").exists()).toBe(false);
      expect((wrapper.get("[data-testid=contact-section] select").element as HTMLSelectElement).value).toBe("artist");
    });
  });
});

import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";

import ProposalDiffViewer from "@/components/proposals/ProposalDiffViewer.vue";
import { mountWithPlugins } from "@/test/utils";
import { ApiError } from "@/types/api";
import type { Proposal } from "@/types/proposal";
import type { SectionDiff } from "@/types/proposalDiff";

const api = vi.hoisted(() => ({ approveProposal: vi.fn(), rejectProposal: vi.fn() }));
vi.mock("@/api/proposals", () => api);

const editorial = vi.hoisted(() => ({ getProposalDiff: vi.fn(), requestChanges: vi.fn() }));
vi.mock("@/api/editorial", () => editorial);

function proposal(patch: Partial<Proposal> = {}): Proposal {
  return {
    id: "p1", record: { type: "artists", id: 7, label: "Ahmad" }, status: "pending", review_type: "second_source_needed",
    field_diffs: { bio_en: { old_value_at_proposal_time: "Old bio.", proposed_value: "New bio." } },
    field_labels: { bio_en: { ar: "السيرة", en: "Biography (English)" } },
    rationale: "Page 12 of the catalogue.", proposed_citations: [],
    proposed_by: { id: 3, name: "Nora" }, reviewed_by: null, reviewed_at: null, review_note: null,
    resulting_revision_id: null, created_at: "2026-09-01T10:00:00Z", ...patch,
  };
}

const sections: SectionDiff[] = [
  {
    key: "fields",
    fields: [{ field: "name_en", label: { ar: null, en: "Name (English)" }, old: "Ahmad", new: "Ahmed" }],
    collections: [],
  },
  {
    key: "educations",
    fields: [],
    collections: [
      {
        key: "educations",
        added: [
          {
            label: "BFA — Royal College",
            fields: [{ field: "institution", label: { ar: null, en: "Institution" }, old: null, new: "Royal College" }],
          },
        ],
        removed: [],
        changed: [],
      },
    ],
  },
];

beforeEach(() => {
  setActivePinia(createPinia());
  api.approveProposal.mockReset().mockResolvedValue({ data: proposal({ status: "approved" }) });
  api.rejectProposal.mockReset().mockResolvedValue({ data: proposal({ status: "rejected" }) });
  editorial.getProposalDiff.mockReset().mockResolvedValue({ data: { sections } });
  editorial.requestChanges.mockReset().mockResolvedValue({ data: proposal({ status: "changes_requested" }) });
});

const mount = (props: Record<string, unknown>) => mountWithPlugins(ProposalDiffViewer, { locale: "en", props });

describe("ProposalDiffViewer", () => {
  it("shows the labelled diff and the rationale, and hides review actions from non-reviewers", () => {
    const wrapper = mount({ proposal: proposal(), canReview: false, currentUserId: null });

    expect(wrapper.get("[data-testid=field-diff]").text()).toContain("Biography (English)");
    expect(wrapper.text()).toContain("Old bio.");
    expect(wrapper.text()).toContain("New bio.");
    expect(wrapper.get("[data-testid=rationale]").text()).toBe("Page 12 of the catalogue.");
    expect(wrapper.find("[data-testid=approve]").exists()).toBe(false);
  });

  it("approves with the review note", async () => {
    const wrapper = mount({ proposal: proposal(), canReview: true, currentUserId: 1 });

    await wrapper.get("[data-testid=review-note-input]").setValue("Checked against the source.");
    await wrapper.get("[data-testid=approve]").trigger("click");
    await flushPromises();

    expect(api.approveProposal).toHaveBeenCalledWith("p1", { review_note: "Checked against the source.", confirm_conflict: false });
    expect(wrapper.emitted("reviewed")).toHaveLength(1);
  });

  it("surfaces the merge conflict on a 409 and only then offers to approve against the current value", async () => {
    api.approveProposal.mockRejectedValueOnce(
      new ApiError("server", "changed", { status: 409, body: { conflicts: [{ field: "bio_en", proposed_against: "Old bio.", current: "Someone else's bio.", proposed_value: "New bio." }] } }),
    );
    const wrapper = mount({ proposal: proposal(), canReview: true, currentUserId: 1 });

    await wrapper.get("[data-testid=approve]").trigger("click");
    await flushPromises();

    expect(wrapper.emitted("reviewed")).toBeUndefined();
    const warning = wrapper.get("[data-testid=conflict-warning]");
    expect(warning.text()).toContain("Someone else's bio.");
    expect(wrapper.findAll("[data-testid=field-conflict]")).toHaveLength(1);
    // The "before" column now shows the live value, not the stale one the contributor saw.
    expect(wrapper.get("[data-testid=field-diff]").text()).toContain("Someone else's bio.");
    expect(wrapper.findAll("[data-testid=approve]")).toHaveLength(0);

    await wrapper.get("[data-testid=approve-confirm]").trigger("click");
    await flushPromises();
    expect(api.approveProposal).toHaveBeenLastCalledWith("p1", { review_note: undefined, confirm_conflict: true });
  });

  it("requires a note before rejecting", async () => {
    const wrapper = mount({ proposal: proposal(), canReview: true, currentUserId: 1 });

    await wrapper.get("[data-testid=reject]").trigger("click");
    await flushPromises();
    expect(api.rejectProposal).not.toHaveBeenCalled();

    await wrapper.get("[data-testid=review-note-input]").setValue("No source given.");
    await wrapper.get("[data-testid=reject]").trigger("click");
    await flushPromises();
    expect(api.rejectProposal).toHaveBeenCalledWith("p1", "No source given.");
  });

  it("shows the reviewer's note instead of actions once reviewed", () => {
    const wrapper = mount({ proposal: proposal({ status: "rejected", review_note: "Not supported.", reviewed_by: { id: 1, name: "Munir" } }), canReview: true, currentUserId: 1 });

    expect(wrapper.get("[data-testid=review-note]").text()).toContain("Not supported.");
    expect(wrapper.find("[data-testid=approve]").exists()).toBe(false);
  });

  it("fetches and renders the per-section diff for a sectioned proposal", async () => {
    const wrapper = mount({ proposal: proposal({ payload: { fields: { name_en: "Ahmed" } } }), canReview: true, currentUserId: 1 });

    await flushPromises();

    expect(editorial.getProposalDiff).toHaveBeenCalledWith("p1", expect.any(AbortSignal));
    expect(wrapper.find("[data-testid=diff-loading]").exists()).toBe(false);
    const sectionHeads = wrapper.findAll("[data-testid=section-diff]");
    expect(sectionHeads).toHaveLength(2);
    expect(wrapper.text()).toContain("Fields");
    expect(wrapper.text()).toContain("Education");
    expect(wrapper.get("[data-testid=collection-added]").text()).toContain("Added");
    expect(wrapper.get("[data-testid=collection-added]").text()).toContain("Royal College");
    expect(wrapper.find("[data-testid=section-diff] [data-testid=field-diff]").exists()).toBe(true);
  });

  it("requires a note before requesting changes, then posts it and emits reviewed", async () => {
    const wrapper = mount({ proposal: proposal(), canReview: true, currentUserId: 1 });

    await wrapper.get("[data-testid=review-note-input]").setValue("no");
    await wrapper.get("[data-testid=request-changes]").trigger("click");
    await flushPromises();
    expect(editorial.requestChanges).not.toHaveBeenCalled();
    expect(wrapper.text()).toContain("A note is required when requesting changes.");
    expect(wrapper.emitted("reviewed")).toBeUndefined();

    await wrapper.get("[data-testid=review-note-input]").setValue("Please cite the source for the new date.");
    await wrapper.get("[data-testid=request-changes]").trigger("click");
    await flushPromises();
    expect(editorial.requestChanges).toHaveBeenCalledWith("p1", "Please cite the source for the new date.");
    expect(wrapper.emitted("reviewed")).toHaveLength(1);
  });

  it("hides review actions on the reviewer's own pending proposal", () => {
    const wrapper = mount({ proposal: proposal(), canReview: true, currentUserId: 3 });

    expect(wrapper.find("[data-testid=approve]").exists()).toBe(false);
    expect(wrapper.find("[data-testid=reject]").exists()).toBe(false);
    expect(wrapper.find("[data-testid=request-changes]").exists()).toBe(false);
    expect(wrapper.get("[data-testid=own-proposal]").text()).toContain("This is your own proposal");
  });

  it("renders the flat field rows for a legacy proposal without a payload", () => {
    editorial.getProposalDiff.mockClear();
    const wrapper = mount({ proposal: proposal(), canReview: false, currentUserId: null });

    expect(editorial.getProposalDiff).not.toHaveBeenCalled();
    expect(wrapper.findAll("[data-testid=field-diff]")).toHaveLength(1);
    expect(wrapper.find("[data-testid=section-diff]").exists()).toBe(false);
    expect(wrapper.text()).toContain("Old bio.");
  });
});

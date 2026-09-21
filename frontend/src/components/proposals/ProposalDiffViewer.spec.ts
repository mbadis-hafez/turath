import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";

import ProposalDiffViewer from "@/components/proposals/ProposalDiffViewer.vue";
import { mountWithPlugins } from "@/test/utils";
import { ApiError } from "@/types/api";
import type { Proposal } from "@/types/proposal";

const api = vi.hoisted(() => ({ approveProposal: vi.fn(), rejectProposal: vi.fn() }));
vi.mock("@/api/proposals", () => api);

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

beforeEach(() => {
  setActivePinia(createPinia());
  api.approveProposal.mockReset().mockResolvedValue({ data: proposal({ status: "approved" }) });
  api.rejectProposal.mockReset().mockResolvedValue({ data: proposal({ status: "rejected" }) });
});

const mount = (props: Record<string, unknown>) => mountWithPlugins(ProposalDiffViewer, { locale: "en", props });

describe("ProposalDiffViewer", () => {
  it("shows the labelled diff and the rationale, and hides review actions from non-reviewers", () => {
    const wrapper = mount({ proposal: proposal(), canReview: false });

    expect(wrapper.get("[data-testid=field-diff]").text()).toContain("Biography (English)");
    expect(wrapper.text()).toContain("Old bio.");
    expect(wrapper.text()).toContain("New bio.");
    expect(wrapper.get("[data-testid=rationale]").text()).toBe("Page 12 of the catalogue.");
    expect(wrapper.find("[data-testid=approve]").exists()).toBe(false);
  });

  it("approves with the review note", async () => {
    const wrapper = mount({ proposal: proposal(), canReview: true });

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
    const wrapper = mount({ proposal: proposal(), canReview: true });

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
    const wrapper = mount({ proposal: proposal(), canReview: true });

    await wrapper.get("[data-testid=reject]").trigger("click");
    await flushPromises();
    expect(api.rejectProposal).not.toHaveBeenCalled();

    await wrapper.get("[data-testid=review-note-input]").setValue("No source given.");
    await wrapper.get("[data-testid=reject]").trigger("click");
    await flushPromises();
    expect(api.rejectProposal).toHaveBeenCalledWith("p1", "No source given.");
  });

  it("shows the reviewer's note instead of actions once reviewed", () => {
    const wrapper = mount({ proposal: proposal({ status: "rejected", review_note: "Not supported.", reviewed_by: { id: 1, name: "Munir" } }), canReview: true });

    expect(wrapper.get("[data-testid=review-note]").text()).toContain("Not supported.");
    expect(wrapper.find("[data-testid=approve]").exists()).toBe(false);
  });
});

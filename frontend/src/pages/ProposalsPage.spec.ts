import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";

import ProposalsPage from "@/pages/ProposalsPage.vue";
import { useAuthStore } from "@/stores/auth";
import { mountWithPlugins } from "@/test/utils";

const api = vi.hoisted(() => ({ listProposals: vi.fn() }));
vi.mock("@/api/proposals", () => ({ listProposals: api.listProposals }));

function signIn(permissions: string[]) {
  const pinia = createPinia();
  setActivePinia(pinia);
  useAuthStore().$patch({ user: { id: 3, name: "Nora", email: "n@x", roles: [], permissions } as never, initialized: true });
  return pinia;
}

async function mountPage(permissions: string[]) {
  const pinia = signIn(permissions);
  const wrapper = mountWithPlugins(ProposalsPage, { locale: "en", pinia });
  await flushPromises();
  return wrapper;
}

const emptyResponse = { data: [], links: [], meta: { current_page: 1, last_page: 1, per_page: 20, total: 0, from: null, to: null } };

const proposal = {
  id: "p1",
  record: { type: "artists", id: 7, label: "Ahmad" },
  status: "pending",
  review_type: "second_source_needed",
  field_diffs: { bio_en: { old_value_at_proposal_time: null, proposed_value: "A fuller biography." } },
  field_labels: {},
  rationale: "Found it in the catalogue.",
  proposed_citations: [],
  proposed_by: { id: 9, name: "Salma" },
  reviewed_by: null,
  reviewed_at: null,
  review_note: null,
  resulting_revision_id: null,
  created_at: "2026-09-20T10:00:00Z",
};

describe("ProposalsPage", () => {
  beforeEach(() => {
    api.listProposals.mockReset().mockResolvedValue(emptyResponse);
  });

  it("points an empty-state contributor to an artist or artwork page to submit their first suggestion", async () => {
    const wrapper = await mountPage(["proposals.submit"]);

    expect(wrapper.find("[data-testid=proposals-list]").exists()).toBe(false);
    const hint = wrapper.get("[data-testid=empty-submit-hint]");
    expect(hint.text()).toContain("open any artist or artwork page");
    expect(hint.text()).toContain("Send a correction");
  });

  it("shows no submit hint to a reviewer", async () => {
    const wrapper = await mountPage(["artists.manage"]);

    expect(wrapper.find("[data-testid=empty-submit-hint]").exists()).toBe(false);
  });

  it("shows the full queue to a reviewer holding review_queue permissions, without record-manage permissions", async () => {
    api.listProposals.mockResolvedValue({ data: [proposal], links: [], meta: { current_page: 1, last_page: 1, per_page: 20, total: 1, from: 1, to: 1 } });
    const wrapper = await mountPage(["review_queue.second_source_needed"]);

    expect(wrapper.get("h1").text()).toBe("Review queue");
    expect(wrapper.find("[data-testid=review-type-filter]").exists()).toBe(true);
    expect(wrapper.get("[data-testid=proposals-list]").text()).toContain("Ahmad");
  });
});
